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
        $rawDetail = $service->getMatchDetail($this->matchId);

        \Illuminate\Support\Facades\Log::info("Match Detail API Payload for ID: {$this->matchId}", [
            'raw' => $rawDetail
        ]);

        // Fetch scorecard and commentary data for detailed match sections
        $rawScorecard = $service->getMatchScorecard($this->matchId);
        $rawCommentary = $service->getMatchCommentary($this->matchId);

        // Fetch home matches cache to extract any live-line data (score, rates, batsman, bowler)
        $homeData = $service->getHomeMatches();
        $liveMatchInfo = null;

        $matchesList = $homeData['data'] ?? $homeData['matches'] ?? [];
        foreach ($matchesList as $m) {
            $id = $m['match_id'] ?? $m['id'] ?? null;
            if ((string) $id === (string) $this->matchId) {
                $liveMatchInfo = $m;
                break;
            }
        }

        $detailData = $rawDetail['data'] ?? $rawDetail;
        $match = is_array($detailData) ? $detailData : [];
        $scorecardData = $rawScorecard['data']['scorecard'] ?? $rawScorecard['scorecard'] ?? [];
        $commentaryData = $rawCommentary['data'] ?? $rawCommentary ?? [];

        // Determine if match is upcoming, live, or finished
        $result = $match['result'] ?? $liveMatchInfo['result'] ?? '';
        $rawStatus = $match['match_status'] ?? $liveMatchInfo['match_status'] ?? 'Live';
        $isLive = is_string($rawStatus) ? strtolower($rawStatus) === 'live' : ($rawStatus == 2 || $rawStatus == 1);
        $isFinished = !empty($result) || (is_string($rawStatus) && strtolower($rawStatus) === 'finished');
        $isUpcoming = !$isLive && !$isFinished;

        // Squad extraction: check team_a_squad, squads, teams, etc.
        $teamASquad = $match['team_a_squad'] ?? $match['squad_a'] ?? $match['squads']['team_a'] ?? $match['teams']['team_a']['squad'] ?? $match['team_a_playing_xi'] ?? [];
        $teamBSquad = $match['team_b_squad'] ?? $match['squad_b'] ?? $match['squads']['team_b'] ?? $match['teams']['team_b']['squad'] ?? $match['team_b_playing_xi'] ?? [];

        \Illuminate\Support\Facades\Log::info("Squads data for match {$this->matchId}:", [
            'team_a' => $match['team_a_squad'] ?? $match['squad_a'] ?? [],
            'team_b' => $match['team_b_squad'] ?? $match['squad_b'] ?? []
        ]);

        // Calculate ISO Target Timestamp for upcoming countdown
        $dateTimeRaw = $match['date_time'] ?? $liveMatchInfo['date_time'] ?? null;
        $matchDate = $match['match_date'] ?? $liveMatchInfo['match_date'] ?? null;
        $matchTime = $match['match_time'] ?? $liveMatchInfo['match_time'] ?? '';
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

        // Check or auto-seed the 3 pre-configured polls for this match
        $teamAName = $match['team_a'] ?? $liveMatchInfo['team_a'] ?? 'Team A';
        $teamBName = $match['team_b'] ?? $liveMatchInfo['team_b'] ?? 'Team B';

        $polls = $this->getOrCreateMatchPolls($teamAName, $teamBName);

        // Fetch Featured Articles
        $featuredArticles = NewsArticle::where('is_published', true)
            ->where('is_featured', true)
            ->latest()
            ->take(3)
            ->get();

        // Active sidebar banner (checks match_sidebar with fallback to desktop_right/sidebar)
        $sidebarBanner = \App\Models\AdBanner::activeSlot('match_sidebar')->first()
            ?? \App\Models\AdBanner::activeSlot('desktop_right')->first()
            ?? \App\Models\AdBanner::activeSlot('sidebar')->first();

        // Social links configured by admin
        $socialLinks = \App\Support\LiveEngineConfig::getActiveSocialLinks();

        // Innings Session Summary Table (with Lowest/Highest Rate & Pass/Fail)
        $sessionSummary = SessionController::getMatchSessionTable($this->matchId, $service, array_merge($liveMatchInfo ?? [], $match));

        return view('livewire.pages.match-detail', [
            'match' => $match,
            'matchId' => $this->matchId,
            'tab' => $this->tab,
            'liveInfo' => $liveMatchInfo ?? [],
            'sessionSummary' => $sessionSummary,
            'scorecardData' => $scorecardData,
            'commentaryData' => $commentaryData,
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
                // Fallback virtual poll object
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
