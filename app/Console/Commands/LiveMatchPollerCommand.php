<?php

namespace App\Console\Commands;

use App\Events\LiveScoreUpdated;
use App\Http\Controllers\Cricket\SessionController;
use App\Models\CricketMatch;
use App\Services\AudioEventNormalizer;
use App\Services\Cricket\RapidApiCricketService;
use App\Services\Cricket\ReverbPresenceService;
use App\Services\RapidApiAdapter;
use App\Support\LiveEngineConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LiveMatchPollerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cricket:poll-live {--tier=standard : standard or low-latency}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Supervisor daemon poller for cricket live scores with subscriber-aware polling and adaptive backoff';

    /**
     * Execute the console command.
     */
    public function handle(
        RapidApiAdapter $api,
        RapidApiCricketService $cricketService,
        ReverbPresenceService $presenceService
    ): void {
        $this->info('Starting cost-optimized cricket live match poller daemon...');
        $tier = $this->option('tier') ?: 'standard';

        while (true) {
            // 1. Check Admin Master Toggle
            if (!LiveEngineConfig::isEnabled()) {
                $this->line('[' . now()->toDateTimeString() . '] Live engine disabled via Admin Settings. Sleeping for 15 seconds...');
                sleep(15);
                continue;
            }

            // 2. Check Live Matches List
            // Fetch /home or /liveMatches (cached for 30–60s when idle, 15s when matches are live)
            $liveMatches = $this->fetchLiveMatchesList($cricketService, $api);

            // 3. State A — 0 Live Matches (Sleep Mode: 300 seconds / 5 minutes)
            if (empty($liveMatches)) {
                $this->warn('[' . now()->toDateTimeString() . '] No live matches active. Entering 5-minute sleep mode to save RapidAPI quota.');
                Log::info('LiveMatchPollerCommand: No live matches active. Entering 5-minute sleep mode to save RapidAPI quota.');
                sleep(300);
                continue;
            }

            // Map live match IDs
            $liveMatchMap = [];
            foreach ($liveMatches as $m) {
                $id = (string) ($m['match_id'] ?? $m['id'] ?? $m['matchInfo']['matchId'] ?? '');
                if ($id !== '') {
                    $liveMatchMap[$id] = $m;
                }
            }
            $allLiveMatchIds = array_keys($liveMatchMap);

            // Detect actively watched matches via Reverb internal HTTP API + Heartbeat Redis cache
            $watchedMatchIds = $presenceService->getActiveWatchedMatchIds($allLiveMatchIds);

            // 4. State B — Live Matches Exist, But 0 Active Subscribers
            if (empty($watchedMatchIds)) {
                $this->line(sprintf(
                    '[%s] %d live match(es) active, but 0 active subscribers. Skipping deep polling to save quota.',
                    now()->toDateTimeString(),
                    count($liveMatchMap)
                ));

                // Do NOT call /live, /fancy, /scorecard, /commentary, or /overHistory for any match!
                // Only broadcast lightweight summary if present and sleep for 30-45 seconds
                foreach ($liveMatchMap as $matchId => $matchData) {
                    try {
                        broadcast(new LiveScoreUpdated((string) $matchId, $matchData))->toOthers();
                    } catch (\Throwable $e) {
                        // Ignore broadcast delivery hiccups
                    }
                }

                sleep(35);
                continue;
            }

            // 5. State C — Live Matches With Active Subscribers
            $this->info(sprintf(
                '[%s] Polling %d actively watched match(es): [%s]',
                now()->toDateTimeString(),
                count($watchedMatchIds),
                implode(', ', $watchedMatchIds)
            ));

            $marketRatesEnabled = LiveEngineConfig::isMarketRatesEnabled();

            foreach ($watchedMatchIds as $matchId) {
                $matchIdStr = (string) $matchId;
                $matchMeta = $liveMatchMap[$matchIdStr] ?? [];

                try {
                    // Fetch /live for watched match
                    $detailedLive = $cricketService->getMatchLiveLine($matchIdStr, true);
                    if (!is_array($detailedLive)) {
                        $detailedLive = [];
                    }

                    // Build unified real-time payload
                    $teamAScore = $detailedLive['team_a_scores'] ?? $matchMeta['team_a_scores'] ?? '';
                    $teamAOver = $detailedLive['team_a_over'] ?? $matchMeta['team_a_over'] ?? '';
                    $teamBScore = $detailedLive['team_b_scores'] ?? $matchMeta['team_b_scores'] ?? '';
                    $teamBOver = $detailedLive['team_b_over'] ?? $matchMeta['team_b_over'] ?? '';

                    $rawStatus = $detailedLive['match_status'] ?? $matchMeta['match_status'] ?? 'Live';
                    $lastBallEvent = AudioEventNormalizer::normalize(
                        $detailedLive['last_ball_event'] ?? $detailedLive['status'] ?? $matchMeta['status'] ?? ''
                    );

                    $livePayload = array_merge($matchMeta, $detailedLive, [
                        'match_id' => $matchIdStr,
                        'team_a_scores' => $teamAScore,
                        'team_a_score' => $teamAScore ? ($teamAOver ? "{$teamAScore} ({$teamAOver})" : $teamAScore) : '',
                        'team_a_over' => $teamAOver,
                        'team_b_scores' => $teamBScore,
                        'team_b_score' => $teamBScore ? ($teamBOver ? "{$teamBScore} ({$teamBOver})" : $teamBScore) : '',
                        'team_b_over' => $teamBOver,
                        'status' => $detailedLive['status'] ?? 'In Progress',
                        'status_badge' => $detailedLive['status_badge'] ?? 'In Progress',
                        'last_ball_event' => $lastBallEvent,
                        'ball_event' => $lastBallEvent,
                        'raw_status' => $rawStatus,
                        'result' => $detailedLive['result'] ?? $matchMeta['result'] ?? '',
                        'need_run_ball' => $detailedLive['need_run_ball'] ?? '',
                        'batsman' => $detailedLive['batsman'] ?? [],
                        'bolwer' => $detailedLive['bolwer'] ?? $detailedLive['bowler'] ?? [],
                        'fav_team' => $detailedLive['fav_team'] ?? null,
                        'min_rate' => $detailedLive['min_rate'] ?? '-',
                        'max_rate' => $detailedLive['max_rate'] ?? '-',
                        's_ovr' => $detailedLive['s_ovr'] ?? null,
                        's_min' => $detailedLive['s_min'] ?? '-',
                        's_max' => $detailedLive['s_max'] ?? '-',
                        'last_wicket' => $detailedLive['last_wicket'] ?? null,
                        'timestamp' => now()->timestamp,
                    ]);

                    // Store real-time live match state in Redis/Cache
                    Cache::put("cricket:match:{$matchIdStr}:live", $livePayload, now()->addMinutes(30));
                    Cache::put("cricket:match:{$matchIdStr}:live_line", $livePayload, now()->addMinutes(30));

                    // Fetch /fancy if session and odds are enabled in Admin
                    if ($marketRatesEnabled) {
                        try {
                            $fancy = $cricketService->getMatchFancy($matchIdStr, true);
                            if (!empty($fancy)) {
                                Cache::put("cricket:match:{$matchIdStr}:fancy", $fancy, now()->addMinutes(30));
                                SessionController::updateMinMaxFromFancy($matchIdStr, $fancy, [
                                    'fav_team' => $livePayload['fav_team'] ?? null,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            Log::warning("LiveMatchPollerCommand: Fancy poll failed for match {$matchIdStr}: " . $e->getMessage());
                        }
                    }

                    // Update Database record if exists
                    try {
                        $dbMatch = CricketMatch::where('api_match_id', $matchIdStr)->first();
                        if ($dbMatch) {
                            $dbMatch->update([
                                'status' => 'live',
                                'result_summary' => $livePayload['result'] ?? $livePayload['raw_status'] ?? null,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        // Ignore DB errors
                    }

                    // Broadcast LiveScoreUpdated event to match.{matchId} and matches.live.ticker
                    try {
                        broadcast(new LiveScoreUpdated($matchIdStr, $livePayload))->toOthers();
                        $this->line("  -> Broadcasted update for watched Match ID {$matchIdStr} ({$lastBallEvent})");
                    } catch (\Throwable $e) {
                        Log::warning("LiveMatchPollerCommand: Broadcast failed for match {$matchIdStr}: " . $e->getMessage());
                    }
                } catch (\Throwable $e) {
                    Log::error("LiveMatchPollerCommand: Error polling watched match {$matchIdStr}: " . $e->getMessage());
                }
            }

            // Dynamically configured sleep interval from Admin Panel
            $dynamicDelay = ($tier === 'low-latency') ? 1 : LiveEngineConfig::pollingInterval();
            sleep($dynamicDelay);
        }
    }

    /**
     * Retrieve live matches list from cache or RapidApiCricketService.
     *
     * @param RapidApiCricketService $cricketService
     * @param RapidApiAdapter $api
     * @return array
     */
    protected function fetchLiveMatchesList(RapidApiCricketService $cricketService, RapidApiAdapter $api): array
    {
        $liveMatches = [];

        // Check if cached recently
        if (Cache::has('cricket:live_matches')) {
            $cached = Cache::get('cricket:live_matches');
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        // Try RapidApiCricketService getLiveMatches() or getHomeMatches()
        try {
            $rawLive = $cricketService->getLiveMatches(true);
            if (!empty($rawLive) && is_array($rawLive)) {
                foreach ($rawLive as $m) {
                    if (!is_array($m)) continue;
                    $status = strtolower(trim((string) ($m['match_status'] ?? $m['status'] ?? '')));
                    if ($status === 'live' || $status === 'in progress' || $status === 'innings break' || $status === '1' || $status === '2') {
                        $liveMatches[] = $m;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        // Fallback to getHomeMatches() if dedicated liveMatches returned empty
        if (empty($liveMatches)) {
            try {
                $homeMatches = $cricketService->getHomeMatches(true);
                if (is_array($homeMatches)) {
                    foreach ($homeMatches as $m) {
                        if (!is_array($m)) continue;
                        $status = strtolower(trim((string) ($m['match_status'] ?? $m['status'] ?? '')));
                        if ($status === 'live' || $status === 'in progress' || $status === 'innings break' || $status === '1' || $status === '2') {
                            $liveMatches[] = $m;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // Fallback to RapidApiAdapter if RapidApiCricketService returned empty
        if (empty($liveMatches)) {
            try {
                $liveData = $api->getLiveMatches();
                if ($liveData && isset($liveData['typeMatches'])) {
                    foreach ($liveData['typeMatches'] as $typeGroup) {
                        $seriesMatches = $typeGroup['seriesMatches'] ?? [];
                        foreach ($seriesMatches as $sMatch) {
                            $wrapper = $sMatch['seriesAdWrapper'] ?? null;
                            if (!$wrapper || empty($wrapper['matches'])) continue;
                            foreach ($wrapper['matches'] as $m) {
                                $matchInfo = $m['matchInfo'] ?? [];
                                $matchScore = $m['matchScore'] ?? [];
                                $apiMatchId = (string) ($matchInfo['matchId'] ?? '');
                                if (!$apiMatchId) continue;

                                $team1Score = $matchScore['team1Score']['inngs1'] ?? [];
                                $team2Score = $matchScore['team2Score']['inngs1'] ?? [];

                                $teamAScore = isset($team1Score['runs']) ? "{$team1Score['runs']}/{$team1Score['wickets']}" : '';
                                $teamAOver = isset($team1Score['overs']) ? (string) $team1Score['overs'] : '';
                                $teamBScore = isset($team2Score['runs']) ? "{$team2Score['runs']}/{$team2Score['wickets']}" : '';
                                $teamBOver = isset($team2Score['overs']) ? (string) $team2Score['overs'] : '';

                                $liveMatches[] = [
                                    'match_id' => $apiMatchId,
                                    'match_status' => 'live',
                                    'status' => $matchInfo['state'] ?? 'In Progress',
                                    'team_a_scores' => $teamAScore,
                                    'team_a_over' => $teamAOver,
                                    'team_b_scores' => $teamBScore,
                                    'team_b_over' => $teamBOver,
                                    'raw_status' => $matchInfo['status'] ?? '',
                                ];
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        $ttl = !empty($liveMatches) ? 15 : 60;
        Cache::put('cricket:live_matches', $liveMatches, $ttl);
        Cache::put('cricket:live_matches:list', $liveMatches, $ttl);

        return $liveMatches;
    }
}
