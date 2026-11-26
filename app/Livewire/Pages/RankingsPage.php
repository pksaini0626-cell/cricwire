<?php

namespace App\Livewire\Pages;

use App\Models\AdBanner;
use App\Models\NewsArticle;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Seo\CricketSeoService;
use Carbon\Carbon;
use Livewire\Component;

class RankingsPage extends Component
{
    public string $category = 'batting'; // batting, bowling, allrounder, teams
    public string $format = 't20';       // t20, odi, test
    public string $search = '';

    public function mount(?string $category = null, ?string $format = null): void
    {
        if ($category !== null) {
            $this->category = CricketLiveApiService::normalizeRankingCategory($category);
        }

        if ($format !== null) {
            $this->format = CricketLiveApiService::normalizeRankingFormat($format);
        }
    }

    public function setCategory(string $category): void
    {
        $clean = strtolower(trim($category));
        if (in_array($clean, CricketLiveApiService::ICC_RANKING_CATEGORIES, true)) {
            $this->category = $clean;
            $this->syncBrowserUrl();
        }
    }

    public function setFormat(string $format): void
    {
        $clean = strtolower(trim($format));
        if (in_array($clean, CricketLiveApiService::ICC_RANKING_FORMATS, true)) {
            $this->format = $clean;
            $this->syncBrowserUrl();
        }
    }

    protected function syncBrowserUrl(): void
    {
        $url = ($this->category === 'batting' && $this->format === 't20')
            ? route('rankings')
            : route('rankings', ['category' => $this->category, 'format' => $this->format]);

        $this->js("window.history.replaceState({}, '', '{$url}')");
    }

    public function render(CricketLiveApiService $apiService)
    {
        // Primary cache read with atomic self-healing lazy hydration on miss
        $payload = $apiService->getIccRankings($this->category, $this->format);
        $allRankings = $payload['rankings'] ?? [];

        $query = strtolower(trim($this->search));
        if ($query !== '') {
            $rankings = array_values(array_filter($allRankings, function (array $row) use ($query) {
                return str_contains(strtolower((string) ($row['name'] ?? '')), $query)
                    || str_contains(strtolower((string) ($row['country'] ?? '')), $query)
                    || str_contains(strtolower((string) ($row['country_short'] ?? '')), $query)
                    || (string) ($row['rank'] ?? '') === $query;
            }));
        } else {
            $rankings = $allRankings;
        }

        $topLeader = ($query === '' && !empty($allRankings)) ? $allRankings[0] : null;

        $syncedAtFormatted = null;
        if (!empty($payload['synced_at'])) {
            try {
                $syncedAtFormatted = Carbon::parse($payload['synced_at'])
                    ->timezone('Asia/Kolkata')
                    ->format('d M Y, h:i A') . ' IST';
            } catch (\Throwable) {
                $syncedAtFormatted = null;
            }
        }

        $latestNews = NewsArticle::where('status', 'published')
            ->latest('published_at')
            ->take(3)
            ->get();

        $sidebarBanner = AdBanner::activeSlot('rankings_sidebar')->first()
            ?? AdBanner::activeSlot('desktop_right')->first()
            ?? AdBanner::activeSlot('sidebar')->first();

        $seoData = CricketSeoService::forRankings($this->category, $this->format, $allRankings);

        $categoryLabels = [
            'batting' => 'Batting',
            'bowling' => 'Bowling',
            'allrounder' => 'All-Rounders',
            'teams' => 'Teams',
        ];

        $formatLabels = [
            't20' => 'T20I',
            'odi' => 'ODI',
            'test' => 'Test',
        ];

        return view('livewire.pages.rankings-page', [
            'category' => $this->category,
            'format' => $this->format,
            'categoryLabels' => $categoryLabels,
            'formatLabels' => $formatLabels,
            'availableFormats' => $payload['available_formats'] ?? CricketLiveApiService::ICC_RANKING_FORMATS,
            'categoryType' => $payload['category_type'] ?? $this->category,
            'totalCount' => count($allRankings),
            'rankings' => $rankings,
            'topLeader' => $topLeader,
            'syncedAtFormatted' => $syncedAtFormatted,
            'latestNews' => $latestNews,
            'sidebarBanner' => $sidebarBanner,
            'seoData' => $seoData,
        ])->layout('components.layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'],
        ]);
    }
}
