<?php

namespace App\Livewire\Pages;

use App\Helpers\CricketMatchFormatter;
use App\Models\AdBanner;
use App\Models\NewsArticle;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Cricket\RapidApiCricketService;
use App\Services\Cricket\StaticJsonCacheWriter;
use Carbon\Carbon;
use Livewire\Component;

class SchedulePage extends Component
{
    public string $tab = 'upcoming'; // Options: 'upcoming', 'recent'
    public string $category = 'all'; // Options: 'all', 't20', 'odi', 'test', 'leagues', 't20-leagues'

    public function mount(?string $status = null, ?string $category = null): void
    {
        if ($status && in_array(strtolower($status), ['upcoming', 'recent', 'finished'], true)) {
            $normStatus = strtolower($status);
            $this->tab = ($normStatus === 'finished') ? 'recent' : $normStatus;
        }

        if ($category && in_array(strtolower($category), ['all', 't20', 'odi', 'test', 'leagues', 't20-leagues'], true)) {
            $normalizedCategory = strtolower($category);
            $this->category = $normalizedCategory === 't20-leagues' ? 'leagues' : $normalizedCategory;
        }
    }

    public function setTab(string $tab): void
    {
        $allowed = ['upcoming', 'recent', 'finished'];
        if (in_array(strtolower($tab), $allowed, true)) {
            $this->tab = strtolower($tab) === 'finished' ? 'recent' : strtolower($tab);
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

    public function render(RapidApiCricketService $rapidService, CricketLiveApiService $apiService)
    {
        // 1. Read from static JSON (public/live-data/home.json) first
        $homeData = StaticJsonCacheWriter::read('home.json');
        $rawUpcoming = [];
        $rawRecent = [];

        if ($homeData && is_array($homeData)) {
            $data = isset($homeData['data']) && is_array($homeData['data']) ? $homeData['data'] : $homeData;
            $rawUpcoming = $data['upcoming'] ?? [];
            $rawRecent = $data['recent'] ?? $data['finished'] ?? [];
        }

        // 2. Fallback to RapidApi or CricketLiveApiService if static cache is empty
        if (empty($rawUpcoming) && empty($rawRecent)) {
            $scheduleData = $rapidService->getScheduleMatches(false);
            $rawUpcoming = $scheduleData['upcoming'] ?? [];
            $rawRecent = $scheduleData['recent'] ?? [];

            if (empty($rawUpcoming) && empty($rawRecent)) {
                $rawUpcoming = $apiService->getUpcomingMatches();
                $rawRecent = $apiService->getRecentMatches();
            }
        }

        // 3. Normalize all match items through CricketMatchFormatter
        $normalizedUpcoming = array_values(array_map(function ($m) {
            return CricketMatchFormatter::formatMatchForCard($m);
        }, $rawUpcoming));

        $normalizedRecent = array_values(array_map(function ($m) {
            return CricketMatchFormatter::formatMatchForCard($m);
        }, $rawRecent));

        $upcomingCount = count($normalizedUpcoming);
        $recentCount = count($normalizedRecent);

        // Select active list
        $matches = ($this->tab === 'recent') ? $normalizedRecent : $normalizedUpcoming;

        // 4. Apply category filtering (case-insensitive & comprehensive)
        if ($this->category !== 'all') {
            $target = strtolower($this->category);

            $matches = array_values(array_filter($matches, function ($match) use ($target) {
                if (!is_array($match)) {
                    return false;
                }

                $format = strtolower((string) ($match['format'] ?? ''));
                $matchType = strtolower((string) ($match['match_type'] ?? ''));
                $desc = strtolower((string) ($match['match_desc'] ?? $match['matchs'] ?? ''));
                $series = strtolower((string) ($match['series_name'] ?? $match['series'] ?? ''));
                $rawType = strtolower((string) ($match['raw']['match_type'] ?? ''));
                $combined = "{$format} {$matchType} {$desc} {$series} {$rawType}";

                return match ($target) {
                    't20' => str_contains($format, 't20') || str_contains($matchType, 't20') || str_contains($desc, 't20') || str_contains($combined, 'twenty20'),
                    'odi' => str_contains($format, 'odi') || str_contains($matchType, 'odi') || str_contains($desc, 'odi') || str_contains($combined, 'one day'),
                    'test' => str_contains($format, 'test') || str_contains($matchType, 'test') || str_contains($desc, 'test'),
                    'leagues', 't20-leagues' => str_contains($rawType, 'league') || str_contains($matchType, 'league') || str_contains($combined, 'league') || str_contains($combined, 'ipl') || str_contains($combined, 'bbl') || str_contains($combined, 'psl') || str_contains($combined, 'cpl') || str_contains($combined, 'sa20') || str_contains($combined, 'bpl') || str_contains($combined, 'ilt20'),
                    default => true,
                };
            }));
        }

        // 5. Group matches by date
        $groupedMatches = [];
        $fallbackGroupTitle = ($this->tab === 'recent') ? 'Recent Results' : 'Upcoming Fixtures';

        foreach ($matches as $match) {
            $rawDate = $match['match_date'] ?? $match['date'] ?? $match['start_time'] ?? $match['start_date'] ?? null;
            $formattedDate = $fallbackGroupTitle;

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

        // Active sidebar banner
        $sidebarBanner = AdBanner::activeSlot('schedule_sidebar')->first()
            ?? AdBanner::activeSlot('desktop_right')->first()
            ?? AdBanner::activeSlot('sidebar')->first();

        $seoData = [
            'title' => 'Cricket Schedule 2026 - International, T20 Leagues & Domestic Fixtures | Cricwire',
            'description' => 'Explore comprehensive cricket match schedules, upcoming series fixtures, match dates, venues, and timings for IPL, ICC events, and bilateral series on Cricwire.',
            'canonical' => route('schedule'),
            'robots' => 'index, follow',
            'og_type' => 'website',
            'og_image' => asset('icons/cricwire_favicons/android-chrome-512x512.png'),
        ];

        return view('livewire.pages.schedule-page', [
            'tab' => $this->tab,
            'category' => $this->category,
            'groupedMatches' => $groupedMatches,
            'matches' => $matches,
            'totalCount' => count($matches),
            'totalMatchesCount' => count($matches),
            'upcomingCount' => $upcomingCount,
            'recentCount' => $recentCount,
            'latestNews' => $latestNews,
            'sidebarBanner' => $sidebarBanner,
            'seoData' => $seoData,
        ])->layout('components.layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'],
        ]);
    }
}
