<?php

namespace App\Console\Commands;

use App\Models\ManagedMatch;
use App\Models\SkippedSeries;
use App\Models\SquadBattle;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\SquadBattleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MatchLifecycleCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cricket:lifecycle-tick';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executes exact IST timed triggers for Toss, Playing XI, Pre-match Archiving, and Live First Ball';

    /**
     * Execute the console command.
     */
    public function handle(CricketLiveApiService $apiService): int
    {
        $nowIst = Carbon::now('Asia/Kolkata');
        $this->info("[{$nowIst->toDateTimeString()} IST] Running Cricket Match Lifecycle Tick...");

        // 1. Pre-Match Data Archiving for Upcoming Matches (next 24 hours)
        $upcomingCandidates = ManagedMatch::where('is_covered', true)
            ->where('pre_match_archived', false)
            ->whereNotNull('start_time_ist')
            ->where('start_time_ist', '>=', $nowIst->copy()->subHours(2))
            ->where('start_time_ist', '<=', $nowIst->copy()->addHours(24))
            ->get();

        foreach ($upcomingCandidates as $match) {
            if ($apiService->isSeriesSkipped($match->series_name, $match->series_id)) {
                continue;
            }
            try {
                $apiService->archivePreMatchData($match);
                $this->line("Archived pre-match data for Match #{$match->match_id}");
            } catch (\Throwable $e) {
                Log::error("Error archiving pre-match for match {$match->match_id}: " . $e->getMessage());
            }
        }

        // 2. Fetch matches needing Toss, Playing XI, or Live transitions
        $activeMatches = ManagedMatch::where('is_covered', true)
            ->whereNotNull('start_time_ist')
            ->where('lifecycle_stage', '!=', 'completed')
            ->where('start_time_ist', '>=', $nowIst->copy()->subHours(12))
            ->where('start_time_ist', '<=', $nowIst->copy()->addHours(6))
            ->get();

        foreach ($activeMatches as $match) {
            if ($apiService->isSeriesSkipped($match->series_name, $match->series_id)) {
                continue;
            }
            $matchStartIst = Carbon::parse($match->start_time_ist)->timezone('Asia/Kolkata');
            $minutesToStart = $nowIst->diffInMinutes($matchStartIst, false); // >0 before start, <0 after start

            // Stage A: Toss Fetch from T - 29 mins down to T + 45 mins (handles rain/wet outfield delays)
            if ($minutesToStart <= 29 && $minutesToStart >= -45 && !$match->toss_fetched) {
                $shouldCheckToss = true;
                if ($minutesToStart < 15) {
                    $shouldCheckToss = Cache::add("toss_check_cooldown:{$match->match_id}", 1, 90);
                }

                if ($shouldCheckToss) {
                    $this->handleTossFetch($match, $apiService, $nowIst);
                }
            }

            // Stage B: Playing XI Fetch (5 minutes after toss, or at T - 24 mins)
            $tossAgeOk = $match->toss_fetched && $match->toss_fetched_at && $nowIst->gte(Carbon::parse($match->toss_fetched_at)->timezone('Asia/Kolkata')->addMinutes(5));
            if (($tossAgeOk || ($minutesToStart <= 24 && $match->toss_fetched)) && !$match->playing_xi_fetched) {
                if (Cache::add("xi_check_cooldown:{$match->match_id}", 1, 90)) {
                    $this->handlePlayingXIFetch($match, $apiService);
                }
            }

            // Stage C: Non-blocking First-Ball Trigger at start minute
            if ($nowIst->format('Y-m-d H:i') === $matchStartIst->format('Y-m-d H:i') && $match->lifecycle_stage !== 'live') {
                $match->update(['lifecycle_stage' => 'live']);
                $this->info("Marked Match #{$match->match_id} as LIVE. Triggering non-blocking first live fetch...");

                try {
                    $delaySeconds = max(0, 20 - $nowIst->second);
                    if ($delaySeconds > 0 && config('queue.default') !== 'sync') {
                        dispatch(function () use ($apiService, $match) {
                            $livePayload = $apiService->getFastMatchData($match->match_id, false);
                            $apiService->writeMatchToRedis($match->match_id, $livePayload);
                        })->delay(now()->addSeconds($delaySeconds));
                    } else {
                        $livePayload = $apiService->getFastMatchData($match->match_id, false);
                        $apiService->writeMatchToRedis($match->match_id, $livePayload);
                    }
                } catch (\Throwable $e) {
                    Log::error("Failed initial first-ball fetch for {$match->match_id}: " . $e->getMessage());
                }
            }

            // If match is live, check throttling
            if ($match->lifecycle_stage === 'live') {
                $this->handleLiveThrottling($match, $apiService);
            }
        }

        // 3. Squad Battles: Expire incomplete drafts past lock deadline & sync live/final runs (0 API calls)
        try {
            $battleService = app(SquadBattleService::class);
            $activeBattles = SquadBattle::whereIn('status', [
                SquadBattle::STATUS_PENDING_ACCEPT,
                SquadBattle::STATUS_DRAFTING,
                SquadBattle::STATUS_LOCKED_LIVE,
            ])->get();

            foreach ($activeBattles as $battle) {
                $battleService->syncLiveAndFinalScores($battle);
            }
        } catch (\Throwable $e) {
            Log::error('MatchLifecycleCron: SquadBattle sync error: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }

    /**
     * Stage A: Fetch toss decision cleanly via single call.
     */
    protected function handleTossFetch(ManagedMatch $match, CricketLiveApiService $apiService, Carbon $nowIst): void
    {
        $matchId = (string) $match->match_id;
        $facts = $apiService->getMatchFacts($matchId, ['start_time_ist' => $match->start_time_ist]);
        $toss = trim((string) ($facts['toss'] ?? ''));

        if (CricketLiveApiService::isDecidedToss($toss)) {
            $matchStartIst = Carbon::parse($match->start_time_ist)->timezone('Asia/Kolkata');
            $startText = "Match starts at " . $matchStartIst->format('h:i A') . " IST";

            $match->update([
                'toss_text' => $toss,
                'toss_fetched' => true,
                'toss_fetched_at' => $nowIst,
                'lifecycle_stage' => 'toss_done',
            ]);

            // Merge into pre-match cache file
            $cacheFilePath = "match-cache/pre-match-{$matchId}.json";
            if (Storage::disk('local')->exists($cacheFilePath)) {
                try {
                    $data = json_decode(Storage::disk('local')->get($cacheFilePath), true) ?: [];
                    $data['toss'] = $toss;
                    $data['toss_banner'] = "🪙 {$toss} • ⏰ {$startText}";
                    $data['start_time_text'] = $startText;
                    Storage::disk('local')->put($cacheFilePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                } catch (\Throwable $e) {}
            }

            $this->info("Toss fetched for Match #{$matchId}: {$toss}");
        } elseif (CricketLiveApiService::isDelayToss($toss)) {
            $match->update([
                'toss_text' => $toss,
            ]);
            $this->info("Toss delayed for Match #{$matchId}: {$toss}");
        }
    }

    /**
     * Stage B: Fetch Playing XI cleanly and save JSON.
     */
    protected function handlePlayingXIFetch(ManagedMatch $match, CricketLiveApiService $apiService): void
    {
        $matchId = (string) $match->match_id;
        $squads = $apiService->getSquads($matchId);

        $teamAPlaying = $squads['team_a']['playing_xi'] ?? [];
        $teamBPlaying = $squads['team_b']['playing_xi'] ?? [];

        if (!empty($teamAPlaying) && !empty($teamBPlaying)) {
            $xiFilePath = "match-cache/playing-xi-{$matchId}.json";
            $xiPayload = [
                'match_id' => $matchId,
                'team_a' => [
                    'name' => $squads['team_a']['name'] ?? 'Team A',
                    'playing_xi' => $teamAPlaying,
                ],
                'team_b' => [
                    'name' => $squads['team_b']['name'] ?? 'Team B',
                    'playing_xi' => $teamBPlaying,
                ],
                'updated_at' => now()->toIso8601String(),
            ];

            try {
                Storage::disk('local')->put($xiFilePath, json_encode($xiPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                Cache::put("match:xi:{$matchId}", $xiPayload, 86400);

                $match->update([
                    'playing_xi_fetched' => true,
                    'lifecycle_stage' => 'xi_ready',
                ]);

                try {
                    app(SquadBattleService::class)->syncPlayingXiForMatch($matchId);
                } catch (\Throwable $battleEx) {
                    Log::warning("Failed syncing battle Playing XI for match {$matchId}: " . $battleEx->getMessage());
                }

                $this->info("Playing XI saved for Match #{$matchId}");
            } catch (\Throwable $e) {
                Log::error("Failed saving Playing XI for match {$matchId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Stage C: Throttled polling for ongoing live matches based on active visitors.
     */
    protected function handleLiveThrottling(ManagedMatch $match, CricketLiveApiService $apiService): void
    {
        $matchId = (string) $match->match_id;
        $hasActiveVisitors = Cache::has('active_site_visitor');
        $isWatched = Cache::has("watching:match:{$matchId}");
        $isEco = $apiService->isEcoModeActive();

        if ($hasActiveVisitors || $isWatched) {
            // Active user watching: allow regular quick refresh (doubled under Eco mode)
            $activeTtl = $isEco ? 60 : 30;
            Cache::put("cricket:poll_rate:match:{$matchId}", 'active', $activeTtl);
        } else {
            // 0 active users: throttle to 120s (doubled to 240s under Eco mode)
            $throttledTtl = $isEco ? 240 : 120;
            Cache::put("cricket:poll_rate:match:{$matchId}", 'throttled', $throttledTtl);
        }
    }
}
