<?php

namespace App\Livewire\Pages;

use App\Models\AdBanner;
use App\Models\NewsArticle;
use App\Services\Cricket\CricketLiveApiService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SeriesDetailPage extends Component
{
    public string $seriesSlug;
    public string $tab = 'matches'; // 'matches', 'points-table', 'news', 'squads'
    public bool $hasPointsTable = false;
    public ?int $selectedTeamSquadIndex = 0;

    public function mount(CricketLiveApiService $service, string $seriesSlug, ?string $tab = null): void
    {
        $this->seriesSlug = \Illuminate\Support\Str::slug(trim($seriesSlug));

        // CRITICAL: Validate Series Hub Data (404 immediately if skipped or non-existent)
        $seriesData = $service->getSeriesHubData($this->seriesSlug);
        if (!$seriesData) {
            abort(404, 'Cricket Series Not Found or Coverage Skipped.');
        }

        // Check Points Table availability (Rule: >= 3 teams)
        $pointsTable = $service->getSeriesPointsTable($seriesData['series_id'] ?? null, $this->seriesSlug);
        $this->hasPointsTable = count($pointsTable) >= 3;

        // Set Tab
        $requestedTab = strtolower(trim($tab ?? 'matches'));
        $allowedTabs = ['matches', 'points-table', 'news', 'squads'];
        if (in_array($requestedTab, $allowedTabs, true)) {
            $this->tab = $requestedTab;
        } else {
            $this->tab = 'matches';
        }

        // If user accessed points-table tab on bilateral series without points table, fallback to matches
        if ($this->tab === 'points-table' && !$this->hasPointsTable) {
            $this->tab = 'matches';
        }
    }

    public function setTab(string $tab): void
    {
        $allowed = ['matches', 'points-table', 'news', 'squads'];
        if (!in_array($tab, $allowed, true)) {
            return;
        }

        if ($tab === 'points-table' && !$this->hasPointsTable) {
            return;
        }

        $this->tab = $tab;

        // Update URL bar without full page reload
        $newUrl = route('series.detail', ['seriesSlug' => $this->seriesSlug, 'tab' => $this->tab]);
        $this->js("window.history.replaceState({}, '', '{$newUrl}');");
    }

    public function selectTeamSquad(int $index): void
    {
        $this->selectedTeamSquadIndex = $index;
    }

    public function render(CricketLiveApiService $service)
    {
        $series = $service->getSeriesHubData($this->seriesSlug);
        if (!$series) {
            abort(404);
        }

        // Points Table
        $pointsTable = $this->hasPointsTable
            ? $service->getSeriesPointsTable($series['series_id'] ?? null, $this->seriesSlug)
            : [];

        // Squads (lazy load if tab is squads)
        $squads = [];
        if ($this->tab === 'squads') {
            $squads = $service->getSeriesSquads($series['series_id'] ?? null, $this->seriesSlug);
        }

        // Editorial News Linking to this Series
        $seriesName = $series['series_name'] ?? '';
        $participatingTeams = [];
        foreach (array_merge($series['matches']['live'] ?? [], $series['matches']['upcoming'] ?? [], $series['matches']['completed'] ?? []) as $m) {
            if (!empty($m['team_a'])) $participatingTeams[] = $m['team_a'];
            if (!empty($m['team_b'])) $participatingTeams[] = $m['team_b'];
        }
        $participatingTeams = array_values(array_unique(array_filter($participatingTeams)));

        $seriesNewsQuery = NewsArticle::where(function ($q) {
            $q->where('status', 'published')
              ->orWhere('is_published', true);
        })->where(function ($query) use ($seriesName, $participatingTeams) {
            $query->where('series_slug', $this->seriesSlug);

            if ($seriesName !== '') {
                $query->orWhere('title', 'like', "%{$seriesName}%")
                      ->orWhere('summary', 'like', "%{$seriesName}%");
            }

            foreach (array_slice($participatingTeams, 0, 4) as $team) {
                if (strlen($team) >= 4) {
                    $query->orWhere('title', 'like', "%{$team}%");
                }
            }
        })
        ->orderByDesc('published_at')
        ->limit(12);

        $seriesNews = $seriesNewsQuery->get();

        $sidebarAd = AdBanner::activeSlot('match_sidebar')->first();

        return view('livewire.pages.series-detail-page', [
            'series' => $series,
            'pointsTable' => $pointsTable,
            'squads' => $squads,
            'seriesNews' => $seriesNews,
            'sidebarAd' => $sidebarAd,
        ]);
    }
}
