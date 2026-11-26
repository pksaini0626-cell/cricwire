<?php

use App\Jobs\SyncPollVotesToDatabaseJob;
use App\Jobs\SyncSchedulesAndSeriesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Cron Tasks
|--------------------------------------------------------------------------
*/

// Sync upcoming schedules, tournaments, and squad structures every 6 hours
Schedule::job(new SyncSchedulesAndSeriesJob)->everySixHours();

// Sync official ICC rankings daily at 05:30 IST into Redis (4 categories x 3 formats = 12 calls/day)
Schedule::command('cricket:sync-rankings')->dailyAt('05:30')->timezone('Asia/Kolkata')->withoutOverlapping();

// Sync high-frequency Redis poll votes down to MySQL every 60 seconds
Schedule::job(new SyncPollVotesToDatabaseJob)->everyMinute();

// Ultra-Low-Quota Match Lifecycle Cron (IST triggers for Toss, XI, Pre-match & Live First Ball)
Schedule::command('cricket:lifecycle-tick')->everyMinute()->withoutOverlapping(2);

// Twice-Daily Static Page Sync (Schedule & Series into Redis Archive)
Schedule::call(function (\App\Services\Cricket\CricketLiveApiService $apiService) {
    $apiService->getScheduleByCategory('all');
    $apiService->getSeriesByCategory('all');
})->twiceDailyAt(6, 18)->timezone('Asia/Kolkata')->name('sync-static-cricket-archive');
