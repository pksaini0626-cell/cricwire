<?php

namespace App\Livewire\Pages;

use App\Helpers\CricketMatchFormatter;
use App\Models\ManagedMatch;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\CricGemsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HomeLiveScores extends Component
{
    public string $activeTab = 'live';
    public int $upcomingPage = 1;
    public int $finishedPage = 1;
    public int $perPage = 10;

    public function mount(CricGemsService $cricGemsService): void
    {
        if (Auth::check()) {
            $cricGemsService->syncOnAuthenticatedVisit(Auth::user());
        }
    }

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

    public function render(
        \App\Http\Controllers\Cricket\LiveMatchController $liveController,
        \App\Http\Controllers\Cricket\UpcomingMatchController $upcomingController,
        \App\Http\Controllers\Cricket\FinishedMatchController $finishedController,
        CricketLiveApiService $apiService
    ) {
        // Track active visitor (skip for search crawlers)
        if (!request()->attributes->get('is_search_crawler') && !app()->bound('crawler_read_only_mode')) {
            \Illuminate\Support\Facades\Cache::put('active_site_visitor', now()->timestamp, 120);
        }

        $formatList = fn (array $items) => array_values(array_map(
            fn ($m) => CricketMatchFormatter::formatMatchForCard($m),
            $items
        ));

        $liveMatches = $apiService->enrichMatchesWithTossState($formatList($liveController->getLiveMatches()));
        $upcomingMatches = $formatList($upcomingController->getUpcomingMatches());
        $todayUpcomingMatches = $apiService->enrichMatchesWithTossState($formatList($apiService->filterTodayUpcomingMatches($upcomingMatches)));
        $finishedMatches = $formatList($finishedController->getFinishedMatches());

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
        $paginatedUpcomingMatches = $apiService->enrichMatchesWithTossState(array_slice($upcomingMatches, ($this->upcomingPage - 1) * $this->perPage, $this->perPage));
        $paginatedFinishedMatches = array_slice($finishedMatches, ($this->finishedPage - 1) * $this->perPage, $this->perPage);

        // Hydrate missing finished scores for the active finished page (DB archive + once-per-match scorecard lookup)
        if ($this->activeTab === 'finished' && !empty($paginatedFinishedMatches)) {
            $paginatedFinishedMatches = $formatList($finishedController->hydratePageMatches($paginatedFinishedMatches));
            $hydratedById = [];
            foreach ($paginatedFinishedMatches as $hm) {
                $hId = (string) ($hm['match_id'] ?? $hm['id'] ?? '');
                if ($hId !== '') {
                    $hydratedById[$hId] = $hm;
                }
            }
            $finishedMatches = array_map(function ($m) use ($hydratedById) {
                $mId = (string) ($m['match_id'] ?? $m['id'] ?? '');
                return $hydratedById[$mId] ?? $m;
            }, $finishedMatches);
        }

        // Selected list based on active tab
        $currentMatches = match ($this->activeTab) {
            'upcoming' => $paginatedUpcomingMatches,
            'finished' => $paginatedFinishedMatches,
            default => $liveMatches,
        };

        $featuredNews = \App\Models\NewsArticle::where('status', 'published')
            ->where('is_featured', true)
            ->latest('published_at')
            ->take(2)
            ->get();

        if ($featuredNews->isEmpty()) {
            $featuredNews = \App\Models\NewsArticle::where('status', 'published')
                ->latest('published_at')
                ->take(1)
                ->get();
        }

        $recentNews = \App\Models\NewsArticle::where('status', 'published')
            ->latest('published_at')
            ->take(5)
            ->get();

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
            'todayUpcomingMatches' => $todayUpcomingMatches,
            'finishedMatches' => $finishedMatches,
            'paginatedUpcomingMatches' => $paginatedUpcomingMatches,
            'paginatedFinishedMatches' => $paginatedFinishedMatches,
            'upcomingPage' => $this->upcomingPage,
            'upcomingTotalPages' => $upcomingTotalPages,
            'finishedPage' => $this->finishedPage,
            'finishedTotalPages' => $finishedTotalPages,
            'perPage' => $this->perPage,
            'matches' => $currentMatches,
            'featuredNews' => $featuredNews,
            'recentNews' => $recentNews,
            'seoData' => $seoData,
        ])->layout('layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'] ?? 'CricWire - Live Cricket Scores',
        ]);
    }
}
