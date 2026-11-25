<?php

namespace App\Http\Controllers\Cricket;

use App\Helpers\CricketMatchFormatter;
use App\Http\Controllers\Controller;
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
                $stored['is_live'] = false;
                $stored['is_completed'] = true;
                $stored['commentary_unavailable'] = true;
                return $stored;
            }
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

        $scorecard = [];
        try {
            $scorecardRes = $apiService->getScorecard($matchKey);
            $scorecard = $scorecardRes['innings'] ?? $scorecardRes ?? [];
        } catch (\Throwable $e) {
            Log::warning("FinishedMatchController: getScorecard failed for {$matchKey}: " . $e->getMessage());
        }

        if (empty($scorecard) && !empty($baseMatch['scorecard']) && is_array($baseMatch['scorecard'])) {
            $scorecard = $baseMatch['scorecard'];
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
        $normalized['commentary_unavailable'] = true;

        try {
            FinishedMatch::updateOrCreate(
                ['match_id' => $matchKey],
                [
                    'series_name' => $normalized['series'] ?? $normalized['series_name'] ?? null,
                    'match_title' => $normalized['matchs'] ?? $normalized['match_desc'] ?? trim(($normalized['team_a'] ?? '') . ' vs ' . ($normalized['team_b'] ?? '')),
                    'result_text' => $normalized['result'] ?? $normalized['result_text'] ?? null,
                    'score_a' => $scores['score_a'],
                    'score_b' => $scores['score_b'],
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

        return $normalized;
    }

    /**
     * Return merged finished matches from DB archive + Redis/static recent feed.
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

            $scores = MatchStateNormalizer::normalizeScores($match, [], 'finished');
            $match['score_a'] = $scores['score_a'];
            $match['team_a_scores'] = $scores['score_a'];
            $match['score_b'] = $scores['score_b'];
            $match['team_b_scores'] = $scores['score_b'];
            $match['is_live'] = false;
            $match['is_completed'] = true;

            $finished[] = CricketMatchFormatter::format($match);
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
                $payload['is_live'] = false;
                $payload['is_completed'] = true;
                $finished[] = CricketMatchFormatter::format($payload);
            }
        }

        return $finished;
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
        $items = array_slice($all, $offset, $perPage);

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
