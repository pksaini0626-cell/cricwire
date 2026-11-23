<?php

namespace App\Livewire\Pages;

use App\Models\AdBanner;
use App\Services\Cricket\CricketLiveApiService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SeriesListPage extends Component
{
    public string $filter = 'all'; // Options: 'all', 'live', 'upcoming'
    public string $search = '';

    public function setFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'live', 'upcoming'], true)) {
            $this->filter = $filter;
        }
    }

    public function render(CricketLiveApiService $service)
    {
        $allSeries = $service->getActiveSeriesDirectory();

        // Search filter
        $query = trim(strtolower($this->search));
        if ($query !== '') {
            $allSeries = array_filter($allSeries, function ($item) use ($query) {
                return str_contains(strtolower($item['series_name'] ?? ''), $query)
                    || str_contains(strtolower($item['format'] ?? ''), $query);
            });
        }

        // Tab / Category filter
        if ($this->filter === 'live') {
            $seriesList = array_values(array_filter($allSeries, fn ($s) => !empty($s['is_live'])));
        } elseif ($this->filter === 'upcoming') {
            $seriesList = array_values(array_filter($allSeries, fn ($s) => empty($s['is_live']) && ($s['upcoming_count'] ?? 0) > 0));
        } else {
            $seriesList = array_values($allSeries);
        }

        $liveCount = count(array_filter($allSeries, fn ($s) => !empty($s['is_live'])));
        $upcomingCount = count(array_filter($allSeries, fn ($s) => empty($s['is_live']) && ($s['upcoming_count'] ?? 0) > 0));
        $totalCount = count($allSeries);

        $sidebarAd = AdBanner::activeSlot('match_sidebar')->first();

        return view('livewire.pages.series-list-page', [
            'seriesList' => $seriesList,
            'liveCount' => $liveCount,
            'upcomingCount' => $upcomingCount,
            'totalCount' => $totalCount,
            'sidebarAd' => $sidebarAd,
        ]);
    }
}
