<?php

namespace App\Livewire\Pages;

use App\Helpers\CricketMatchFormatter;
use App\Models\ManagedMatch;
use App\Services\Cricket\CricketLiveApiService;
use Livewire\Component;

class HomeLiveScores extends Component
{
    public string $activeTab = 'live';
    public int $upcomingPage = 1;
    public int $finishedPage = 1;
    public int $perPage = 10;

    public function setTab(string $tab): void
    {
        $allowed = ['live', 'upcoming', 'finished'];
        if (in_array(strtolower($tab), $allowed, true)) {
            $this->activeTab = strtolower($tab);
        }
    }

    public function nextUpcomingPage(): void
    {
        $this->upcomingPage++;
    }

    public function prevUpcomingPage(): void
    {
        if ($this->upcomingPage > 1) {
            $this->upcomingPage--;
        }
    }

    public function setUpcomingPage(int $page): void
    {
        $this->upcomingPage = max(1, $page);
    }

    public function nextFinishedPage(): void
    {
        $this->finishedPage++;
    }

    public function prevFinishedPage(): void
    {
        if ($this->finishedPage > 1) {
            $this->finishedPage--;
        }
    }

    public function setFinishedPage(int $page): void
    {
        $this->finishedPage = max(1, $page);
    }

    public function render(CricketLiveApiService $apiService)
    {
        // Track active visitor (skip for search crawlers)
        if (!request()->attributes->get('is_search_crawler') && !app()->bound('crawler_read_only_mode')) {
            \Illuminate\Support\Facades\Cache::put('active_site_visitor', now()->timestamp, 120);
        }

        // Direct Fast Real-Time Live Cricket Data backed strictly by Redis / cache
        $homeData = $apiService->getHomeFromRedis();

        // Also check static public/live-data/home.json if present as fallback or merge source
        $staticHomeFile = public_path('live-data/home.json');
        $staticHome = [];
        if (file_exists($staticHomeFile)) {
            $decoded = json_decode(@file_get_contents($staticHomeFile), true);
            if (is_array($decoded)) {
                $staticHome = $decoded;
            }
        }

        // Extra safeguard: sanitize and re-classify all source matches using strict bucket rules
        $rawLive = array_merge($homeData['live'] ?? [], $staticHome['live'] ?? []);
        $rawUpcoming = array_merge($homeData['upcoming'] ?? [], $staticHome['upcoming'] ?? []);
        $rawFinished = array_merge(
            $homeData['recent'] ?? ($homeData['finished'] ?? []),
            $staticHome['recent'] ?? ($staticHome['finished'] ?? [])
        );

        $sanitized = CricketLiveApiService::sanitizeMatchBuckets($rawLive, $rawUpcoming, $rawFinished);
        $liveMatches = $sanitized['live'];
        $upcomingMatches = $sanitized['upcoming'];
        $finishedMatches = $sanitized['finished'];

        // Exclude any match where ManagedMatch is not covered or series is skipped
        $skippedMatchIds = ManagedMatch::where('is_covered', false)
            ->orWhere('series_skipped', true)
            ->pluck('match_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $skippedSeriesNames = \App\Models\SkippedSeries::where('is_skipped', true)
            ->pluck('series_name')
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->filter()
            ->flip()
            ->all();

        $filterAndFormat = function (array $matches) use ($skippedMatchIds, $skippedSeriesNames) {
            $filtered = array_filter($matches, function ($m) use ($skippedMatchIds, $skippedSeriesNames) {
                if (!is_array($m)) {
                    return false;
                }
                $mId = (int) ($m['match_id'] ?? $m['id'] ?? 0);
                if (isset($skippedMatchIds[$mId])) {
                    return false;
                }
                $seriesName = strtolower(trim((string) ($m['series_name'] ?? $m['series'] ?? $m['series_title'] ?? '')));
                if ($seriesName !== '' && isset($skippedSeriesNames[$seriesName])) {
                    return false;
                }
                return true;
            });

            return array_values(array_map(function ($m) {
                return CricketMatchFormatter::formatMatchForCard($m);
            }, $filtered));
        };

        $liveMatches = $filterAndFormat($liveMatches);
        $upcomingMatches = $filterAndFormat($upcomingMatches);
        $finishedMatches = $filterAndFormat($finishedMatches);

        // Pagination bounds calculation
        $upcomingTotal = count($upcomingMatches);
        $upcomingTotalPages = max(1, (int) ceil($upcomingTotal / $this->perPage));
        if ($this->upcomingPage > $upcomingTotalPages) {
            $this->upcomingPage = $upcomingTotalPages;
        }

        $finishedTotal = count($finishedMatches);
        $finishedTotalPages = max(1, (int) ceil($finishedTotal / $this->perPage));
        if ($this->finishedPage > $finishedTotalPages) {
            $this->finishedPage = $finishedTotalPages;
        }

        // Paginated slices for upcoming and finished
        $paginatedUpcomingMatches = array_slice($upcomingMatches, ($this->upcomingPage - 1) * $this->perPage, $this->perPage);
        $paginatedFinishedMatches = array_slice($finishedMatches, ($this->finishedPage - 1) * $this->perPage, $this->perPage);

        // Selected list based on active tab
        $currentMatches = match ($this->activeTab) {
            'upcoming' => $paginatedUpcomingMatches,
            'finished' => $paginatedFinishedMatches,
            default => $liveMatches,
        };

        $seoData = class_exists(\App\Services\Seo\CricketSeoService::class)
            ? \App\Services\Seo\CricketSeoService::forHome()
            : [
                'title' => 'Live Cricket Scores & Ball-by-Ball Commentary | CricWire',
                'description' => 'Fastest live cricket score updates, ball-by-ball commentary, and match schedules.',
            ];

        return view('livewire.pages.home-live-scores', [
            'activeTab' => $this->activeTab,
            'liveCount' => count($liveMatches),
            'upcomingCount' => $upcomingTotal,
            'finishedCount' => $finishedTotal,
            'liveMatches' => $liveMatches,
            'upcomingMatches' => $upcomingMatches,
            'finishedMatches' => $finishedMatches,
            'paginatedUpcomingMatches' => $paginatedUpcomingMatches,
            'paginatedFinishedMatches' => $paginatedFinishedMatches,
            'upcomingPage' => $this->upcomingPage,
            'upcomingTotalPages' => $upcomingTotalPages,
            'finishedPage' => $this->finishedPage,
            'finishedTotalPages' => $finishedTotalPages,
            'perPage' => $this->perPage,
            'matches' => $currentMatches,
            'seoData' => $seoData,
        ])->layout('layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'] ?? 'CricWire - Live Cricket Scores',
        ]);
    }
}
