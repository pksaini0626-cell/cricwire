<?php

namespace App\Console\Commands;

use App\Services\Cricket\CricketLiveApiService;
use Illuminate\Console\Command;

class SyncIccRankingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cricket:sync-rankings
                            {--category= : Optional single category (batting, bowling, allrounder, teams)}
                            {--format= : Optional single format (t20, odi, test)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily sync of ICC Cricket Rankings (4 categories x 3 formats) into Redis cache';

    /**
     * Execute the console command.
     */
    public function handle(CricketLiveApiService $apiService): int
    {
        $category = $this->option('category');
        $format = $this->option('format');

        if ($category !== null && !in_array(strtolower($category), CricketLiveApiService::ICC_RANKING_CATEGORIES, true)) {
            $this->error("Invalid category '{$category}'. Valid: " . implode(', ', CricketLiveApiService::ICC_RANKING_CATEGORIES));
            return self::INVALID;
        }

        if ($format !== null && !in_array(strtolower($format), CricketLiveApiService::ICC_RANKING_FORMATS, true)) {
            $this->error("Invalid format '{$format}'. Valid: " . implode(', ', CricketLiveApiService::ICC_RANKING_FORMATS));
            return self::INVALID;
        }

        $this->info('[' . now('Asia/Kolkata')->toDateTimeString() . ' IST] Syncing ICC Cricket Rankings into Redis...');

        $result = $apiService->syncIccRankingsToRedis($category, $format);
        $synced = $result['synced'] ?? [];
        $failed = $result['failed'] ?? [];

        foreach ($synced as $key => $count) {
            $this->line("  [OK] {$key} -> {$count} entries cached");
        }

        foreach ($failed as $key) {
            $this->warn("  [SKIP/FAIL] {$key} (preserved existing Redis cache if present)");
        }

        if (!empty($result['quota_halted'])) {
            $this->warn('Daily API quota hard-stop reached. Remaining combinations skipped.');
        }

        $this->info('Done. Synced ' . count($synced) . ' table(s), failed/skipped ' . count($failed) . '.');

        return empty($synced) && !empty($failed) ? self::FAILURE : self::SUCCESS;
    }
}
