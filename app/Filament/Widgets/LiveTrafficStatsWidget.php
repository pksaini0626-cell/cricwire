<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class LiveTrafficStatsWidget extends BaseWidget
{
    protected static ?int $sort = -1;

    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $now = now()->timestamp;
        $cutoff = $now - 180; // Last 3 minutes
        $today = now()->format('Y-m-d');

        $onlineVisitors = Cache::get('site:online_visitors', []);
        $onlineCount = 0;
        if (is_array($onlineVisitors)) {
            foreach ($onlineVisitors as $timestamp) {
                if (is_int($timestamp) && $timestamp >= $cutoff) {
                    $onlineCount++;
                }
            }
        }

        $dailyVisitors = Cache::get('site:daily_visitors:' . $today, []);
        $uniqueVisitorsToday = is_array($dailyVisitors) ? count($dailyVisitors) : 0;

        $pageViewsToday = (int) Cache::get('site:daily_pageviews:' . $today, 0);

        $totalUsers = User::count();
        $activeLoggedInToday = User::whereDate('last_login_at', today())->count();

        $liveBadge = new HtmlString(
            '<span class="inline-flex items-center gap-1.5 font-medium">'
            . '<span style="position:relative;display:inline-flex;width:8px;height:8px;">'
            . '<span class="animate-ping" style="position:absolute;inset:0;border-radius:9999px;background-color:#34d399;opacity:0.75;"></span>'
            . '<span style="position:relative;display:inline-flex;width:8px;height:8px;border-radius:9999px;background-color:#10b981;"></span>'
            . '</span>'
            . 'Live on site'
            . '</span>'
        );

        return [
            Stat::make('Current Users (Online Now)', number_format($onlineCount))
                ->description($liveBadge)
                ->descriptionColor('success')
                ->icon('heroicon-m-signal')
                ->color('success'),

            Stat::make("Today's Unique Visitors", number_format($uniqueVisitorsToday))
                ->description(now()->format('d M Y') . ' unique visitors')
                ->descriptionIcon('heroicon-m-user-group')
                ->icon('heroicon-m-users')
                ->color('info'),

            Stat::make("Today's Page Views", number_format($pageViewsToday))
                ->description('Total page views today')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->icon('heroicon-m-eye')
                ->color('warning'),

            Stat::make('Total / Today Logged-in Users', number_format($totalUsers))
                ->description(number_format($activeLoggedInToday) . ' active logged-in today')
                ->descriptionIcon('heroicon-m-arrow-right-end-on-rectangle')
                ->icon('heroicon-m-user-circle')
                ->color('primary'),
        ];
    }
}
