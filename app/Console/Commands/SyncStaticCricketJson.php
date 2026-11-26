<?php

namespace App\Console\Commands;

use App\Models\ManagedMatch;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Cricket\StaticJsonCacheWriter;
use App\Support\LiveEngineConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncStaticCricketJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cricket:sync-static-json {--once : Run a single iteration instead of 55s daemon loop}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync CricketLiveApi matches and ball data into public/live-data static JSON files for shared hosting';

    public function handle(CricketLiveApiService $api): int
    {
        $startTime = microtime(true);
        $runOnce = (bool) $this->option('once');

        $this->info('[' . now()->toDateTimeString() . '] Starting static cricket JSON synchronization...');

        do {
            // 1. Check if background engine is enabled
            if (!LiveEngineConfig::isEnabled()) {
                $this->warn('Live engine is disabled in LiveEngineSettings. Sleeping...');
                if ($runOnce) break;
                sleep(5);
                continue;
            }

            // 2. Check daily API call limit
            $dailyLimit = LiveEngineConfig::dailyApiCallLimit();
            $dailyCalls = $api->getDailyCallCount();
            if ($dailyCalls >= $dailyLimit) {
                $this->warn("Daily API call limit reached ({$dailyCalls}/{$dailyLimit}). Halting polling until tomorrow.");
                break;
            }

            // 3. Fetch matches from API
            $liveMatches = $api->getLiveMatches();
            $upcomingMatches = $api->getUpcomingMatches();
            $recentMatches = $api->getRecentMatches();

            // Strict sanitization: ensure no completed or live matches leak into upcoming
            $sanitized = CricketLiveApiService::sanitizeMatchBuckets($liveMatches, $upcomingMatches, $recentMatches);
            $cleanLive = $sanitized['live'];
            $cleanUpcoming = $sanitized['upcoming'];
            $cleanRecent = $sanitized['recent'];

            // Fetch list of skipped match IDs from DB (is_covered = false)
            $skippedMatchIds = ManagedMatch::where('is_covered', false)
                ->pluck('match_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();

            // Filter out skipped matches
            $coveredLive = array_values(array_filter($cleanLive, function ($m) use ($skippedMatchIds) {
                $mId = (int) ($m['match_id'] ?? 0);
                return !isset($skippedMatchIds[$mId]);
            }));

            $coveredUpcoming = array_values(array_filter($cleanUpcoming, function ($m) use ($skippedMatchIds) {
                $mId = (int) ($m['match_id'] ?? 0);
                return !isset($skippedMatchIds[$mId]);
            }));

            $coveredRecent = array_values(array_filter($cleanRecent, function ($m) use ($skippedMatchIds) {
                $mId = (int) ($m['match_id'] ?? 0);
                return !isset($skippedMatchIds[$mId]);
            }));

            // Write home.json
            $homePayload = [
                'live' => $coveredLive,
                'upcoming' => $coveredUpcoming,
                'recent' => $coveredRecent,
                'updated_at' => now()->toIso8601String(),
                'timestamp' => time(),
            ];
            StaticJsonCacheWriter::write('home.json', $homePayload);

            // Upsert any unseen matches to managed_matches catalog for admin visibility
            $this->upsertCatalogMatches($cleanLive, $cleanUpcoming, $cleanRecent);

            // If 0 covered live matches exist, exit early to save CPU & API quota on shared hosting
            if (empty($coveredLive)) {
                $this->info('No covered live matches active. Home payload written. Exiting cycle.');
                if ($runOnce) break;
                break; // cPanel cron will wake again on the next minute
            }

            // Process each covered live match format-wise
            $pollT20 = LiveEngineConfig::pollIntervalT20();
            $pollOdi = LiveEngineConfig::pollIntervalOdi();
            $pollTest = LiveEngineConfig::pollIntervalTest();

            foreach ($coveredLive as $match) {
                $matchId = (int) ($match['match_id'] ?? 0);
                if ($matchId <= 0) continue;

                $format = strtoupper($match['format'] ?? 'ODI');
                $intervalSec = match ($format) {
                    'T20' => $pollT20,
                    'TEST' => $pollTest,
                    default => $pollOdi,
                };

                $lastPolled = (int) Cache::get("last_polled:{$matchId}", 0);
                $now = time();

                if (($now - $lastPolled) >= $intervalSec) {
                    // Check quota again before detail call
                    if ($api->getDailyCallCount() >= $dailyLimit) {
                        break;
                    }

                    // Fetch commentary
                    $commentaryData = $api->getCommentary($matchId);

                    // Fetch scorecard every 2nd cycle (or if not yet cached)
                    $scorecardCycle = (int) Cache::get("scorecard_cycle:{$matchId}", 0);
                    $scorecard = null;
                    if ($scorecardCycle % 2 === 0) {
                        $scorecard = $api->getScorecard($matchId);
                        Cache::put("last_scorecard:{$matchId}", $scorecard, 300);
                    } else {
                        $scorecard = Cache::get("last_scorecard:{$matchId}", []);
                    }
                    Cache::put("scorecard_cycle:{$matchId}", $scorecardCycle + 1, 300);

                    // Highlights
                    $highlights = $api->getHighlights($matchId);

                    // Facts
                    $facts = $api->getMatchFacts($matchId);

                    // Extract normalized commentary items
                    $commList = $commentaryData['commentary'] ?? [];
                    $latestBall = !empty($commList) ? $commList[0] : null;

                    $scoreA = $match['score_a'] ?? $match['team_a_scores'] ?? '';
                    $scoreB = $match['score_b'] ?? $match['team_b_scores'] ?? '';
                    $scoreCombined = $match['score'] ?? $scoreA;

                    // Build match payload
                    $matchPayload = [
                        'match_id' => $matchId,
                        'series_name' => $match['series_name'] ?? '',
                        'match_desc' => $match['match_desc'] ?? '',
                        'format' => $format,
                        'poll_interval_seconds' => $intervalSec,
                        'poll_ms' => $intervalSec * 1000,
                        'state' => 'Live',
                        'score' => $scoreCombined,
                        'team_a' => $match['team_a'] ?? '',
                        'team_b' => $match['team_b'] ?? '',
                        'team_a_short' => $match['team_a_short'] ?? '',
                        'team_b_short' => $match['team_b_short'] ?? '',
                        'team_a_img' => $match['team_a_img'] ?? '',
                        'team_b_img' => $match['team_b_img'] ?? '',
                        'team_a_scores' => $scoreA,
                        'team_b_scores' => $scoreB,
                        'score_a' => $scoreA,
                        'score_b' => $scoreB,
                        'team_a_score' => $scoreA,
                        'team_b_score' => $scoreB,
                        'team_a_over' => $match['team_a_over'] ?? '',
                        'team_b_over' => $match['team_b_over'] ?? '',
                        'live_inning' => $match['live_inning'] ?? '',
                        'status' => $match['status'] ?? 'Live',
                        'result' => $match['result'] ?? '',
                        'latest_ball' => $latestBall,
                        'recent_balls' => $api->extractRecentBalls($commList, [], 18),
                        'commentary' => $commList,
                        'scorecard' => $scorecard['innings'] ?? [],
                        'highlights' => $highlights,
                        'facts' => $facts,
                        'updated_at' => now()->toIso8601String(),
                        'timestamp' => $now,
                    ];

                    StaticJsonCacheWriter::write("match-{$matchId}.json", $matchPayload);
                    Cache::put("last_polled:{$matchId}", $now, 3600);

                    $this->line("Synced match-{$matchId}.json ({$format}, every {$intervalSec}s)");
                }
            }

            if ($runOnce) {
                break;
            }

            // Loop every 5 seconds up to 55 seconds total to stay within 1-minute cron
            sleep(5);
        } while ((microtime(true) - $startTime) < 55);

        $this->info('[' . now()->toDateTimeString() . '] Sync static cricket JSON finished.');
        return self::SUCCESS;
    }

    /**
     * Upsert matches silently into managed_matches table preserving admin choices.
     */
    protected function upsertCatalogMatches(array $live, array $upcoming, array $recent): void
    {
        $all = array_merge($live, $upcoming, $recent);
        foreach ($all as $m) {
            $mId = (int) ($m['match_id'] ?? 0);
            if ($mId <= 0) continue;

            $existing = ManagedMatch::where('match_id', $mId)->first();
            if (!$existing) {
                ManagedMatch::create([
                    'match_id' => $mId,
                    'series_name' => $m['series_name'] ?? null,
                    'match_desc' => $m['match_desc'] ?? null,
                    'format' => $m['format'] ?? 'ODI',
                    'team_a' => $m['team_a'] ?? null,
                    'team_b' => $m['team_b'] ?? null,
                    'state' => $m['state'] ?? 'Scheduled',
                    'start_time' => $m['start_time'] ?? null,
                    'is_covered' => true,
                    'is_featured' => false,
                ]);
            } else {
                // Update basic state info without touching is_covered
                $existing->update([
                    'series_name' => $m['series_name'] ?? $existing->series_name,
                    'match_desc' => $m['match_desc'] ?? $existing->match_desc,
                    'state' => $m['state'] ?? $existing->state,
                ]);
            }
        }
    }
}
