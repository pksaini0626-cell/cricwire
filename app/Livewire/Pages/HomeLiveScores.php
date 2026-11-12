<?php

namespace App\Livewire\Pages;

use App\Services\Cricket\RapidApiCricketService;
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

    public function render(RapidApiCricketService $cricketService)
    {
        $raw = $cricketService->getHomeMatches();

        // The API returns matches either under 'data' or directly as arrays / keyed buckets
        $matches = [];
        if (isset($raw['data']) && is_array($raw['data'])) {
            $matches = $raw['data'];
        } elseif (isset($raw['matches']) && is_array($raw['matches'])) {
            $matches = $raw['matches'];
        } elseif (is_array($raw) && !empty($raw) && isset($raw[0])) {
            $matches = $raw;
        }

        $liveMatches = [];
        $upcomingMatches = [];
        $finishedMatches = [];

        // Check if API has pre-partitioned keys like $raw['live_matches'], $raw['upcoming_matches'], etc.
        if (!empty($raw['live_matches']) && is_array($raw['live_matches'])) {
            $liveMatches = $raw['live_matches'];
        }
        if (!empty($raw['upcoming_matches']) && is_array($raw['upcoming_matches'])) {
            $upcomingMatches = $raw['upcoming_matches'];
        }
        if (!empty($raw['finished_matches']) && is_array($raw['finished_matches'])) {
            $finishedMatches = $raw['finished_matches'];
        } elseif (!empty($raw['recent_matches']) && is_array($raw['recent_matches'])) {
            $finishedMatches = $raw['recent_matches'];
        }

        // Categorize match list if items are in unified 'data' array
        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $status = strtolower(trim((string) ($match['match_status'] ?? $match['status'] ?? '')));
            $result = trim((string) ($match['result'] ?? ''));

            if ($status === 'live' || $status === 'in progress' || $status === 'innings break') {
                $liveMatches[] = $match;
            } elseif ($status === 'finished' || $status === 'completed' || $status === 'result' || !empty($result)) {
                $finishedMatches[] = $match;
            } elseif ($status === 'upcoming' || $status === 'scheduled' || $status === 'not started') {
                $upcomingMatches[] = $match;
            } else {
                // Fallback heuristic: if result is empty and date_time > now, upcoming; otherwise finished/live
                $upcomingMatches[] = $match;
            }
        }

        // Selected list based on active tab
        $currentMatches = match ($this->activeTab) {
            'upcoming' => $upcomingMatches,
            'finished' => $finishedMatches,
            default => $liveMatches,
        };

        return view('livewire.pages.home-live-scores', [
            'activeTab' => $this->activeTab,
            'liveCount' => count($liveMatches),
            'upcomingCount' => count($upcomingMatches),
            'finishedCount' => count($finishedMatches),
            'liveMatches' => $liveMatches,
            'upcomingMatches' => $upcomingMatches,
            'finishedMatches' => $finishedMatches,
            'matches' => $currentMatches,
        ]);
    }
}
