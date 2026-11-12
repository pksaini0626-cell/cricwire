<?php

use App\Jobs\SyncPollVotesToDatabaseJob;
use App\Jobs\SyncRankingsJob;
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

// Sync official ICC rankings daily at 03:00 UTC
Schedule::job(new SyncRankingsJob)->dailyAt('03:00');

// Sync high-frequency Redis poll votes down to MySQL every 60 seconds
Schedule::job(new SyncPollVotesToDatabaseJob)->everyMinute();
