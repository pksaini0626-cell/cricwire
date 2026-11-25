<?php

namespace App\Http\Controllers\Cricket;

use App\Helpers\CricketMatchFormatter;
use App\Http\Controllers\Controller;
use App\Models\CompletedMatchScorecard;
use App\Models\FinishedMatch;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Cricket\MatchStateNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FinishedMatchController extends Controller
{
    /**
     * Return a flipped map [match_id => true] of all archived finished matches.
     */
    public static function getArchivedMatchIds(): array
    {
        return Cache::remember('cricket:finished_matches:ids', 60, function () {
            return FinishedMatch::query()
                ->pluck('match_id')
                ->map(fn ($id) => (string) $id)
                ->flip()
                ->all();
        });
    }

    /**
     * Archive a match evicted from Live directly into finished_matches.
     */
    public function archiveFromLive(array $match): array
    {
        $matchId = (string) ($match['match_id'] ?? $match['id'] ?? '');
        if ($matchId === '') {
            return $match;
        }

        return $this->getOrArchive($matchId, $match);
    }

    /**
     * Load a finished match from DB (0 external API calls) or fetch once,
     * normalize both teams' scores via MatchStateNormalizer, persist, and return.
     */
    public function getOrArchive(string|int $matchId, array $contextMatch = []): array
    {
        $matchKey = (string) $matchId;

        $existing = FinishedMatch::where('match_id', $matchKey)->first();
        if ($existing) {
            $stored = is_array($existing->match_data) && !empty($existing->match_data)
                ? $existing->match_data
                : $existing->match_json;

            if (is_array($stored) && !empty($stored)) {
                $scores = MatchStateNormalizer::normalizeScores($stored, $stored['scorecard'] ?? [], 'finished');
                $stored['score_a'] = $existing->score_a ?: $scores['score_a'];
                $stored['team_a_scores'] = $stored['score_a'];
                $stored['score_b'] = $existing->score_b ?: $scores['score_b'];
                $stored['team_b_scores'] = $stored['score_b'];
                $stored['result'] = $stored['result'] ?? $existing->result_text ?? '';
                $stored['is_live'] = false;
                $stored['is_completed'] = true;
                $stored['commentary_unavailable'] = true;
                return $stored;
            }
        }

        // Check CompletedMatchScorecard table before calling external API
        $cms = CompletedMatchScorecard::where('match_id', $matchKey)->first();
        $scorecard = [];
        if ($cms && is_array($cms->scorecard_payload) && !empty($cms->scorecard_payload)) {
            $scorecard = $cms->scorecard_payload['innings'] ?? $cms->scorecard_payload;
        }

        $apiService = app(CricketLiveApiService::class);
        $baseMatch = $contextMatch;

        if (empty($baseMatch)) {
            try {
                $baseMatch = $apiService->getFastMatchData($matchKey) ?? [];
            } catch (\Throwable $e) {
                Log::warning("FinishedMatchController: getFastMatchData failed for {$matchKey}: " . $e->getMessage());
                $baseMatch = [];
            }
        }

        $rawScorecardRes = [];
        if (empty($scorecard) && !$apiService->isHardStopActive()) {
            try {
                $rawScorecardRes = $apiService->getScorecard($matchKey);
                $scorecard = $rawScorecardRes['innings'] ?? $rawScorecardRes ?? [];
            } catch (\Throwable $e) {
                Log::warning("FinishedMatchController: getScorecard failed for {$matchKey}: " . $e->getMessage());
            }
        }

        if (empty($scorecard) && !empty($baseMatch['scorecard']) && is_array($baseMatch['scorecard'])) {
            $scorecard = $baseMatch['scorecard'];
        }

        // Extract result/status text from raw scorecard metadata if missing on list item
        if (empty($baseMatch['result']) && !empty($rawScorecardRes['raw']) && is_array($rawScorecardRes['raw'])) {
            $rawData = $rawScorecardRes['raw']['data'] ?? $rawScorecardRes['raw'];
            $candidateResult = $rawData['result'] ?? $rawData['status_note'] ?? $rawData['status'] ?? ($rawData['match_header']['status'] ?? '');
            if (is_string($candidateResult) && trim($candidateResult) !== '') {
                $baseMatch['result'] = trim($candidateResult);
            }
        }

        $scores = MatchStateNormalizer::normalizeScores($baseMatch, is_array($scorecard) ? $scorecard : [], 'finished');

        $normalized = $baseMatch;
        $normalized['match_id'] = $normalized['match_id'] ?? $matchKey;
        $normalized['score_a'] = $scores['score_a'];
        $normalized['team_a_scores'] = $scores['score_a'];
        $normalized['score_b'] = $scores['score_b'];
        $normalized['team_b_scores'] = $scores['score_b'];
        $normalized['scorecard'] = $scorecard;
        $normalized['is_live'] = false;
        $normalized['is_completed'] = true;
        $normalized['match_status'] = 'Finished';
        $normalized['state'] = 'Finished';
        $normalized['commentary_unavailable'] = true;

        $hasPersistableData = !MatchStateNormalizer::isEmptyScore($scores['score_a'])
            || !MatchStateNormalizer::isEmptyScore($scores['score_b'])
            || !empty($scorecard)
            || !empty($normalized['result']);

        if ($hasPersistableData) {
            try {
                FinishedMatch::updateOrCreate(
                    ['match_id' => $matchKey],
                    [
                        'series_name' => $normalized['series'] ?? $normalized['series_name'] ?? null,
                        'match_title' => $normalized['matchs'] ?? $normalized['match_desc'] ?? trim(($normalized['team_a'] ?? '') . ' vs ' . ($normalized['team_b'] ?? '')),
                        'result_text' => $normalized['result'] ?? $normalized['result_text'] ?? null,
                        'score_a' => $scores['score_a'] ?: null,
                        'score_b' => $scores['score_b'] ?: null,
                        'match_data' => $normalized,
                        'match_json' => $normalized,
                        'fetched_at' => now(),
                        'archived_at' => now(),
                    ]
                );
                Cache::forget('cricket:finished_matches:ids');
            } catch (\Throwable $e) {
                Log::error("FinishedMatchController: failed to archive {$matchKey}: " . $e->getMessage());
            }
        }

        return $normalized;
    }

    /**
     * Return merged finished matches from DB archive + Redis/static recent feed.
     * Merges archived FinishedMatch & CompletedMatchScorecard scores in a single batch query (0 API calls).
     */
    public function getFinishedMatches(): array
    {
        $apiService = app(CricketLiveApiService::class);
        $homeData = $apiService->getHomeFromRedis();
        $rawRecent = $homeData['recent'] ?? ($homeData['finished'] ?? []);

        if (empty($rawRecent)) {
            $staticHomeFile = public_path('live-data/home.json');
            if (file_exists($staticHomeFile)) {
                $decoded = json_decode(@file_get_contents($staticHomeFile), true);
                if (is_array($decoded)) {
                    $rawRecent = $decoded['recent'] ?? ($decoded['finished'] ?? []);
                }
            }
        }

        $candidates = $apiService->filterAllowedMatches($rawRecent);
        $matchIds = [];
        foreach ($candidates as $m) {
            if (!is_array($m)) {
                continue;
            }
            $id = (string) ($m['match_id'] ?? $m['id'] ?? '');
            if ($id !== '') {
                $matchIds[] = $id;
            }
        }
        $matchIds = array_values(array_unique($matchIds));

        // Batch-load archived DB records (0 API calls)
        $archivedRows = !empty($matchIds)
            ? FinishedMatch::whereIn('match_id', $matchIds)->get()->keyBy('match_id')
            : collect();
        $scorecardRows = !empty($matchIds)
            ? CompletedMatchScorecard::whereIn('match_id', $matchIds)->get()->keyBy('match_id')
            : collect();

        $finished = [];
        $seenIds = [];

        foreach ($candidates as $match) {
            if (!is_array($match)) {
                continue;
            }
            $matchId = (string) ($match['match_id'] ?? $match['id'] ?? '');
            if ($matchId === '' || isset($seenIds[$matchId])) {
                continue;
            }
            $seenIds[$matchId] = true;

            $match = $this->mergeArchivedDbData($match, $archivedRows->get($matchId), $scorecardRows->get($matchId));
            $finished[] = CricketMatchFormatter::formatMatchForCard($match);
        }

        // Also include recently archived DB matches not already in feed
        $dbArchived = FinishedMatch::query()
            ->orderByDesc('archived_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        foreach ($dbArchived as $row) {
            $mId = (string) $row->match_id;
            if (isset($seenIds[$mId])) {
                continue;
            }
            $payload = is_array($row->match_data) && !empty($row->match_data) ? $row->match_data : $row->match_json;
            if (is_array($payload) && !empty($payload)) {
                $seenIds[$mId] = true;
                $payload = $this->mergeArchivedDbData($payload, $row, null);
                $finished[] = CricketMatchFormatter::formatMatchForCard($payload);
            }
        }

        return $finished;
    }

    /**
     * Auto-hydrate missing scores for the current page (max 10 items) from DB or scorecard API once.
     */
    public function hydratePageMatches(array $pageMatches): array
    {
        $apiService = app(CricketLiveApiService::class);
        $hydrated = [];

        foreach ($pageMatches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $matchId = (string) ($match['match_id'] ?? $match['id'] ?? '');
            $scoreA = (string) ($match['score_a'] ?? $match['team_a_scores'] ?? '');
            $scoreB = (string) ($match['score_b'] ?? $match['team_b_scores'] ?? '');

            $missingScores = MatchStateNormalizer::isEmptyScore($scoreA) || MatchStateNormalizer::isEmptyScore($scoreB);

            if ($matchId !== '' && $missingScores && !$apiService->isHardStopActive()) {
                $skipKey = "cricket:finished_hydrate_skip:{$matchId}";
                if (!Cache::has($skipKey)) {
                    $lock = Cache::lock("lock:finished_hydrate:{$matchId}", 5);
                    if ($lock->get()) {
                        try {
                            $archived = $this->getOrArchive($matchId, $match);
                            if (!MatchStateNormalizer::isEmptyScore($archived['score_a'] ?? '')
                                || !MatchStateNormalizer::isEmptyScore($archived['score_b'] ?? '')) {
                                $match = array_merge($match, [
                                    'score_a' => $archived['score_a'] ?? '',
                                    'team_a_scores' => $archived['score_a'] ?? '',
                                    'score_b' => $archived['score_b'] ?? '',
                                    'team_b_scores' => $archived['score_b'] ?? '',
                                    'result' => $archived['result'] ?? ($match['result'] ?? ''),
                                    'state' => 'Finished',
                                    'match_status' => 'Finished',
                                ]);
                            } else {
                                // Back off 10 minutes if upstream genuinely has no scorecard yet
                                Cache::put($skipKey, true, 600);
                            }
                        } catch (\Throwable $e) {
                            Log::warning("FinishedMatchController: hydratePageMatches failed for {$matchId}: " . $e->getMessage());
                        } finally {
                            $lock->release();
                        }
                    }
                }
            }

            $match['is_live'] = false;
            $match['is_completed'] = true;
            $match['match_status'] = 'Finished';
            $match['state'] = 'Finished';

            $hydrated[] = CricketMatchFormatter::formatMatchForCard($match);
        }

        return $hydrated;
    }

    /**
     * Merge archived FinishedMatch / CompletedMatchScorecard data into a list match item.
     */
    protected function mergeArchivedDbData(array $match, ?FinishedMatch $archivedRow, ?CompletedMatchScorecard $scorecardRow): array
    {
        $scorecard = [];

        if ($archivedRow) {
            $stored = is_array($archivedRow->match_data) && !empty($archivedRow->match_data)
                ? $archivedRow->match_data
                : (is_array($archivedRow->match_json) ? $archivedRow->match_json : []);

            if (!empty($stored['scorecard']) && is_array($stored['scorecard'])) {
                $scorecard = $stored['scorecard'];
            }

            // Overwrite empty fields with archived DB values
            foreach (['score_a', 'team_a_scores', 'team_a_over', 'score_b', 'team_b_scores', 'team_b_over', 'result', 'status_note'] as $key) {
                if (empty($match[$key]) && !empty($stored[$key])) {
                    $match[$key] = $stored[$key];
                }
            }

            if (empty($match['score_a']) && !empty($archivedRow->score_a)) {
                $match['score_a'] = $archivedRow->score_a;
                $match['team_a_scores'] = $archivedRow->score_a;
            }
            if (empty($match['score_b']) && !empty($archivedRow->score_b)) {
                $match['score_b'] = $archivedRow->score_b;
                $match['team_b_scores'] = $archivedRow->score_b;
            }
            if (empty($match['result']) && !empty($archivedRow->result_text)) {
                $match['result'] = $archivedRow->result_text;
            }
        }

        if ($scorecardRow && is_array($scorecardRow->scorecard_payload) && !empty($scorecardRow->scorecard_payload)) {
            if (empty($scorecard)) {
                $scorecard = $scorecardRow->scorecard_payload['innings'] ?? $scorecardRow->scorecard_payload;
            }
            if (empty($match['result']) && !empty($scorecardRow->result_text)) {
                $match['result'] = $scorecardRow->result_text;
            }
        }

        $scores = MatchStateNormalizer::normalizeScores($match, is_array($scorecard) ? $scorecard : [], 'finished');
        $match['score_a'] = $scores['score_a'];
        $match['team_a_scores'] = $scores['score_a'];
        $match['score_b'] = $scores['score_b'];
        $match['team_b_scores'] = $scores['score_b'];
        $match['is_live'] = false;
        $match['is_completed'] = true;
        $match['match_status'] = 'Finished';
        $match['state'] = 'Finished';

        return $match;
    }

    /**
     * GET /api/matches/finished?page=1&per_page=10
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));

        $all = $this->getFinishedMatches();
        $total = count($all);
        $offset = ($page - 1) * $perPage;
        $items = $this->hydratePageMatches(array_slice($all, $offset, $perPage));

        return response()->json([
            'status' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * GET /api/matches/finished/{matchId}
     */
    public function show(string|int $matchId): JsonResponse
    {
        $data = $this->getOrArchive($matchId);

        return response()->json([
            'status' => true,
            'is_live' => false,
            'is_completed' => true,
            'commentary_unavailable' => true,
            'data' => $data,
        ]);
    }
}
