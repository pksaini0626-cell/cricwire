<?php

namespace App\Livewire\Pages;

use App\Helpers\CricketSlugHelper;
use App\Http\Controllers\Cricket\CompletedMatchController;
use App\Http\Controllers\Cricket\SessionController;
use App\Models\ManagedMatch;
use App\Models\NewsArticle;
use App\Models\Poll;
use App\Models\PollVote;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Cricket\CricketUrlService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Livewire\Component;

class MatchDetail extends Component
{
    public string|int $matchId;
    public string $tab = 'live-line'; // Options: 'live-line', 'commentary', 'scorecard', 'squad', 'odds', 'info', 'highlights'
    public ?string $seriesSlug = null;
    public ?string $matchSlug = null;
    public bool $isCompleted = false;
    public bool $commentaryUnavailable = false;
    public array $recentBalls = [];

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

        // Auto-select scorecard for finished matches if default 'live-line' requested
        $requestedTab = CricketUrlService::normalizeTab($tab);
        $apiService = app(CricketLiveApiService::class);
        $isFinishedMatch = $apiService->isFinishedMatch($this->matchId);

        // Pre-hydrate fast match data immediately on initial page mount (0 delay, batters + bowler populated on first paint)
        $fastData = $apiService->getFastMatchData($this->matchId);
        if (!empty($fastData)) {
            $fastState = strtolower((string) ($fastData['state'] ?? $fastData['status'] ?? ''));
            if (in_array($fastState, ['finished', 'completed', 'complete', '3'], true) || !empty($fastData['result'])) {
                $isFinishedMatch = true;
            }
            $this->recentBalls = $apiService->extractRecentBalls(
                !empty($fastData['commentary']) && is_array($fastData['commentary'])
                    ? $fastData['commentary']
                    : (is_array($fastData['recent_balls'] ?? null) ? $fastData['recent_balls'] : []),
                is_array($fastData['over_history'] ?? $fastData['overHistory'] ?? $fastData['recent_overs'] ?? null)
                    ? ($fastData['over_history'] ?? $fastData['overHistory'] ?? $fastData['recent_overs'])
                    : [],
                18
            );
        }

        if ($requestedTab === 'live-line' && $isFinishedMatch) {
            $requestedTab = 'scorecard';
        }

        $this->tab = $requestedTab;

        $isCrawler = request()->attributes->get('is_search_crawler') || app()->bound('crawler_read_only_mode');
        if (!$isCrawler) {
            if (!$isFinishedMatch) {
                Cache::put("watching:match:{$this->matchId}", now()->timestamp, 45);
            }
            Cache::put('active_site_visitor', now()->timestamp, 120);
        }

        \Illuminate\Support\Facades\Log::info("MatchDetail loaded", [
            'slug' => $this->matchSlug,
            'extracted_id' => $this->matchId,
        ]);
    }

    public function setTab(string $tab): void
    {
        $cleanTab = CricketUrlService::normalizeTab($tab);
        $this->tab = $cleanTab;

        if ($this->seriesSlug && $this->matchSlug) {
            $url = route('match.detail', [
                'seriesSlug' => $this->seriesSlug,
                'tab' => $cleanTab,
                'matchSlug' => $this->matchSlug,
            ]);
            $this->js("window.history.replaceState({}, '', '{$url}')");
        }
    }

    /**
     * Lightweight Livewire action loadTabData: fetches only the clicked tab's data on demand.
     */
    public function loadTabData(string $tab): void
    {
        $cleanTab = CricketUrlService::normalizeTab($tab);
        $this->setTab($cleanTab);
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

    public function render(CricketLiveApiService $apiService)
    {
        // Check if this match is skipped by admin (is_covered = false)
        $managed = ManagedMatch::where('match_id', $this->matchId)->first();
        if ($managed && !$managed->is_covered) {
            abort(404, 'Match not found or coverage skipped.');
        }

        $matchDataLoaded = $this->loadMatchData($apiService);
        $match = $matchDataLoaded['match'];
        $scorecardData = $matchDataLoaded['scorecardData'];
        $commentaryData = $matchDataLoaded['commentaryData'];
        $matchFacts = $matchDataLoaded['matchFacts'];
        $highlights = $matchDataLoaded['highlights'];
        $liveBatters = $matchDataLoaded['liveBatters'];
        $liveBowler = $matchDataLoaded['liveBowler'];
        $liveBowlers = $matchDataLoaded['liveBowlers'] ?? [];
        $pollSeconds = $matchDataLoaded['pollSeconds'];

        // Status determination
        $state = strtolower((string) ($match['state'] ?? $match['status'] ?? 'live'));
        $result = strtolower(trim((string) ($match['result'] ?? '')));
        $completedKeywords = ['finished', 'complete', 'completed', 'won', 'abandoned', 'no result', 'draw', 'tie'];
        
        $isCompletedStatus = in_array($state, ['finished', 'completed', 'complete', '3'], true);
        if (!$isCompletedStatus) {
            foreach ($completedKeywords as $kw) {
                if (str_contains($state, $kw)) {
                    $isCompletedStatus = true;
                    break;
                }
            }
        }

        $isCompletedResult = false;
        if (!empty($result)) {
            foreach ($completedKeywords as $kw) {
                if (str_contains($result, $kw)) {
                    $isCompletedResult = true;
                    break;
                }
            }
            if (!$isCompletedResult && !in_array($state, ['live', 'in progress', 'in_progress', 'innings break', '1', '2'], true)) {
                $isCompletedResult = true;
            }
        }

        $completion = CricketLiveApiService::detectMatchCompletion($match, $scorecardData ?? []);
        $this->isCompleted = $isCompletedStatus || $isCompletedResult || $completion['is_completed'] || CompletedMatchController::isFinishedMatch($match) || $apiService->isFinishedMatch($this->matchId);

        if ($this->isCompleted) {
            $match['is_live'] = false;
            $match['match_status'] = 'Finished';
            $finalLabel = $completion['status_label'] ?: ($match['result'] ?? 'Completed');
            if (preg_match('/^(match in progress|live|ball in progress|in progress)$/i', trim((string) ($match['result'] ?? '')))) {
                $match['result'] = $finalLabel;
            }
            if (empty($match['result'])) {
                $match['result'] = $finalLabel;
            }
        }

        // Pre-Match & Playing XI Archived Lookups (0 Upstream API calls!)
        $preMatchData = $apiService->getPreMatchArchivedData($this->matchId);
        $archivedXI = $apiService->getArchivedPlayingXI($this->matchId);

        if ($managed) {
            if (empty($match['start_time_ist']) && !empty($managed->start_time_ist)) {
                $match['start_time_ist'] = $managed->start_time_ist->timezone('Asia/Kolkata')->format('Y-m-d H:i:s');
            }
            if (!empty($managed->toss_text) && (CricketLiveApiService::isDecidedToss($managed->toss_text) || CricketLiveApiService::isDelayToss($managed->toss_text))) {
                if (CricketLiveApiService::isUndecidedToss($match['toss'] ?? '') || CricketLiveApiService::isDecidedToss($managed->toss_text)) {
                    $match['toss'] = $managed->toss_text;
                }
            }
        }

        if ($preMatchData) {
            if (empty($matchFacts) && !empty($preMatchData['facts'])) {
                $matchFacts = $preMatchData['facts'];
            }
            if (empty($match['venue']) && !empty($preMatchData['venue'])) {
                $match['venue'] = $preMatchData['venue'];
            }
            if (!empty($preMatchData['toss']) && CricketLiveApiService::isUndecidedToss($match['toss'] ?? '') && !CricketLiveApiService::isUndecidedToss($preMatchData['toss'])) {
                $match['toss'] = $preMatchData['toss'];
            }
        }

        // When match is completed, delegate to FinishedMatchController (DB-first JSON, 0 API calls on repeat) and disable commentary
        if ($this->isCompleted) {
            $this->commentaryUnavailable = true;
            $commentaryData = [];

            try {
                $finishedPayload = app(\App\Http\Controllers\Cricket\FinishedMatchController::class)->getOrArchive($this->matchId, $match);
                if (!empty($finishedPayload)) {
                    $match = array_merge($match, $finishedPayload);
                    if (!empty($finishedPayload['scorecard']) && is_array($finishedPayload['scorecard'])) {
                        $scorecardData = $finishedPayload['scorecard'];
                    }
                    if (!empty($finishedPayload['facts']) && is_array($finishedPayload['facts'])) {
                        $matchFacts = array_merge($matchFacts, $finishedPayload['facts']);
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed loading finished match {$this->matchId}: " . $e->getMessage());
            }
        } else {
            // Lazy-load Scorecard: when scorecard tab is active and scorecardData is empty
            if ($this->tab === 'scorecard' && empty($scorecardData)) {
                try {
                    $scorecardRes = $apiService->getScorecard($this->matchId);
                    $scorecardData = $scorecardRes['innings'] ?? $scorecardRes;
                } catch (\Throwable $e) {
                    $scorecardData = [];
                }
            }

            // Ensure commentary is loaded when commentary tab is active OR when live-line lacks recent_balls
            $needsRecentBallSource = ($this->tab === 'live-line')
                && empty($commentaryData)
                && (empty($match['recent_balls']) || !is_array($match['recent_balls']) || !isset($match['recent_balls'][0]['event_type']));
            if (($this->tab === 'commentary' && empty($commentaryData)) || ($needsRecentBallSource && !$apiService->isHardStopActive())) {
                try {
                    $commRes = $apiService->getCommentary($this->matchId);
                    $commentaryData = $commRes['commentary'] ?? $commRes;
                } catch (\Throwable $e) {
                    $commentaryData = [];
                }
            }
        }

        // Lazy-load Squads: ONLY when the squad/playing-xi tab is active
        $squads = [];
        if (in_array($this->tab, ['squad', 'squads', 'playing-xi'], true)) {
            if ($archivedXI && !empty($archivedXI['team_a']['playing_xi'])) {
                $squads = [
                    'team_a' => $archivedXI['team_a'],
                    'team_b' => $archivedXI['team_b'],
                ];
            } elseif ($preMatchData && !empty($preMatchData['squads'])) {
                $squads = $preMatchData['squads'];
            } else {
                try {
                    $squads = $apiService->getSquads($this->matchId);
                } catch (\Throwable $e) {
                    $squads = [];
                }
            }
        }

        // Load Match Facts when Info tab is active or when toss is still undecided (short-cached in 30m window)
        $needsInfoFacts = ($this->tab === 'info') && (empty($matchFacts) || (!$this->isCompleted && CricketLiveApiService::isUndecidedToss($match['toss'] ?? $matchFacts['toss'] ?? '')));
        if ($needsInfoFacts) {
            try {
                $fetchedFacts = $apiService->getMatchFacts($this->matchId, $match);
                if (!empty($fetchedFacts)) {
                    $matchFacts = array_merge($matchFacts, $fetchedFacts);
                }
            } catch (\Throwable $e) {
                // Keep existing facts on failure
            }
        }

        // Resolve toss state (checks 30m IST pre-match window & short-cached match-facts automatically)
        $tossState = $apiService->resolveTossDisplayState($match, $matchFacts);
        if (empty($matchFacts)) {
            $cachedFacts = Cache::get("cricket:facts:{$this->matchId}");
            if (is_array($cachedFacts) && !empty($cachedFacts)) {
                $matchFacts = $cachedFacts;
            }
        }

        // Merge resolved facts back into $match so Info tab and Hero Box never lose toss/venue/officials
        if (!empty($matchFacts)) {
            $match['facts'] = $matchFacts;
            if (empty($match['venue']) && !empty($matchFacts['venue'])) {
                $match['venue'] = $matchFacts['venue'];
            }
            if (empty($match['umpire']) && !empty($matchFacts['umpires'])) {
                $match['umpire'] = is_array($matchFacts['umpires'])
                    ? implode(', ', array_filter($matchFacts['umpires']))
                    : (string) $matchFacts['umpires'];
                $match['umpires'] = $matchFacts['umpires'];
            }
            if (empty($match['referee']) && !empty($matchFacts['referee'])) {
                $match['referee'] = $matchFacts['referee'];
            }
            if (!isset($match['day_night']) && isset($matchFacts['day_night'])) {
                $match['day_night'] = (bool) $matchFacts['day_night'];
            }
            if (empty($match['series']) && !empty($matchFacts['series'])) {
                $match['series'] = $matchFacts['series'];
            }
        }
        if (in_array($tossState['toss_status'], ['decided', 'delayed'], true)) {
            $match['toss'] = $tossState['toss_text'];
        }
        $match['show_toss_banner'] = $tossState['show_toss_banner'];
        $match['toss_status'] = $tossState['toss_status'];
        $match['toss_text'] = $tossState['toss_text'];

        // Lazy-load Highlights: ONLY when the highlights tab is active
        if ($this->tab === 'highlights') {
            if (empty($highlights) && !empty($match['highlights']) && is_array($match['highlights'])) {
                $highlights = CricketLiveApiService::normalizeHighlights($match['highlights']);
            }
            if (empty($highlights)) {
                try {
                    $highlights = $apiService->getHighlights($this->matchId);
                } catch (\Throwable $e) {
                    $highlights = [];
                }
            }
        }

        // Lazy-load Commentary: ONLY when commentary tab is explicitly active and match is NOT completed
        if ($this->tab === 'commentary' && empty($commentaryData) && !$this->isCompleted) {
            try {
                $commRes = $apiService->getCommentary($this->matchId);
                $commentaryData = $commRes['commentary'] ?? [];
            } catch (\Throwable $e) {
                $commentaryData = [];
            }
        }

        // Over History & Recent Balls normalization
        $recentOvers = [];
        $recentBalls = [];
        try {
            $recentSource = !empty($commentaryData)
                ? $commentaryData
                : (is_array($match['recent_balls'] ?? null) ? $match['recent_balls'] : []);
            $overHistorySource = is_array($match['over_history'] ?? $match['overHistory'] ?? $match['recent_overs'] ?? null)
                ? ($match['over_history'] ?? $match['overHistory'] ?? $match['recent_overs'])
                : [];

            // Fallback to local static JSON cache if both commentary and recent_balls are empty
            if (empty($recentSource) && empty($overHistorySource)) {
                $staticFile = public_path("live-data/match-{$this->matchId}.json");
                if (file_exists($staticFile)) {
                    $staticJson = json_decode(@file_get_contents($staticFile), true);
                    if (is_array($staticJson)) {
                        $recentSource = !empty($staticJson['commentary']) ? $staticJson['commentary'] : ($staticJson['recent_balls'] ?? []);
                    }
                }
            }

            $recentBalls = $apiService->extractRecentBalls($recentSource, $overHistorySource, 18);
            $this->recentBalls = $recentBalls;
            $match['recent_balls'] = $recentBalls;
            $recentOvers = $apiService->getNormalizedRecentOvers($this->matchId, !empty($commentaryData) ? $commentaryData : $recentBalls);
        } catch (\Throwable $e) {
            $recentOvers = [];
            $recentBalls = [];
            $this->recentBalls = [];
        }

        $hasScores = !empty($match['score_a']) || !empty($match['score_b']) || !empty($match['score']) || !empty($match['team_a_scores']) || !empty($match['team_b_scores']);
        $isLive = !$this->isCompleted && (in_array($state, ['live', 'in progress', 'in_progress', 'innings break', '1', '2']) || ($hasScores && empty($result)));
        $isFinished = $this->isCompleted;
        $isUpcoming = !$isLive && !$isFinished;
        $pollSeconds = $isLive ? $pollSeconds : ($tossState['show_toss_banner'] ? 15 : 0);

        // Team names & polls
        $teamAName = $match['team_a'] ?? 'Team A';
        $teamBName = $match['team_b'] ?? 'Team B';
        $polls = $this->getOrCreateMatchPolls($teamAName, $teamBName);

        // Featured Articles & Sidebar Ads
        $featuredArticles = NewsArticle::where('is_published', true)
            ->where('is_featured', true)
            ->latest()
            ->take(3)
            ->get();

        $sidebarBanner = \App\Models\AdBanner::activeSlot('match_sidebar')->first()
            ?? \App\Models\AdBanner::activeSlot('desktop_right')->first()
            ?? \App\Models\AdBanner::activeSlot('sidebar')->first();

        $socialLinks = \App\Support\LiveEngineConfig::getActiveSocialLinks();

        // SEO
        $seoData = class_exists(\App\Services\Seo\CricketSeoService::class)
            ? \App\Services\Seo\CricketSeoService::forMatch($match, $this->tab, $commentaryData)
            : [
                'title' => "{$teamAName} vs {$teamBName} Live Cricket Score | CricWire",
                'description' => "Get {$teamAName} vs {$teamBName} live score, ball-by-ball commentary, and scorecard.",
            ];

        return view('livewire.pages.match-detail', [
            'match' => $match,
            'matchId' => $this->matchId,
            'tab' => $this->tab,
            'liveInfo' => $match,
            'liveLine' => $match,
            'liveBatters' => $liveBatters,
            'liveBowler' => $liveBowler,
            'liveBowlers' => $liveBowlers,
            'pollSeconds' => $pollSeconds,
            'scorecardData' => $scorecardData,
            'commentaryData' => $commentaryData,
            'highlights' => $highlights,
            'matchFacts' => $matchFacts,
            'tossState' => $tossState,
            'squads' => $squads,
            'seriesSlug' => $this->seriesSlug,
            'matchSlug' => $this->matchSlug,
            'teamASquad' => $squads['team_a']['playing_xi'] ?? [],
            'teamBSquad' => $squads['team_b']['playing_xi'] ?? [],
            'matchStartTime' => $match['start_time'] ?? null,
            'isUpcoming' => $isUpcoming,
            'isLive' => $isLive,
            'isFinished' => $isFinished,
            'isCompleted' => $this->isCompleted,
            'commentaryUnavailable' => $this->commentaryUnavailable,
            'polls' => $polls,
            'featuredArticles' => $featuredArticles,
            'sidebarBanner' => $sidebarBanner,
            'socialLinks' => $socialLinks,
            'seoData' => $seoData,
            'faqs' => $seoData['faqs'] ?? [],
            'recentOvers' => $recentOvers,
            'recentBalls' => $recentBalls,
            'preMatchData' => $preMatchData,
            'archivedXI' => $archivedXI,
            'managedMatch' => $managed,
        ])->layout('components.layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'],
        ]);
    }

    /**
     * Load match data directly via Fast Real-Time Live Cricket Data backed strictly by Redis.
     */
    protected function loadMatchData(CricketLiveApiService $apiService): array
    {
        $payload = $apiService->getFastMatchData($this->matchId);

        $format = strtoupper($payload['format'] ?? 'T20');
        $pollSeconds = match ($format) {
            'T20' => \App\Support\LiveEngineConfig::pollIntervalT20(),
            'TEST' => \App\Support\LiveEngineConfig::pollIntervalTest(),
            default => \App\Support\LiveEngineConfig::pollIntervalOdi(),
        };

        $liveBatters = $payload['batters'] ?? $payload['live_batters'] ?? $payload['batsman'] ?? [];
        
        // Standardize bowler normalization: extract current bowler and previous bowler
        $rawBowlers = $payload['bowler'] ?? $payload['live_bowlers'] ?? $payload['live_bowler'] ?? [];
        $liveBowlers = [];
        if (is_array($rawBowlers)) {
            if (isset($rawBowlers['name'])) {
                $rawBowlers = [$rawBowlers];
            }
            foreach ($rawBowlers as $idx => $b) {
                if (is_array($b) && !empty($b['name']) && $b['name'] !== '-') {
                    $liveBowlers[] = [
                        'name' => trim($b['name']),
                        'overs' => \App\Helpers\CricketMatchFormatter::normalizeOvers($b['overs'] ?? $b['over'] ?? '0.0'),
                        'over' => \App\Helpers\CricketMatchFormatter::normalizeOvers($b['overs'] ?? $b['over'] ?? '0.0'),
                        'maidens' => (int) ($b['maidens'] ?? $b['maiden'] ?? 0),
                        'maiden' => (int) ($b['maidens'] ?? $b['maiden'] ?? 0),
                        'runs' => (int) ($b['runs'] ?? $b['run'] ?? 0),
                        'run' => (int) ($b['runs'] ?? $b['run'] ?? 0),
                        'wickets' => (int) ($b['wickets'] ?? $b['wicket'] ?? 0),
                        'wicket' => (int) ($b['wickets'] ?? $b['wicket'] ?? 0),
                        'economy' => (string) ($b['economy'] ?? '0.00'),
                        'is_current' => $idx === 0,
                    ];
                }
            }
        }
        $liveBowler = $liveBowlers[0] ?? [];

        $match = array_merge($payload, [
            'poll_interval_seconds' => $pollSeconds,
            'poll_ms' => $pollSeconds * 1000,
        ]);

        return [
            'match' => $match,
            'scorecardData' => $payload['scorecard'] ?? [],
            'commentaryData' => $payload['commentary'] ?? [],
            'matchFacts' => $payload['facts'] ?? [],
            'highlights' => CricketLiveApiService::normalizeHighlights(is_array($payload['highlights'] ?? null) ? $payload['highlights'] : []),
            'liveBatters' => $liveBatters,
            'liveBowler' => $liveBowler,
            'liveBowlers' => $liveBowlers,
            'pollSeconds' => $pollSeconds,
        ];
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
