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
                        // Actively watched match: poll every 10–12 seconds (20-24s in Eco mode)
                        $pollInterval = $isEco ? 24 : min($pollInterval, 12);
                    }

                    if ($secondsSinceLastPoll < $pollInterval) {
                        continue;
                    }

                    // Mark last poll timestamp in Redis (TTL 120s)
                    Cache::put("cricket:redis:last_poll:{$matchId}", $nowTs, 120);

                    // Fetch commentary (fresh)
                    $commRes = $cricketService->getCommentary($matchId, true);
                    $commentaryData = $commRes['commentary'] ?? [];

                    // Fetch scorecard: break-aware or format-aware cycle
                    $cycleCount = ($this->matchScorecardCycles[$matchId] ?? 0) + 1;
                    $this->matchScorecardCycles[$matchId] = $cycleCount;
                    $scorecardCycleInterval = $dynamicConfig['in_break'] ? 4 : match ($dynamicConfig['format']) {
                        'TEST' => 3,
                        'ODI' => 3,
                        default => 3,
                    };
                    $shouldFetchScorecard = ($cycleCount % $scorecardCycleInterval === 1);

                    $scorecardCacheKey = "live:match:{$matchId}:scorecard_20s";
                    $scorecardTtl = $dynamicConfig['scorecard_ttl'];
                    if ($shouldFetchScorecard) {
                        $scorecardRes = $cricketService->getScorecard($matchId, true);
                        if (!empty($scorecardRes['innings'])) {
                            Cache::put($scorecardCacheKey, $scorecardRes, $scorecardTtl);
                        }
                    } else {
                        $scorecardRes = Cache::remember($scorecardCacheKey, $scorecardTtl, fn () => $cricketService->getScorecard($matchId));
                    }
                    $scorecardData = $scorecardRes['innings'] ?? [];

                    // Match Facts cached 24h
                    $matchFacts = $cricketService->getMatchFacts($matchId);

                    // Header normalization
                    if (empty($baseMatch['team_a'])) {
                        $header = $commRes['match_header'] ?? $scorecardRes['match_header'] ?? ($matchFacts['match_info'] ?? null);
                        if ($header) {
                            $baseMatch['team_a'] = $header['team1']['name'] ?? $baseMatch['team_a'] ?? 'Team A';
                            $baseMatch['team_b'] = $header['team2']['name'] ?? $baseMatch['team_b'] ?? 'Team B';
                            $baseMatch['team_a_short'] = $header['team1']['short'] ?? $baseMatch['team_a_short'] ?? CricketLiveApiService::generateTeamShort($baseMatch['team_a']);
                            $baseMatch['team_b_short'] = $header['team2']['short'] ?? $baseMatch['team_b_short'] ?? CricketLiveApiService::generateTeamShort($baseMatch['team_b']);
                            $baseMatch['series_name'] = $header['series_name'] ?? $baseMatch['series_name'] ?? 'Cricket Series';
                            $baseMatch['match_desc'] = $header['description'] ?? $header['match_desc'] ?? $baseMatch['match_desc'] ?? 'Match';
                            $baseMatch['format'] = $header['format'] ?? $baseMatch['format'] ?? 'T20';
                            $baseMatch['venue'] = $header['venue'] ?? $baseMatch['venue'] ?? ($matchFacts['venue'] ?? '');
                            $baseMatch['state'] = $header['state'] ?? $baseMatch['state'] ?? 'Live';
                            $baseMatch['status'] = $header['status'] ?? $baseMatch['status'] ?? 'Live';
                        }
                    }

                    // Score normalization
                    $scoreA = $baseMatch['score_a'] ?? $baseMatch['team_a_scores'] ?? $baseMatch['team_a_score'] ?? '';
                    $scoreB = $baseMatch['score_b'] ?? $baseMatch['team_b_scores'] ?? $baseMatch['team_b_score'] ?? '';
                    $combinedScore = $baseMatch['score'] ?? '';

                    if (!empty($scorecardData) && is_array($scorecardData)) {
                        $teamAName = strtolower(trim((string) ($baseMatch['team_a'] ?? '')));
                        $teamBName = strtolower(trim((string) ($baseMatch['team_b'] ?? '')));
                        $teamAShort = strtolower(trim((string) ($baseMatch['team_a_short'] ?? '')));
                        $teamBShort = strtolower(trim((string) ($baseMatch['team_b_short'] ?? '')));

                        foreach ($scorecardData as $inn) {
                            if (!is_array($inn)) continue;
                            $inTeam = $inn['bat_team'] ?? $inn['team']['name'] ?? $inn['team']['short_name'] ?? $inn['team_name'] ?? '';
                            $inTeamShort = $inn['bat_team_short'] ?? $inn['team']['short_name'] ?? '';
                            $inScore = trim((string) ($inn['score'] ?? ''));

                            if (empty($inScore) && isset($inn['team']['score'])) {
                                $r = $inn['team']['score'] ?? 0;
                                $w = $inn['team']['wicket'] ?? 0;
                                $ov = $inn['team']['over'] ?? '';
                                $inScore = "{$r}/{$w}" . ($ov ? " ({$ov} ov)" : '');
                            }

                            if (!empty($inScore)) {
                                $inTeamLower = strtolower(trim((string) $inTeam));
                                $inTeamShortLower = strtolower(trim((string) $inTeamShort));

                                $isTeamA = ($inTeamLower && ($inTeamLower === $teamAName || str_contains($teamAName, $inTeamLower) || str_contains($inTeamLower, $teamAName)))
                                    || ($inTeamShortLower && ($inTeamShortLower === $teamAShort || str_contains($teamAShort, $inTeamShortLower)));

                                $isTeamB = ($inTeamLower && ($inTeamLower === $teamBName || str_contains($teamBName, $inTeamLower) || str_contains($inTeamLower, $teamBName)))
                                    || ($inTeamShortLower && ($inTeamShortLower === $teamBShort || str_contains($teamBShort, $inTeamShortLower)));

                                if ($isTeamA && empty($scoreA)) {
                                    $scoreA = $inScore;
                                } elseif ($isTeamB && empty($scoreB)) {
                                    $scoreB = $inScore;
                                }
                            }
                        }
                    }

                    // Fallback to miniscore
                    $miniscore = $commRes['miniscore'] ?? null;
                    if ($miniscore && !empty($miniscore['innings_scores']) && is_array($miniscore['innings_scores'])) {
                        foreach ($miniscore['innings_scores'] as $innS) {
                            $innScoreStr = ($innS['score'] ?? 0) . '/' . ($innS['wickets'] ?? 0) . (isset($innS['overs']) ? " ({$innS['overs']} ov)" : '');
                            $innBatTeam = strtolower(trim((string) ($innS['bat_team'] ?? '')));
                            $tAShortLower = strtolower(trim((string) ($baseMatch['team_a_short'] ?? '')));
                            $tBShortLower = strtolower(trim((string) ($baseMatch['team_b_short'] ?? '')));

                            if (empty($scoreA) && ($innBatTeam === $tAShortLower || str_contains($tAShortLower, $innBatTeam))) {
                                $scoreA = $innScoreStr;
                            } elseif (empty($scoreB) && ($innBatTeam === $tBShortLower || str_contains($tBShortLower, $innBatTeam))) {
                                $scoreB = $innScoreStr;
                            }
                        }
                    }

                    if (empty($scoreA) && empty($scoreB) && !empty($combinedScore)) {
                        $scoreA = $combinedScore;
                    }
                    if (empty($combinedScore)) {
                        $combinedScore = $scoreA ?: $scoreB;
                    }

                    // Batters normalization
                    $batters = [];
                    if ($miniscore) {
                        if (!empty($miniscore['striker']['name'])) {
                            $batters[] = [
                                'name' => $miniscore['striker']['name'],
                                'runs' => $miniscore['striker']['runs'] ?? 0,
                                'run' => $miniscore['striker']['runs'] ?? 0,
                                'balls' => $miniscore['striker']['balls'] ?? 0,
                                'ball' => $miniscore['striker']['balls'] ?? 0,
                                'fours' => $miniscore['striker']['fours'] ?? 0,
                                'sixes' => $miniscore['striker']['sixes'] ?? 0,
                                'strike_rate' => $miniscore['striker']['strike_rate'] ?? '0.00',
                                'is_striker' => true,
                            ];
                        }
                        if (!empty($miniscore['non_striker']['name'])) {
                            $batters[] = [
                                'name' => $miniscore['non_striker']['name'],
                                'runs' => $miniscore['non_striker']['runs'] ?? 0,
                                'run' => $miniscore['non_striker']['runs'] ?? 0,
                                'balls' => $miniscore['non_striker']['balls'] ?? 0,
                                'ball' => $miniscore['non_striker']['balls'] ?? 0,
                                'fours' => $miniscore['non_striker']['fours'] ?? 0,
                                'sixes' => $miniscore['non_striker']['sixes'] ?? 0,
                                'strike_rate' => $miniscore['non_striker']['strike_rate'] ?? '0.00',
                                'is_striker' => false,
                            ];
                        }
                    }

                    if (empty($batters) && !empty($scorecardData) && is_array($scorecardData)) {
                        $activeInning = end($scorecardData);
                        if (is_array($activeInning)) {
                            $allBatsmen = $activeInning['batsmen'] ?? $activeInning['batsman'] ?? [];
                            foreach ($allBatsmen as $b) {
                                $outDesc = strtolower(trim((string) ($b['out_desc'] ?? $b['out_by'] ?? '')));
                                if ($outDesc === 'batting' || $outDesc === 'not out' || $outDesc === '' || str_contains($outDesc, 'not out')) {
                                    $batters[] = [
                                        'name' => $b['name'] ?? $b['short_name'] ?? 'Batter',
                                        'runs' => $b['runs'] ?? $b['run'] ?? 0,
                                        'run' => $b['runs'] ?? $b['run'] ?? 0,
                                        'balls' => $b['balls'] ?? $b['ball'] ?? 0,
                                        'ball' => $b['balls'] ?? $b['ball'] ?? 0,
                                        'fours' => $b['fours'] ?? 0,
                                        'sixes' => $b['sixes'] ?? 0,
                                        'strike_rate' => $b['strike_rate'] ?? '0.00',
                                        'is_striker' => (bool) ($b['is_striker'] ?? false),
                                    ];
                                }
                            }
                        }
                    }

                    if (empty($batters) && !empty($commentaryData[0]['batsman'])) {
                        $batters[] = [
                            'name' => $commentaryData[0]['batsman'],
                            'runs' => 0,
                            'run' => 0,
                            'balls' => 0,
                            'ball' => 0,
                            'fours' => 0,
                            'sixes' => 0,
                            'strike_rate' => '0.00',
                            'is_striker' => true,
                        ];
                    }

                    // Bowler normalization
                    $bowler = [];
                    if ($miniscore && !empty($miniscore['bowler_striker']['name'])) {
                        $b = $miniscore['bowler_striker'];
                        $bowler = [
                            'name' => $b['name'],
                            'overs' => $b['overs'] ?? $b['over'] ?? '0.0',
                            'over' => $b['overs'] ?? $b['over'] ?? '0.0',
                            'maidens' => $b['maidens'] ?? $b['maiden'] ?? 0,
                            'maiden' => $b['maidens'] ?? $b['maiden'] ?? 0,
                            'runs' => $b['runs'] ?? $b['run'] ?? 0,
                            'run' => $b['runs'] ?? $b['run'] ?? 0,
                            'wickets' => $b['wickets'] ?? $b['wicket'] ?? 0,
                            'wicket' => $b['wickets'] ?? $b['wicket'] ?? 0,
                            'economy' => $b['economy'] ?? '0.00',
                        ];
                    }

                    if (empty($bowler) && !empty($scorecardData) && is_array($scorecardData)) {
                        $activeInning = end($scorecardData);
                        if (is_array($activeInning)) {
                            $allBowlers = $activeInning['bowlers'] ?? $activeInning['bolwer'] ?? $activeInning['bowler'] ?? [];
                            if (!empty($allBowlers)) {
                                $lastB = end($allBowlers);
                                $bowler = [
                                    'name' => $lastB['name'] ?? $lastB['short_name'] ?? 'Bowler',
                                    'overs' => $lastB['overs'] ?? $lastB['over'] ?? '0.0',
                                    'over' => $lastB['overs'] ?? $lastB['over'] ?? '0.0',
                                    'maidens' => $lastB['maidens'] ?? $lastB['maiden'] ?? 0,
                                    'maiden' => $lastB['maidens'] ?? $lastB['maiden'] ?? 0,
                                    'runs' => $lastB['runs'] ?? $lastB['run'] ?? 0,
                                    'run' => $lastB['runs'] ?? $lastB['run'] ?? 0,
                                    'wickets' => $lastB['wickets'] ?? $lastB['wicket'] ?? 0,
                                    'wicket' => $lastB['wickets'] ?? $lastB['wicket'] ?? 0,
                                    'economy' => $lastB['economy'] ?? '0.00',
                                ];
                            }
                        }
                    }

                    if (empty($bowler) && !empty($commentaryData[0]['bowler'])) {
                        $bowler = [
                            'name' => $commentaryData[0]['bowler'],
                            'overs' => '0.0',
                            'over' => '0.0',
                            'maidens' => 0,
                            'maiden' => 0,
                            'runs' => 0,
                            'run' => 0,
                            'wickets' => 0,
                            'wicket' => 0,
                            'economy' => '0.00',
                        ];
                    }

                    if (empty($batters) || empty($bowler)) {
                        Log::warning("Live Match {$matchId} missing batters/bowler in CricketRedisWorker", [
                            'scorecard_keys' => is_array($scorecardRes) ? array_keys($scorecardRes) : gettype($scorecardRes),
                            'first_commentary' => is_array($commentaryData) ? ($commentaryData[0] ?? array_slice($commentaryData, 0, 1)) : null,
                        ]);
                    }

                    $recentBalls = array_slice($commentaryData, 0, 12);
                    $latestBall = !empty($commentaryData) ? $commentaryData[0] : null;

                    $statusText = $baseMatch['result'] ?? $baseMatch['status_note'] ?? $baseMatch['status'] ?? $baseMatch['state'] ?? 'Live';

                    // Target calculation
                    $activeInningNum = (int) ($baseMatch['live_inning'] ?? (!empty($scorecardData) ? count($scorecardData) : 1));
                    $targetVal = null;
                    $runsNeededVal = null;
                    $ballsRemVal = null;
                    $rrrVal = null;

                    $formatOvers = str_contains(strtoupper((string) ($baseMatch['format'] ?? '')), 'T20') ? 20 : (str_contains(strtoupper((string) ($baseMatch['format'] ?? '')), 'TEST') ? 90 : 50);
                    $totalMaxBalls = $formatOvers * 6;

                    $currInningOverStr = $bowler['overs'] ?? '0.0';
                    $overParts = explode('.', (string) $currInningOverStr);
                    $compOvers = (int) ($overParts[0] ?? 0);
                    $extraBalls = (int) ($overParts[1] ?? 0);
                    $ballsBowled = ($compOvers * 6) + $extraBalls;

                    if ($activeInningNum >= 2 && !empty($scorecardData['1']['score'])) {
                        preg_match('/^(\d+)/', (string) $scorecardData['1']['score'], $mFirstInn);
                        if (!empty($mFirstInn[1])) {
                            $targetVal = ((int) $mFirstInn[1]) + 1;
                        }
                    }

                    if ($targetVal) {
                        $activeScoreNum = 0;
                        if (preg_match('/^(\d+)/', (string) ($activeInningNum === 2 ? $scoreB : $scoreA), $mCurInn)) {
                            $activeScoreNum = (int) $mCurInn[1];
                        }
                        $runsNeededVal = max(0, $targetVal - $activeScoreNum);
                        $ballsRemVal = max(0, $totalMaxBalls - $ballsBowled);
                        if ($ballsRemVal > 0) {
                            $rrrVal = number_format(($runsNeededVal / $ballsRemVal) * 6, 2);
                        }
                    }

                    $matchPayload = array_merge($baseMatch, [
                        'match_id' => $matchId,
                        'id' => $matchId,
                        'team_a' => $baseMatch['team_a'] ?? 'Team A',
                        'team_b' => $baseMatch['team_b'] ?? 'Team B',
                        'team_a_short' => $baseMatch['team_a_short'] ?? CricketLiveApiService::generateTeamShort($baseMatch['team_a'] ?? 'Team A'),
                        'team_b_short' => $baseMatch['team_b_short'] ?? CricketLiveApiService::generateTeamShort($baseMatch['team_b'] ?? 'Team B'),
                        'score_a' => $scoreA,
                        'score_b' => $scoreB,
                        'score' => $combinedScore,
                        'team_a_scores' => $scoreA,
                        'team_b_scores' => $scoreB,
                        'team_a_score' => $scoreA,
                        'team_b_score' => $scoreB,
                        'status' => $statusText,
                        'statusText' => $statusText,
                        'state' => $baseMatch['state'] ?? 'Live',
                        'result' => $baseMatch['result'] ?? '',
                        'batters' => $batters,
                        'live_batters' => $batters,
                        'batsman' => $batters,
                        'bowler' => $bowler ? [$bowler] : [],
                        'live_bowler' => $bowler,
                        'recent_balls' => $recentBalls,
                        'latest_ball' => $latestBall,
                        'commentary' => $commentaryData,
                        'scorecard' => $scorecardData,
                        'facts' => $matchFacts,
                        'target' => $targetVal,
                        'run_needed' => $runsNeededVal,
                        'balls_rem' => $ballsRemVal,
                        'rrr' => $rrrVal,
                        'poll_interval_ms' => $dynamicConfig['poll_interval_ms'],
                        'dynamic_format' => $dynamicConfig['format'],
                        'in_break' => $dynamicConfig['in_break'],
                        'updated_at' => now()->toIso8601String(),
                        'timestamp' => time(),
                    ]);

                    // Invoke SessionController tick if market rates & sessions enabled
                    if (LiveEngineConfig::isMarketRatesEnabled()) {
                        try {
                            $fancyData = $baseMatch['session'] ?? $commRes['fancy'] ?? [];
                            SessionController::recordLiveSessionTick($matchId, $fancyData, $matchPayload);
                        } catch (\Throwable $sessionEx) {
                            Log::debug("Session tick error for match {$matchId}: " . $sessionEx->getMessage());
                        }
                    }

                    // Write normalized match detail to Redis
                    $cricketService->writeMatchToRedis($matchId, $matchPayload);

                    $this->line('[' . now()->toDateTimeString() . "] Synced match {$matchId} ({$baseMatch['team_a_short']} vs {$baseMatch['team_b_short']}) | Viewers: YES");
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
