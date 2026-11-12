<?php

namespace App\Console\Commands;

use App\Events\LiveScoreUpdated;
use App\Http\Controllers\Cricket\SessionController;
use App\Models\CricketMatch;
use App\Services\AudioEventNormalizer;
use App\Services\Cricket\RapidApiCricketService;
use App\Services\RapidApiAdapter;
use App\Support\LiveEngineConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

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
    protected $description = 'Supervisor daemon poller for cricket live scores with adaptive backoff';

    /**
     * Execute the console command.
     */
    public function handle(RapidApiAdapter $api, RapidApiCricketService $cricketService): void
    {
        $this->info('Starting cricket live match poller daemon...');
        $tier = $this->option('tier') ?: 'standard';

        while (true) {
            // Check if poller is enabled via dynamic settings
            if (!LiveEngineConfig::isEnabled()) {
                $this->line('[' . now()->toDateTimeString() . '] Live engine disabled via Admin Settings. Sleeping for 10 seconds...');
                sleep(10);
                continue;
            }

            // Check if any matches are currently marked live or starting right now
            $hasLiveFixtures = CricketMatch::where('status', 'live')
                ->orWhere(function ($q) {
                    $q->where('status', 'upcoming')
                      ->where('start_time', '<=', now());
                })
                ->exists();

            if (!$hasLiveFixtures) {
                $this->line('[' . now()->toDateTimeString() . '] Zero live fixtures detected. Entering adaptive backoff sleep (15 minutes)...');
                sleep(900); // 15 minutes sleep
                continue;
            }

            // Fetch live matches payload
            $liveData = $api->getLiveMatches();
            $liveMatchesList = [];

            if ($liveData && isset($liveData['typeMatches'])) {
                foreach ($liveData['typeMatches'] as $typeGroup) {
                    $seriesMatches = $typeGroup['seriesMatches'] ?? [];
                    foreach ($seriesMatches as $sMatch) {
                        $wrapper = $sMatch['seriesAdWrapper'] ?? null;
                        if (!$wrapper || empty($wrapper['matches'])) {
                            continue;
                        }

                        foreach ($wrapper['matches'] as $m) {
                            $matchInfo = $m['matchInfo'] ?? [];
                            $matchScore = $m['matchScore'] ?? [];
                            $apiMatchId = (string) ($matchInfo['matchId'] ?? '');

                            if (!$apiMatchId) {
                                continue;
                            }

                            $team1Score = $matchScore['team1Score']['inngs1'] ?? [];
                            $team2Score = $matchScore['team2Score']['inngs1'] ?? [];

                            $teamAScore = isset($team1Score['runs']) ? "{$team1Score['runs']}/{$team1Score['wickets']} ({$team1Score['overs']})" : '';
                            $teamBScore = isset($team2Score['runs']) ? "{$team2Score['runs']}/{$team2Score['wickets']} ({$team2Score['overs']})" : '';

                            $lastBallEvent = AudioEventNormalizer::normalize($matchInfo['status'] ?? '');

                            $livePayload = [
                                'match_id' => $apiMatchId,
                                'team_a_score' => $teamAScore,
                                'team_b_score' => $teamBScore,
                                'status' => $matchInfo['state'] ?? 'In Progress',
                                'last_ball_event' => $lastBallEvent,
                                'raw_status' => $matchInfo['status'] ?? '',
                                'timestamp' => now()->timestamp,
                            ];

                            $liveMatchesList[] = $livePayload;

                            // Cache match state in Redis: cricket:match:{id}:live
                            try {
                                Redis::set("cricket:match:{$apiMatchId}:live", json_encode($livePayload));
                            } catch (\Throwable $e) {
                                // Redis fallback handling
                            }

                            // Fetch detailed live line / mini scorecard if needed
                            $commData = $api->getMatchLiveLine($apiMatchId);
                            if ($commData) {
                                $livePayload['comm'] = $commData;
                            }

                            // Refresh /fancy and record Lowest & Highest Session Rates
                            try {
                                $fancy = $cricketService->getMatchFancy($apiMatchId);
                                if (!empty($fancy)) {
                                    SessionController::updateMinMaxFromFancy($apiMatchId, $fancy, [
                                        'fav_team' => $matchInfo['state'] ?? null,
                                    ]);
                                }
                            } catch (\Throwable $e) {
                                // Gracefully continue if fancy poll fails
                            }

                            // Update Database record if exists
                            $dbMatch = CricketMatch::where('api_match_id', $apiMatchId)->first();
                            if ($dbMatch) {
                                $dbMatch->update([
                                    'status' => 'live',
                                    'result_summary' => $matchInfo['status'] ?? null,
                                ]);
                            }

                            // Broadcast event
                            broadcast(new LiveScoreUpdated($apiMatchId, $livePayload))->toOthers();
                            $this->info("Broadcasted update for Match ID {$apiMatchId} ({$lastBallEvent})");
                        }
                    }
                }
            }

            // Home ticker cache: cricket:live_matches:list with 24-hour TTL
            if (!empty($liveMatchesList)) {
                try {
                    Redis::setex('cricket:live_matches:list', 86400, json_encode($liveMatchesList));
                } catch (\Throwable $e) {
                    // Redis fallback handling
                }
            }

            // Dynamically configured sleep interval from Admin Panel
            $dynamicDelay = ($tier === 'low-latency') ? 1 : LiveEngineConfig::pollingInterval();
            sleep($dynamicDelay);
        }
    }
}
