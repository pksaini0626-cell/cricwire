<?php

namespace App\Livewire\Pages;

use App\Models\AdBanner;
use App\Models\NewsArticle;
use App\Services\Cricket\RapidApiCricketService;
use Carbon\Carbon;
use Livewire\Component;

class SchedulePage extends Component
{
    public string $tab = 'upcoming'; // Options: 'upcoming', 'recent'
    public string $category = 'all'; // Options: 'all', 't20', 'odi', 'test', 'leagues', 't20-leagues'

    public function mount(?string $status = null, ?string $category = null): void
    {
        if ($status && in_array(strtolower($status), ['upcoming', 'recent'], true)) {
            $this->tab = strtolower($status);
        }

        if ($category && in_array(strtolower($category), ['all', 't20', 'odi', 'test', 'leagues', 't20-leagues'], true)) {
            $normalizedCategory = strtolower($category);
            $this->category = $normalizedCategory === 't20-leagues' ? 'leagues' : $normalizedCategory;
        }
    }

    public function setTab(string $tab): void
    {
        $allowed = ['upcoming', 'recent'];
        if (in_array(strtolower($tab), $allowed, true)) {
            $this->tab = strtolower($tab);
        }
    }

    public function setCategory(string $category): void
    {
        $allowed = ['all', 't20', 'odi', 'test', 'leagues', 't20-leagues'];
        if (in_array(strtolower($category), $allowed, true)) {
            $normalizedCategory = strtolower($category);
            $this->category = $normalizedCategory === 't20-leagues' ? 'leagues' : $normalizedCategory;
        }
    }

    public function render(RapidApiCricketService $service)
    {
        // Read strictly from cache (allowUpstreamFetch = false)
        $scheduleData = $service->getScheduleMatches(false);
        $matches = $scheduleData[$this->tab] ?? [];

        // Apply category filtering
        if ($this->category !== 'all') {
            $matches = array_filter($matches, function ($match) {
                if (!is_array($match)) {
                    return false;
                }

                $type = strtolower((string) ($match['match_type'] ?? $match['match_desc'] ?? $match['matchs'] ?? $match['series'] ?? ''));
                $target = strtolower($this->category);

                return match ($target) {
                    't20' => str_contains($type, 't20') || str_contains($type, 'twenty20'),
                    'odi' => str_contains($type, 'odi') || str_contains($type, 'one day'),
                    'test' => str_contains($type, 'test'),
                    'leagues', 't20-leagues' => str_contains($type, 'league') || str_contains($type, 'ipl') || str_contains($type, 'bbl') || str_contains($type, 'psl') || str_contains($type, 'cpl') || str_contains($type, 'sa20') || str_contains($type, 'bpl') || str_contains($type, 'ilt20'),
                    default => true,
                };
            });
        }

        // Group matches by date if a date field exists
        $groupedMatches = [];
        foreach ($matches as $match) {
            $rawDate = $match['match_date'] ?? $match['date'] ?? $match['start_date'] ?? $match['dateTimeGMT'] ?? null;
            
            $formattedDate = 'Upcoming Fixtures';
            if (!empty($rawDate)) {
                try {
                    $formattedDate = Carbon::parse($rawDate)->isoFormat('dddd, MMMM D, YYYY');
                } catch (\Exception) {
                    $formattedDate = (string) $rawDate;
                }
            }
            $groupedMatches[$formattedDate][] = $match;
        }

        // Query top 3 latest published news for the sidebar widget
        $latestNews = NewsArticle::where('status', 'published')
            ->latest('published_at')
            ->take(3)
            ->get();

        // Active sidebar banner (checks schedule_sidebar with fallback to desktop_right/sidebar)
        $sidebarBanner = AdBanner::activeSlot('schedule_sidebar')->first()
            ?? AdBanner::activeSlot('desktop_right')->first()
            ?? AdBanner::activeSlot('sidebar')->first();

        return view('livewire.pages.schedule-page', [
            'tab' => $this->tab,
            'category' => $this->category,
            'groupedMatches' => $groupedMatches,
            'matches' => $matches,
            'totalCount' => count($matches),
            'totalMatchesCount' => count($matches),
            'upcomingCount' => count($scheduleData['upcoming'] ?? []),
            'recentCount' => count($scheduleData['recent'] ?? []),
            'latestNews' => $latestNews,
            'sidebarBanner' => $sidebarBanner,
        ])->layout('components.layouts.app');
    }
}