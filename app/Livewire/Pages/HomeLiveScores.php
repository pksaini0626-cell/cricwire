<?php

namespace App\Livewire\Pages;

use App\Helpers\CricketMatchFormatter;
use App\Models\ManagedMatch;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Cricket\StaticJsonCacheWriter;
use Livewire\Component;

class HomeLiveScores extends Component
{
    public string $activeTab = 'live';

    public function setTab(string $tab): void
    {
        $allowed = ['live', 'upcoming', 'finished'];
        if (in_array(strtolower($tab), $allowed, true)) {
            $this->activeTab = strtolower($tab);
        }
    }

    public function render(CricketLiveApiService $apiService)
    {
        // 1. First attempt to read directly from static JSON edge cache (public/live-data/home.json)
        $homeData = StaticJsonCacheWriter::read('home.json');

        $liveMatches = [];
        $upcomingMatches = [];
        $finishedMatches = [];

        if ($homeData && is_array($homeData)) {
            // Support both top-level and nested 'data' structures
            $data = isset($homeData['data']) && is_array($homeData['data']) ? $homeData['data'] : $homeData;
            $liveMatches = $data['live'] ?? [];
            $upcomingMatches = $data['upcoming'] ?? [];
            $finishedMatches = $data['recent'] ?? $data['finished'] ?? [];
        }

        // Fallback to CricketLiveApiService only if empty
        if (empty($liveMatches) && empty($upcomingMatches) && empty($finishedMatches)) {
            $liveMatches = $apiService->getLiveMatches();
            $upcomingMatches = $apiService->getUpcomingMatches();
            $finishedMatches = $apiService->getRecentMatches();
        }

        // 2. Exclude any match where ManagedMatch::where('is_covered', false) has skipped it
        $skippedMatchIds = ManagedMatch::where('is_covered', false)
            ->pluck('match_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $filterAndFormat = function (array $matches) use ($skippedMatchIds) {
            $filtered = array_filter($matches, function ($m) use ($skippedMatchIds) {
                if (!is_array($m)) {
                    return false;
                }
                $mId = (int) ($m['match_id'] ?? $m['id'] ?? 0);
                return !isset($skippedMatchIds[$mId]);
            });

            return array_values(array_map(function ($m) {
                return CricketMatchFormatter::formatMatchForCard($m);
            }, $filtered));
        };

        $liveMatches = $filterAndFormat($liveMatches);
        $upcomingMatches = $filterAndFormat($upcomingMatches);
        $finishedMatches = $filterAndFormat($finishedMatches);

        // Selected list based on active tab
        $currentMatches = match ($this->activeTab) {
            'upcoming' => $upcomingMatches,
            'finished' => $finishedMatches,
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
            'upcomingCount' => count($upcomingMatches),
            'finishedCount' => count($finishedMatches),
            'liveMatches' => $liveMatches,
            'upcomingMatches' => $upcomingMatches,
            'finishedMatches' => $finishedMatches,
            'matches' => $currentMatches,
            'seoData' => $seoData,
        ])->layout('layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'] ?? 'CricWire - Live Cricket Scores',
        ]);
    }
}
