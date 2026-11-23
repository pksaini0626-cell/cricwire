<?php

namespace App\Filament\Widgets;

use App\Models\CompletedMatchScorecard;
use App\Models\ManagedMatch;
use App\Services\Cricket\CricketLiveApiService;
use App\Support\LiveEngineConfig;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ApiQuotaOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $apiService = app(CricketLiveApiService::class);
        $used = $apiService->getDailyCallCount();
        $limit = LiveEngineConfig::dailyApiCallLimit();
        $percent = $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : 0;

        // Stat 1: Today's API Quota (IST)
        $quotaColor = 'success';
        $quotaDesc = "{$percent}% utilized";
        if ($apiService->isHardStopActive()) {
            $quotaColor = 'danger';
            $quotaDesc = "HARD STOP ACTIVE (>=96%)";
        } elseif ($apiService->isEcoModeActive()) {
            $quotaColor = 'danger';
            $quotaDesc = "ECO MODE ACTIVE (>=80%)";
        } elseif ($percent >= 60) {
            $quotaColor = 'warning';
            $quotaDesc = "Moderate usage";
        }

        $quotaStat = Stat::make("Today's API Quota (IST)", "{$used} / {$limit}")
            ->description($quotaDesc)
            ->descriptionIcon($quotaColor === 'danger' ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-bolt')
            ->color($quotaColor);

        // Stat 2: Site Traffic State
        $hasActiveVisitors = Cache::has('active_site_visitor');
        $trafficTitle = $hasActiveVisitors ? 'Active Visitors Present' : 'Idle Mode (1-Hr Gate • 24 calls/day)';
        $trafficColor = $hasActiveVisitors ? 'success' : 'gray';
        $trafficDesc = $hasActiveVisitors ? 'Live polling active' : 'API paused / low background frequency';

        $trafficStat = Stat::make('Site Traffic State', $trafficTitle)
            ->description($trafficDesc)
            ->descriptionIcon($hasActiveVisitors ? 'heroicon-m-user-group' : 'heroicon-m-moon')
            ->color($trafficColor);

        // Stat 3: Archived Scorecards
        $archivedCount = CompletedMatchScorecard::count();
        $archivedStat = Stat::make('Archived Scorecards', (string) $archivedCount)
            ->description('0 Repeat API Calls')
            ->descriptionIcon('heroicon-m-archive-box')
            ->color('success');

        // Stat 4: Today's Managed Matches
        $todayIst = Carbon::today('Asia/Kolkata');
        $managedCount = ManagedMatch::where('is_covered', true)
            ->where('series_skipped', false)
            ->whereDate('start_time_ist', $todayIst)
            ->count();

        $managedStat = Stat::make("Today's Managed Matches", (string) $managedCount)
            ->description($todayIst->format('d M Y') . ' (IST)')
            ->descriptionIcon('heroicon-m-trophy')
            ->color('primary');

        return [
            $quotaStat,
            $trafficStat,
            $archivedStat,
            $managedStat,
        ];
    }
}
