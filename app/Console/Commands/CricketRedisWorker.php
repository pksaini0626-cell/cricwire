<?php

namespace App\Console\Commands;

use App\Http\Controllers\Cricket\SessionController;
use App\Models\ManagedMatch;
use App\Models\SkippedSeries;
use App\Services\Cricket\CricketLiveApiService;
use App\Support\LiveEngineConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CricketRedisWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cricket:redis-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Continuously syncs CricketLiveAPI data into Redis RAM with quota and viewer protection';

    /**
     * Track scorecard fetch cycle counter per matchId.
     *
     * @var array<int|string, int>
     */
    protected array $matchScorecardCycles = [];

    /**
     * Execute the console command.
     */
    public function handle(CricketLiveApiService $cricketService): void
    {
        $this->info('[' . now()->toDateTimeString() . '] Starting pure in-memory Cricket Redis Worker daemon...');

        while (true) {
            try {
                // Step 1 — Admin Master Switch
                if (!LiveEngineConfig::isEnabled()) {
                    $this->line('[' . now()->toDateTimeString() . '] Live engine disabled via Admin settings. Sleeping 10s...');
                    sleep(10);
                    continue;
                }

                // Step 2 — Daily Quota Guard (Hard stop at 4500 calls/day in production; 300 in local)
                $dailyCallKey = 'cricketliveapi:calls:' . now()->format('Y-m-d');
                $dailyCalls = (int) Cache::get($dailyCallKey, 0);
                if (app()->environment('local') && $dailyCalls >= 300) {
                    $this->warn('[' . now()->toDateTimeString() . "] Local daily API quota cap reached ({$dailyCalls}/300 calls). Stopping polling & sleeping 60s...");
                    Log::warning("CricketRedisWorker: Local daily API quota cap reached ({$dailyCalls}/300 calls). Sleeping 60s.");
                    sleep(60);
                    continue;
                }

                if ($dailyCalls >= 4500) {
                    $this->warn('[' . now()->toDateTimeString() . "] Daily API quota limit reached ({$dailyCalls}/4500 calls). Sleeping 60s...");
                    Log::warning("CricketRedisWorker: Daily API quota reached ({$dailyCalls}/4500 calls). Sleeping 60s.");
                    sleep(60);
                    continue;
                }

                // Step 2.5 — Active-Visitor 1-Hour Home Refresh Gate (24 Calls/Day When Idle)
                $hasActiveVisitors = Cache::has('active_site_visitor');
                if (!$hasActiveVisitors) {
                    $lastHomeSync = (int) Cache::get('cricket:idle:last_home_sync', 0);
                    // When idle, refresh homepage at most once every 3600 seconds (1 hour = 24 calls/day)
                    if (time() - $lastHomeSync < 3600 && Cache::has('cricket:redis:home')) {
                        $this->line('[' . now()->toDateTimeString() . '] 0 active site visitors. Homepage in 1-hour sleep mode. Sleeping 60s...');
                        sleep(60);
                        continue;
                    }
                    Cache::put('cricket:idle:last_home_sync', time(), 7200);
                }

                // Step 3 — Home Feed Sync
                // Fetch live fresh, upcoming & recent cached in Redis for 1800s (30m)
                $live = $cricketService->getLiveMatches(true);

                $upcoming = Cache::remember('cricket:redis:feed:upcoming', 1800, function () use ($cricketService) {
                    return $cricketService->getUpcomingMatches(true);
                });

                $recent = Cache::remember('cricket:redis:feed:recent', 1800, function () use ($cricketService) {
                    return $cricketService->getRecentMatches(true);
                });

                // Filter out matches not covered or in skipped series
                $skippedMatchIds = class_exists(ManagedMatch::class)
                    ? ManagedMatch::where('is_covered', false)->pluck('match_id')->map(fn ($id) => (int) $id)->flip()->all()
                    : [];

                $filterCovered = function (array $matches) use ($skippedMatchIds) {
                    return array_values(array_filter($matches, function ($m) use ($skippedMatchIds) {
                        if (!is_array($m)) {
                            return false;
                        }
                        $mId = (int) ($m['match_id'] ?? $m['id'] ?? 0);
                        return !isset($skippedMatchIds[$mId]);
                    }));
                };

                // Deduplicate and re-classify matches into strict buckets: finished, live, upcoming
                $sanitized = CricketLiveApiService::sanitizeMatchBuckets($live, $upcoming, $recent);
                $classifiedLive = $sanitized['live'];
                $classifiedUpcoming = $sanitized['upcoming'];
                $classifiedRecent = $sanitized['recent'];

                $coveredLiveMatches = $cricketService->filterAllowedMatches($classifiedLive);
                $coveredUpcoming = $cricketService->filterAllowedMatches($classifiedUpcoming);
                $coveredRecent = $cricketService->filterAllowedMatches($classifiedRecent);

                $homePayload = [
                    'live' => $coveredLiveMatches,
                    'upcoming' => $coveredUpcoming,
                    'recent' => $coveredRecent,
                    'updated_at' => now()->toIso8601String(),
                    'timestamp' => time(),
                ];

                $cricketService->writeHomeToRedis($homePayload);

                // Step 4 — Smart Sleep Mode (0 Live Matches)
                if (count($coveredLiveMatches) === 0) {
                    $this->line('[' . now()->toDateTimeString() . '] No live matches active. Sleeping 10 minutes (600s)...');
                    Log::info('CricketRedisWorker: No live matches active. Sleeping 10 minutes (600s)...');
                    sleep(600);
                    continue;
                }

                // Step 5 — STRICT Watcher-Only Live Match Detail Sync (Quota Saver)
                foreach ($coveredLiveMatches as $baseMatch) {
                    $matchId = (int) ($baseMatch['match_id'] ?? $baseMatch['id'] ?? 0);
                    if ($matchId <= 0 || $cricketService->isSeriesSkipped($baseMatch)) {
                        continue;
                    }

                    // STRICT Watcher / Active User check:
                    // If no active visitors on site and match not watched, poll at most once every 120s
                    $hasActiveVisitors = Cache::has('active_site_visitor');
                    $isWatching = Cache::has("watching:match:{$matchId}") || Cache::has("cricket:watchers:match:{$matchId}");

                    $lastPollTs = (int) Cache::get("cricket:redis:last_poll:{$matchId}", 0);
                    $nowTs = time();
                    $secondsSinceLastPoll = $nowTs - $lastPollTs;

                    $isEco = $cricketService->isEcoModeActive();
                    $unwatchedLimit = $isEco ? 240 : 120;
                    $idleHomeLimit = $isEco ? 70 : 35;

                    if (!$isWatching && !$hasActiveVisitors) {
                        // Unwatched live match with 0 active users: poll at most once every 120s (240s in Eco)
                        if ($secondsSinceLastPoll < $unwatchedLimit) {
                            continue;
                        }
                    } elseif (!$isWatching && $hasActiveVisitors) {
                        // Users active on homepage but match not directly watched: poll at most every 35s (70s in Eco)
                        if ($secondsSinceLastPoll < $idleHomeLimit) {
                            continue;
                        }
                    }

                    // Format-wise and break-aware dynamic interval
                    $dynamicConfig = $cricketService->getDynamicTtls($matchId, $baseMatch);
                    $pollInterval = (int) ($dynamicConfig['poll_interval_ms'] / 1000);

                    // Respect Admin settings as floor if configured higher
                    $configuredInterval = match ($dynamicConfig['format']) {
                        'TEST' => LiveEngineConfig::pollIntervalTest(),
                        'ODI' => LiveEngineConfig::pollIntervalOdi(),
                        default => LiveEngineConfig::pollIntervalT20(),
                    };
                    $pollInterval = max($pollInterval, $configuredInterval);

                    if ($isWatching) {
                        // Actively watched match: poll every 8 seconds for T20/ODI (12s for TEST, 16s in Eco mode)
                        $pollInterval = $isEco ? 16 : ($dynamicConfig['format'] === 'TEST' ? min($pollInterval, 12) : 8);
                    }

                    if ($secondsSinceLastPoll < $pollInterval) {
                        continue;
                    }

                    // Mark last poll timestamp in Redis (TTL 120s)
                    Cache::put("cricket:redis:last_poll:{$matchId}", $nowTs, 120);

                    // Fetch commentary (always fresh upstream HTTP call)
                    $commRes = $cricketService->getCommentary($matchId);

                    // Fetch scorecard fresh when watched (every 2 cycles = ~16s) or per format cycle
                    $cycleCount = ($this->matchScorecardCycles[$matchId] ?? 0) + 1;
                    $this->matchScorecardCycles[$matchId] = $cycleCount;
                    $scorecardCycleInterval = $isWatching ? 2 : ($dynamicConfig['in_break'] ? 4 : 3);
                    $shouldFetchScorecard = ($cycleCount % $scorecardCycleInterval === 1);

                    $scorecardCacheKey = "live:match:{$matchId}:scorecard_16s";
                    $scorecardTtl = 16;
                    if ($shouldFetchScorecard) {
                        $scorecardRes = $cricketService->getScorecard($matchId);
                        if (!empty($scorecardRes['innings'])) {
                            Cache::put($scorecardCacheKey, $scorecardRes, $scorecardTtl);
                        }
                    } else {
                        $scorecardRes = Cache::remember($scorecardCacheKey, $scorecardTtl, fn () => $cricketService->getScorecard($matchId));
                    }

                    // Match Facts with smart toss-aware TTL (30s when pending/empty, 24h when decided)
                    $matchFacts = $cricketService->getMatchFacts($matchId, $baseMatch);

                    // Build unified payload with server_updated_at = now()->toIso8601String()
                    $matchPayload = $cricketService->buildLiveMatchPayload(
                        $matchId,
                        $baseMatch,
                        is_array($commRes) ? $commRes : [],
                        is_array($scorecardRes) ? $scorecardRes : [],
                        is_array($matchFacts) ? $matchFacts : [],
                        $dynamicConfig
                    );

                    // Invoke SessionController tick if market rates & sessions enabled
                    if (LiveEngineConfig::isMarketRatesEnabled()) {
                        try {
                            $fancyData = $baseMatch['session'] ?? $commRes['fancy'] ?? [];
                            SessionController::recordLiveSessionTick($matchId, $fancyData, $matchPayload);
                        } catch (\Throwable $sessionEx) {
                            Log::debug("Session tick error for match {$matchId}: " . $sessionEx->getMessage());
                        }
                    }

                    // Write normalized match detail to Redis (TTL 10s for live) and short-lived live payload key
                    $cricketService->writeMatchToRedis($matchId, $matchPayload);
                    Cache::put("cricket:live_match_payload:{$matchId}", $matchPayload, 10);

                    $this->line('[' . now()->toDateTimeString() . "] Synced match {$matchId} ({$matchPayload['team_a_short']} vs {$matchPayload['team_b_short']}) | Viewers: " . ($isWatching ? 'YES' : 'NO'));
                }

                // Sleep 2 seconds at the end of each active loop iteration
                sleep(2);
            } catch (\Throwable $e) {
                $this->error('[' . now()->toDateTimeString() . '] CricketRedisWorker exception: ' . $e->getMessage());
                Log::error('CricketRedisWorker daemon exception: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                sleep(5);
            }
        }
    }
}
