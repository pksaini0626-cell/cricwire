<?php

namespace App\Jobs;

use App\Services\PollService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPollVotesToDatabaseJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(PollService $pollService): void
    {
        $pollService->syncVotesToDatabase();
    }
}

