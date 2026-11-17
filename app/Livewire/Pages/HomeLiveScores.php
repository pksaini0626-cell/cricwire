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
        // 1. Fetch matches: Try dedicated endpoints, fall back to /home partitioning
        $homeData = $cricketService->getHomeMatches();

        // Check if $homeData is an array of matches (e.g. from $json['data'])
        $allMatches = is_array($homeData) ? $homeData : [];

        $liveMatches = [];
        $upcomingMatches = [];
        $finishedMatches = [];

        // Partition matches strictly according to verified API match_status values
        foreach ($allMatches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $status = strtolower(trim((string) ($match['match_status'] ?? $match['status'] ?? '')));
            $result = trim((string) ($match['result'] ?? ''));

            if ($status === 'live' || $status === 'in progress' || $status === 'innings break' || $status === '1' || $status === '2') {
                $liveMatches[] = $match;
            } elseif ($status === 'finished' || $status === 'completed' || $status === '3' || !empty($result)) {
                $finishedMatches[] = $match;
            } elseif ($status === 'upcoming' || $status === 'scheduled' || $status === 'not started' || $status === '0') {
                $upcomingMatches[] = $match;
            } else {
                // Default based on result availability
                if (!empty($result)) {
                    $finishedMatches[] = $match;
                } else {
                    $upcomingMatches[] = $match;
                }
            }
        }

        // If live matches are empty, check dedicated /liveMatches endpoint
        if (empty($liveMatches)) {
            $dedicatedLive = $cricketService->getLiveMatches();
            if (!empty($dedicatedLive) && is_array($dedicatedLive)) {
                $liveMatches = $dedicatedLive;
            }
        }

        // Filter and cap upcoming and finished matches to clean, fast date windows
        $upcomingMatches = $cricketService->filterMatchesByDateWindow($upcomingMatches, 'upcoming');
        $finishedMatches = $cricketService->filterMatchesByDateWindow($finishedMatches, 'recent');

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
