<?php

namespace App\Http\Controllers\Cricket;

use App\Helpers\CricketMatchFormatter;
use App\Http\Controllers\Controller;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Cricket\MatchStateNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LiveMatchController extends Controller
{
    public function __construct(
        protected CricketLiveApiService $apiService,
        protected FinishedMatchController $finishedController
    ) {}

    /**
     * Return ONLY active 'live' matches, excluding any archived finished matches
     * and auto-evicting newly finished matches to FinishedMatchController.
     */
    public function getLiveMatches(): array
    {
        $archivedIds = FinishedMatchController::getArchivedMatchIds();

        $homeData = $this->apiService->getHomeFromRedis();
        if (empty($homeData['live'])) {
            $staticHomeFile = public_path('live-data/home.json');
            if (file_exists($staticHomeFile)) {
                $decoded = json_decode(@file_get_contents($staticHomeFile), true);
                if (is_array($decoded) && !empty($decoded['live'])) {
                    $homeData['live'] = $decoded['live'];
                }
            }
        }

        $candidates = $this->apiService->filterAllowedMatches($homeData['live'] ?? []);
        $liveMatches = [];
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

            $bucket = MatchStateNormalizer::classify($match, $archivedIds);

            if ($bucket === 'finished') {
                // Evict immediately from Live and archive to Finished
                $this->finishedController->archiveFromLive($match);
                continue;
            }

            if ($bucket !== 'live') {
                continue;
            }

            $match = $this->hydrateLiveScores($match, $matchId);
            $match['is_live'] = true;
            $match['is_completed'] = false;

            $liveMatches[] = CricketMatchFormatter::format($match);
        }

        return $liveMatches;
    }

    /**
     * Hydrate missing Team A / Team B scores for active live matches (e.g., 2nd innings chase)
     * using cached scorecard data protected by Cache::lock and daily API quota guard.
     */
    protected function hydrateLiveScores(array $match, string $matchId): array
    {
        $scores = MatchStateNormalizer::normalizeScores($match, [], 'live');

        $needsScorecard = MatchStateNormalizer::isEmptyScore($scores['score_a'])
            || MatchStateNormalizer::isEmptyScore($scores['score_b']);

        $hasActiveChase = trim((string) ($match['need_run_ball'] ?? '')) !== ''
            || (int) ($match['target'] ?? $match['target_runs'] ?? 0) > 0;

        if ($needsScorecard && $hasActiveChase && !$this->apiService->isHardStopActive()) {
            $scorecard = Cache::remember("cricket:live_scorecard:{$matchId}", 30, function () use ($matchId) {
                $lock = Cache::lock("lock:live_scorecard:{$matchId}", 5);
                if ($lock->get()) {
                    try {
                        return $this->apiService->getScorecard($matchId) ?? [];
                    } catch (\Throwable $e) {
                        Log::warning("LiveMatchController: scorecard hydration failed for {$matchId}: " . $e->getMessage());
                        return [];
                    } finally {
                        $lock->release();
                    }
                }
                return [];
            });

            if (!empty($scorecard)) {
                $scores = MatchStateNormalizer::normalizeScores($match, $scorecard, 'live');
            }
        }

        $match['score_a'] = $scores['score_a'];
        $match['team_a_scores'] = $scores['score_a'];
        $match['score_b'] = $scores['score_b'];
        $match['team_b_scores'] = $scores['score_b'];

        return $match;
    }

    /**
     * GET /api/matches/live (and /api/live/home)
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->attributes->get('is_search_crawler') && !app()->bound('crawler_read_only_mode')) {
            Cache::put('active_site_visitor', now()->timestamp, 120);
        }

        $live = $this->getLiveMatches();

        return response()
            ->json([
                'status' => true,
                'live' => $live,
                'count' => count($live),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * GET /api/matches/live/{matchId} (and /api/live/match/{matchId})
     */
    public function show(string|int $matchId, Request $request): JsonResponse
    {
        $matchKey = (string) $matchId;

        if (!$request->attributes->get('is_search_crawler') && !app()->bound('crawler_read_only_mode')) {
            Cache::put('active_site_visitor', now()->timestamp, 120);
            Cache::put("watching:match:{$matchKey}", true, 30);
        }

        $archivedIds = FinishedMatchController::getArchivedMatchIds();
        if (isset($archivedIds[$matchKey])) {
            return $this->finishedController->show($matchKey);
        }

        $includeCommentary = $request->boolean('include_commentary');
        $data = $this->apiService->getFastMatchData($matchKey, $includeCommentary) ?? [];

        if (!empty($data) && MatchStateNormalizer::classify($data, $archivedIds) === 'finished') {
            $archived = $this->finishedController->archiveFromLive($data);
            return response()
                ->json($archived)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        if (!empty($data)) {
            $scores = MatchStateNormalizer::normalizeScores($data, $data['scorecard'] ?? [], 'live');
            $data['score_a'] = $scores['score_a'];
            $data['team_a_scores'] = $scores['score_a'];
            $data['score_b'] = $scores['score_b'];
            $data['team_b_scores'] = $scores['score_b'];
            $data['is_live'] = true;
            $data['is_completed'] = false;
        }

        return response()
            ->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
