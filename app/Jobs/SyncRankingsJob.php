<?php

namespace App\Jobs;

use App\Services\Cricket\CricketLiveApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncRankingsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(CricketLiveApiService $apiService): void
    {
        Log::info('SyncRankingsJob: Syncing ICC rankings into Redis...');

        $result = $apiService->syncIccRankingsToRedis();

        Log::info('SyncRankingsJob: ICC rankings Redis sync completed.', [
            'synced_count' => count($result['synced'] ?? []),
            'failed_count' => count($result['failed'] ?? []),
            'quota_halted' => (bool) ($result['quota_halted'] ?? false),
        ]);
    }
}
