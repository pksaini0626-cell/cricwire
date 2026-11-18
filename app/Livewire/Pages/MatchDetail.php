<?php

namespace App\Livewire\Pages;

use App\Helpers\CricketSlugHelper;
use App\Http\Controllers\Cricket\SessionController;
use App\Models\NewsArticle;
use App\Models\Poll;
use App\Models\PollVote;
use App\Services\Cricket\CricketUrlService;
use App\Services\Cricket\RapidApiCricketService;
use Illuminate\Support\Facades\Request;
use Livewire\Component;

class MatchDetail extends Component
{
    public string|int $matchId;
    public string $tab = 'live-line'; // Options: 'live-line', 'commentary', 'scorecard', 'squad', 'odds', 'info'
    public ?string $seriesSlug = null;
    public ?string $matchSlug = null;

    /**
     * Mount component supporting both:
     * 1. New SEO route: /match-detail/{seriesSlug}/{tab}/{matchSlug}
     * 2. Backward compatible route: /live-cricket-score/{matchId}/{tab?}
     */
    public function mount(
        string|int|null $matchId = null,
        ?string $tab = 'live-line',
        ?string $seriesSlug = null,
        ?string $matchSlug = null
    ): void {
        $this->seriesSlug = $seriesSlug;
        $this->matchSlug = $matchSlug;

        // Resolve match ID dynamically: first check CricketSlugHelper extracted trailing ID, then CricketUrlService
        $resolved = null;
        if (!empty($matchSlug)) {
            $resolved = CricketSlugHelper::extractMatchId($matchSlug);
        }

        if (!$resolved) {
            $rawTarget = $matchSlug ?: $matchId;
            $resolved = CricketUrlService::resolveMatchId($rawTarget);
        }

        $this->matchId = $resolved ?? $matchId ?? 1;
        $this->tab = CricketUrlService::normalizeTab($tab);
    }

    public function setTab(string $tab): void
    {
        $this->tab = CricketUrlService::normalizeTab($tab);
    }

    /**
     * Submit an interactive prediction vote from the upcoming match screen.
     */
    public function vote(int $pollId, string $option): void
    {
        $poll = Poll::find($pollId);
        if (!$poll || !$poll->is_active) {
            return;
        }

        $validOptions = ['option_a', 'option_b', 'option_c'];
        if (!in_array($option, $validOptions, true)) {
            return;
        }

        $voteCol = match ($option) {
            'option_a' => 'votes_a',
            'option_b' => 'votes_b',
            'option_c' => 'votes_c',
        };

        $poll->increment($voteCol);

        try {
            PollVote::create([
                'poll_id' => $poll->id,
                'user_id' => auth()->id(),
                'ip_address' => Request::ip(),
                'selected_option' => $option,
            ]);
        } catch (\Throwable $e) {
            // Unique vote constraint fallback
        }
    }

    public function render(RapidApiCricketService $service)
    {
        // 1. Core Data: Fetch /info and /live line strictly from cache (allowUpstreamFetch = false)
        // Upstream RapidAPI hits are solely reserved for LiveMatchPollerCommand daemon
        $infoData = $service->getMatchInfo($this->matchId, false);
        $liveLineData = $service->getMatchLiveLine($this->matchId, false);

        // Fetch home matches list for fallback / live-line synchronization
        $homeData = $service->getHomeMatches(false);
        $liveMatchInfo = null;

        $matchesList = is_array($homeData) ? $homeData : [];
        foreach ($matchesList as $m) {
            if (!is_array($m)) continue;
            $id = $m['match_id'] ?? $m['id'] ?? null;
            if ((string) $id === (string) $this->matchId) {
                $liveMatchInfo = $m;
                break;
            }
        }

        // Consolidated match metadata
        $match = array_merge($liveMatchInfo ?? [], $infoData, $liveLineData);

        // 2. Normalize Live Batters and Current Bowler strictly according to API schema
        // The API returns $liveLine['batsman'] (array of 2 on-crease batters)
        $rawBatters = $liveLineData['batsman'] ?? $match['batsman'] ?? [];
        $liveBatters = [];
        if (is_array($rawBatters)) {
            foreach ($rawBatters as $b) {
                if (!is_array($b)) continue;
                $bName = trim((string) ($b['name'] ?? ''));
                if ($bName !== '' && $bName !== '-') {
                    $liveBatters[] = $b;
                }
            }
        }

        // The API spells bowler as 'bolwer' (b-o-l-w-e-r)
        $rawBolwer = $liveLineData['bolwer'] ?? $match['bolwer'] ?? $liveLineData['bowler'] ?? $match['bowler'] ?? [];
        $liveBowler = is_array($rawBolwer) ? $rawBolwer : [];

        // 3. Tab-Specific On-Demand Fetching (Preserving RapidAPI Quota, strictly cached)
        $scorecardData = [];
        $playingXI = [];
        $impactPlayers = [];
        $trackerData = [];
        $commentaryData = [];

        if ($this->tab === 'scorecard') {
            $rawScorecard = $service->getMatchScorecard($this->matchId, false);
            $scorecardData = $rawScorecard['scorecard'] ?? $rawScorecard['data']['scorecard'] ?? (is_array($rawScorecard) ? $rawScorecard : []);
        } elseif ($this->tab === 'squad') {
            $playingXI = $service->getMatchPlayingXI($this->matchId, false);
            $impactPlayers = $service->getMatchImpactPlayers($this->matchId, false);
        } elseif ($this->tab === 'commentary' || $this->tab === 'live-line') {
            $commentaryData = $service->getMatchCommentary($this->matchId, false);
        }

        if ($this->tab === 'info') {
            $trackerData = $service->getMatchTracker($this->matchId, false);
        }

        // 4. Status determination
        $result = $match['result'] ?? '';
        $rawStatus = $match['match_status'] ?? 'Live';
        $isLive = is_string($rawStatus) 
            ? in_array(strtolower($rawStatus), ['live', 'in progress', 'innings break', '1', '2'])
            : ($rawStatus == 2 || $rawStatus == 1);
        $isFinished = !empty($result) || (is_string($rawStatus) && in_array(strtolower($rawStatus), ['finished', 'completed', '3'])) || ($rawStatus == 3);
        $isUpcoming = !$isLive && !$isFinished;

        // Squad fallback from playingXI or match
        $teamASquad = $playingXI['team_a']['player'] ?? $match['team_a_playing_xi'] ?? $match['team_a_squad'] ?? [];
        $teamBSquad = $playingXI['team_b']['player'] ?? $match['team_b_playing_xi'] ?? $match['team_b_squad'] ?? [];

        // 5. Calculate ISO Target Timestamp for upcoming countdown
        $dateTimeRaw = $match['date_time'] ?? null;
        $matchDate = $match['match_date'] ?? null;
        $matchTime = $match['match_time'] ?? '';
        $matchStartTime = null;

        if (!empty($dateTimeRaw)) {
            try {
                $matchStartTime = \Carbon\Carbon::parse($dateTimeRaw)->toIso8601String();
            } catch (\Throwable $e) {}
        }
        if (!$matchStartTime && !empty($matchDate)) {
            try {
                $matchStartTime = \Carbon\Carbon::parse($matchDate . ' ' . ($matchTime ?: '12:00 PM'))->toIso8601String();
            } catch (\Throwable $e) {}
        }

        // 6. Check or auto-seed the 3 pre-configured polls for this match
        $teamAName = $match['team_a'] ?? 'Team A';
        $teamBName = $match['team_b'] ?? 'Team B';
        $polls = $this->getOrCreateMatchPolls($teamAName, $teamBName);

        // Fetch Featured Articles & Sidebar Ads
        $featuredArticles = NewsArticle::where('is_published', true)
            ->where('is_featured', true)
            ->latest()
            ->take(3)
            ->get();

        $sidebarBanner = \App\Models\AdBanner::activeSlot('match_sidebar')->first()
            ?? \App\Models\AdBanner::activeSlot('desktop_right')->first()
            ?? \App\Models\AdBanner::activeSlot('sidebar')->first();

        $socialLinks = \App\Support\LiveEngineConfig::getActiveSocialLinks();

        // 7. Innings Session Summary Table (with Lowest/Highest Rate & Pass/Fail)
        $sessionSummary = SessionController::getMatchSessionTable($this->matchId, $service, $match);

        return view('livewire.pages.match-detail', [
            'match' => $match,
            'matchId' => $this->matchId,
            'tab' => $this->tab,
            'liveInfo' => $match,
            'liveLine' => $liveLineData,
            'liveBatters' => $liveBatters,
            'liveBowler' => $liveBowler,
            'sessionSummary' => $sessionSummary,
            'scorecardData' => $scorecardData,
            'commentaryData' => $commentaryData,
            'playingXI' => $playingXI,
            'impactPlayers' => $impactPlayers,
            'trackerData' => $trackerData,
            'seriesSlug' => $this->seriesSlug,
            'matchSlug' => $this->matchSlug,
            'teamASquad' => $teamASquad,
            'teamBSquad' => $teamBSquad,
            'matchStartTime' => $matchStartTime,
            'isUpcoming' => $isUpcoming,
            'polls' => $polls,
            'featuredArticles' => $featuredArticles,
            'sidebarBanner' => $sidebarBanner,
            'socialLinks' => $socialLinks,
        ])->layout('components.layouts.app');
    }

    /**
     * Retrieve or dynamically prepare the 3 standard prediction polls for upcoming fixtures.
     */
    protected function getOrCreateMatchPolls(string $teamA, string $teamB): array
    {
        $questions = [
            1 => [
                'question' => "Who will win the match between {$teamA} and {$teamB}?",
                'option_a' => $teamA,
                'option_b' => $teamB,
                'option_c' => 'Draw / Tie',
            ],
            2 => [
                'question' => "Who will win the toss?",
                'option_a' => $teamA,
                'option_b' => $teamB,
                'option_c' => null,
            ],
            3 => [
                'question' => "Total Wickets in this Match?",
                'option_a' => '5+ Wickets',
                'option_b' => '10+ Wickets',
                'option_c' => '15+ Wickets',
            ],
        ];

        $results = [];

        foreach ($questions as $index => $qData) {
            try {
                $poll = Poll::firstOrCreate(
                    [
                        'match_id' => is_numeric($this->matchId) ? (int) $this->matchId : null,
                        'question' => $qData['question'],
                    ],
                    [
                        'option_a' => $qData['option_a'],
                        'option_b' => $qData['option_b'],
                        'option_c' => $qData['option_c'],
                        'votes_a' => rand(15, 65),
                        'votes_b' => rand(20, 70),
                        'votes_c' => $qData['option_c'] ? rand(3, 12) : 0,
                        'is_active' => true,
                        'expires_at' => now()->addDays(2),
                    ]
                );
                $results[] = $poll;
            } catch (\Throwable $e) {
                $results[] = (object) [
                    'id' => $index,
                    'question' => $qData['question'],
                    'option_a' => $qData['option_a'],
                    'option_b' => $qData['option_b'],
                    'option_c' => $qData['option_c'],
                    'votes_a' => 45,
                    'votes_b' => 52,
                    'votes_c' => 8,
                ];
            }
        }

        return $results;
    }
}