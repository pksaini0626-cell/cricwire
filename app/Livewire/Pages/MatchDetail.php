<?php

namespace App\Livewire\Pages;

use App\Helpers\CricketSlugHelper;
use App\Http\Controllers\Cricket\SessionController;
use App\Models\ManagedMatch;
use App\Models\NewsArticle;
use App\Models\Poll;
use App\Models\PollVote;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Cricket\CricketUrlService;
use App\Services\Cricket\StaticJsonCacheWriter;
use Illuminate\Support\Facades\Request;
use Livewire\Component;

class MatchDetail extends Component
{
    public string|int $matchId;
    public string $tab = 'live-line'; // Options: 'live-line', 'commentary', 'scorecard', 'squad', 'odds', 'info', 'highlights'
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

    public function render(CricketLiveApiService $apiService)
    {
        // Check if this match is skipped by admin (is_covered = false)
        $managed = ManagedMatch::where('match_id', $this->matchId)->first();
        if ($managed && !$managed->is_covered) {
            abort(404, 'Match not found or coverage skipped.');
        }

        // 1. First attempt to read match data directly from public/live-data/match-{$matchId}.json
        $staticMatch = StaticJsonCacheWriter::read("match-{$this->matchId}.json");
        $staticHome = StaticJsonCacheWriter::read('home.json');

        // Look for baseline match object from public/live-data/home.json
        $baseMatch = null;
        if ($staticHome && is_array($staticHome)) {
            $allHome = array_merge(
                $staticHome['live'] ?? [],
                $staticHome['upcoming'] ?? [],
                $staticHome['recent'] ?? [],
                $staticHome['finished'] ?? []
            );
            foreach ($allHome as $m) {
                if ((string) ($m['match_id'] ?? $m['id'] ?? '') === (string) $this->matchId) {
                    $baseMatch = $m;
                    break;
                }
            }
        }

        // Check if static match file exists and is fresh (< 60 seconds old)
        $matchFilePath = StaticJsonCacheWriter::getStorageDir() . DIRECTORY_SEPARATOR . "match-{$this->matchId}.json";
        $isStaticFresh = file_exists($matchFilePath) && (time() - filemtime($matchFilePath)) < 60;

        $matchData = $baseMatch ?? [];
        $commentaryData = [];
        $scorecardData = [];
        $highlights = [];
        $matchFacts = [];

        if ($staticMatch && is_array($staticMatch) && $isStaticFresh) {
            $matchData = array_merge($matchData, $staticMatch);
            $commentaryData = $staticMatch['commentary'] ?? [];
            $scorecardData = $staticMatch['scorecard'] ?? [];
            $highlights = $staticMatch['highlights'] ?? [];
            $matchFacts = $staticMatch['facts'] ?? [];
        } else {
            // Static file is missing or stale (> 60s) -> Fetch from Live API endpoints with short 15s cache
            try {
                $cacheKeyPrefix = "cricket:match_detail:{$this->matchId}";
                $commRes = \Illuminate\Support\Facades\Cache::remember("{$cacheKeyPrefix}:comm", 15, fn () => $apiService->getCommentary($this->matchId));
                $scRes = \Illuminate\Support\Facades\Cache::remember("{$cacheKeyPrefix}:sc", 15, fn () => $apiService->getScorecard($this->matchId));
                $factsRes = \Illuminate\Support\Facades\Cache::remember("{$cacheKeyPrefix}:facts", 15, fn () => $apiService->getMatchFacts($this->matchId));
                $highRes = \Illuminate\Support\Facades\Cache::remember("{$cacheKeyPrefix}:highlights", 60, fn () => $apiService->getHighlights($this->matchId));

                $commentaryData = $commRes['commentary'] ?? [];
                $scorecardData = $scRes['innings'] ?? [];
                $matchFacts = $factsRes ?? [];
                $highlights = $highRes ?? [];

                // Fallback to upstream matches if baseMatch wasn't in home.json
                if (empty($baseMatch)) {
                    $upstreamLive = $apiService->getLiveMatches();
                    $upstreamRecent = $apiService->getRecentMatches();
                    $upstreamUpcoming = $apiService->getUpcomingMatches();
                    $allUpstream = array_merge($upstreamLive, $upstreamRecent, $upstreamUpcoming);
                    foreach ($allUpstream as $m) {
                        if ((string) ($m['match_id'] ?? $m['id'] ?? '') === (string) $this->matchId) {
                            $baseMatch = $m;
                            $matchData = array_merge($matchData, $baseMatch);
                            break;
                        }
                    }
                }

                // If static match previously existed, merge its non-empty fields
                if ($staticMatch && is_array($staticMatch)) {
                    $matchData = array_merge($matchData, array_filter($staticMatch, fn ($v) => $v !== null && $v !== ''));
                }

                // Build and write updated public/live-data/match-{$matchId}.json via StaticJsonCacheWriter
                $format = strtoupper($matchData['format'] ?? 'ODI');
                $pollIntervalSec = match ($format) {
                    'T20' => \App\Support\LiveEngineConfig::pollIntervalT20(),
                    'TEST' => \App\Support\LiveEngineConfig::pollIntervalTest(),
                    default => \App\Support\LiveEngineConfig::pollIntervalOdi(),
                };

                $writePayload = array_merge($matchData, [
                    'match_id' => (int) $this->matchId,
                    'series_name' => $matchData['series_name'] ?? ($matchFacts['series'] ?? ''),
                    'match_desc' => $matchData['match_desc'] ?? '',
                    'format' => $format,
                    'poll_interval_seconds' => $pollIntervalSec,
                    'poll_ms' => $pollIntervalSec * 1000,
                    'state' => $matchData['state'] ?? 'Live',
                    'score' => $matchData['score'] ?? '',
                    'team_a' => $matchData['team_a'] ?? 'Team A',
                    'team_b' => $matchData['team_b'] ?? 'Team B',
                    'team_a_short' => $matchData['team_a_short'] ?? '',
                    'team_b_short' => $matchData['team_b_short'] ?? '',
                    'team_a_img' => $matchData['team_a_img'] ?? '',
                    'team_b_img' => $matchData['team_b_img'] ?? '',
                    'team_a_scores' => $matchData['team_a_scores'] ?? $matchData['score_a'] ?? '',
                    'team_b_scores' => $matchData['team_b_scores'] ?? $matchData['score_b'] ?? '',
                    'score_a' => $matchData['score_a'] ?? $matchData['team_a_scores'] ?? '',
                    'score_b' => $matchData['score_b'] ?? $matchData['team_b_scores'] ?? '',
                    'team_a_score' => $matchData['team_a_scores'] ?? $matchData['score_a'] ?? '',
                    'team_b_score' => $matchData['team_b_scores'] ?? $matchData['score_b'] ?? '',
                    'status' => $matchData['status'] ?? 'Live',
                    'result' => $matchData['result'] ?? '',
                    'latest_ball' => !empty($commentaryData) ? $commentaryData[0] : null,
                    'recent_balls' => array_slice($commentaryData, 0, 12),
                    'commentary' => $commentaryData,
                    'scorecard' => $scorecardData,
                    'highlights' => $highlights,
                    'facts' => $matchFacts,
                    'updated_at' => now()->toIso8601String(),
                    'timestamp' => time(),
                ]);

                StaticJsonCacheWriter::write("match-{$this->matchId}.json", $writePayload);
                $matchData = $writePayload;
            } catch (\Throwable $e) {
                // If API call fails, fall back gracefully to baseMatch or staticMatch
                if ($staticMatch && is_array($staticMatch)) {
                    $matchData = array_merge($matchData, $staticMatch);
                    $commentaryData = $commentaryData ?: ($staticMatch['commentary'] ?? []);
                    $scorecardData = $scorecardData ?: ($staticMatch['scorecard'] ?? []);
                    $highlights = $highlights ?: ($staticMatch['highlights'] ?? []);
                    $matchFacts = $matchFacts ?: ($staticMatch['facts'] ?? []);
                }
            }
        }

        // 3. Extract & Compute Innings Scores from Scorecard / Live Data
        $scoreA = $matchData['score_a'] ?? $matchData['team_a_scores'] ?? $matchData['team_a_score'] ?? ($baseMatch['score_a'] ?? $baseMatch['team_a_scores'] ?? '');
        $scoreB = $matchData['score_b'] ?? $matchData['team_b_scores'] ?? $matchData['team_b_score'] ?? ($baseMatch['score_b'] ?? $baseMatch['team_b_scores'] ?? '');
        $scoreCombined = $matchData['score'] ?? ($baseMatch['score'] ?? '');

        // If scorecard data is available, compute and match scores directly from innings
        if (!empty($scorecardData) && is_array($scorecardData)) {
            $teamAName = strtolower(trim((string) ($matchData['team_a'] ?? $baseMatch['team_a'] ?? '')));
            $teamBName = strtolower(trim((string) ($matchData['team_b'] ?? $baseMatch['team_b'] ?? '')));
            $teamAShort = strtolower(trim((string) ($matchData['team_a_short'] ?? $baseMatch['team_a_short'] ?? '')));
            $teamBShort = strtolower(trim((string) ($matchData['team_b_short'] ?? $baseMatch['team_b_short'] ?? '')));

            foreach ($scorecardData as $inn) {
                if (!is_array($inn)) continue;

                // Extract team name and score string
                $inTeam = $inn['bat_team'] ?? $inn['team']['name'] ?? $inn['team']['short_name'] ?? $inn['team_name'] ?? '';
                $inTeamShort = $inn['bat_team_short'] ?? $inn['team']['short_name'] ?? '';
                $inScore = trim((string) ($inn['score'] ?? ''));

                if (empty($inScore) && isset($inn['team']['score'])) {
                    $runs = $inn['team']['score'] ?? 0;
                    $wkts = $inn['team']['wicket'] ?? 0;
                    $ovs = $inn['team']['over'] ?? '';
                    $inScore = "{$runs}/{$wkts}" . ($ovs ? " ({$ovs} ov)" : '');
                }

                if (!empty($inScore)) {
                    $inTeamLower = strtolower(trim((string) $inTeam));
                    $inTeamShortLower = strtolower(trim((string) $inTeamShort));

                    $isTeamA = ($inTeamLower && ($inTeamLower === $teamAName || str_contains($teamAName, $inTeamLower) || str_contains($inTeamLower, $teamAName)))
                        || ($inTeamShortLower && ($inTeamShortLower === $teamAShort || str_contains($teamAShort, $inTeamShortLower)));

                    $isTeamB = ($inTeamLower && ($inTeamLower === $teamBName || str_contains($teamBName, $inTeamLower) || str_contains($inTeamLower, $teamBName)))
                        || ($inTeamShortLower && ($inTeamShortLower === $teamBShort || str_contains($teamBShort, $inTeamShortLower)));

                    if ($isTeamA && empty($scoreA)) {
                        $scoreA = $inScore;
                    } elseif ($isTeamB && empty($scoreB)) {
                        $scoreB = $inScore;
                    }
                }
            }
        }

        // If still empty, fallback to combined score
        if (empty($scoreA) && empty($scoreB) && !empty($scoreCombined)) {
            $scoreA = $scoreCombined;
        }
        if (empty($scoreCombined)) {
            $scoreCombined = $scoreA ?: $scoreB;
        }

        // Populate ALL score aliases identically on $match
        $match = array_merge([
            'match_id' => $this->matchId,
            'team_a' => $baseMatch['team_a'] ?? 'Team A',
            'team_b' => $baseMatch['team_b'] ?? 'Team B',
            'team_a_short' => $baseMatch['team_a_short'] ?? 'TMA',
            'team_b_short' => $baseMatch['team_b_short'] ?? 'TMB',
            'team_a_img' => $baseMatch['team_a_img'] ?? '',
            'team_b_img' => $baseMatch['team_b_img'] ?? '',
            'series_name' => $baseMatch['series_name'] ?? 'Cricket Series',
            'series' => $baseMatch['series'] ?? ($baseMatch['series_name'] ?? 'Cricket Series'),
            'match_desc' => $baseMatch['match_desc'] ?? 'Match',
            'matchs' => $baseMatch['matchs'] ?? ($baseMatch['match_desc'] ?? 'Match'),
            'format' => $baseMatch['format'] ?? 'ODI',
            'state' => $baseMatch['state'] ?? 'Live',
            'result' => $baseMatch['result'] ?? '',
        ], $matchData, [
            'score' => $scoreCombined,
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'team_a_scores' => $scoreA,
            'team_b_scores' => $scoreB,
            'team_a_score' => $scoreA,
            'team_b_score' => $scoreB,
        ]);

        // 4. STEP 2: Extract Active Batters, Current Bowler & Ball-by-Ball Mapping
        $liveBatters = [];
        $liveBowler = [];

        // Current Batters on Crease
        if (!empty($scorecardData) && is_array($scorecardData)) {
            $activeInning = end($scorecardData);
            if (is_array($activeInning)) {
                $allBatsmen = $activeInning['batsmen'] ?? $activeInning['batsman'] ?? [];
                foreach ($allBatsmen as $b) {
                    $outDesc = strtolower(trim((string) ($b['out_desc'] ?? $b['out_by'] ?? '')));
                    if ($outDesc === 'batting' || $outDesc === 'not out' || $outDesc === '' || str_contains($outDesc, 'not out')) {
                        $liveBatters[] = [
                            'name' => $b['name'] ?? $b['short_name'] ?? 'Batter',
                            'runs' => $b['runs'] ?? $b['run'] ?? 0,
                            'run' => $b['runs'] ?? $b['run'] ?? 0,
                            'balls' => $b['balls'] ?? $b['ball'] ?? 0,
                            'ball' => $b['balls'] ?? $b['ball'] ?? 0,
                            'fours' => $b['fours'] ?? 0,
                            'sixes' => $b['sixes'] ?? 0,
                            'strike_rate' => $b['strike_rate'] ?? '0.00',
                            'is_striker' => (bool) ($b['is_striker'] ?? false),
                        ];
                    }
                }
            }
        }

        // Fallback / check active batters from commentary miniscore if present
        if (empty($liveBatters) && !empty($matchData['latest_ball']['batsman'])) {
            $liveBatters[] = [
                'name' => $matchData['latest_ball']['batsman'],
                'runs' => 0,
                'run' => 0,
                'balls' => 0,
                'ball' => 0,
                'fours' => 0,
                'sixes' => 0,
                'strike_rate' => '0.00',
                'is_striker' => true,
            ];
        }

        // Current Bowler
        if (!empty($scorecardData) && is_array($scorecardData)) {
            $activeInning = end($scorecardData);
            if (is_array($activeInning)) {
                $allBowlers = $activeInning['bowlers'] ?? $activeInning['bolwer'] ?? $activeInning['bowler'] ?? [];
                // Sort or pick bowler with recent overs
                if (!empty($allBowlers)) {
                    $lastB = end($allBowlers);
                    $liveBowler = [
                        'name' => $lastB['name'] ?? $lastB['short_name'] ?? 'Bowler',
                        'overs' => $lastB['overs'] ?? $lastB['over'] ?? '0.0',
                        'over' => $lastB['overs'] ?? $lastB['over'] ?? '0.0',
                        'maidens' => $lastB['maidens'] ?? $lastB['maiden'] ?? 0,
                        'maiden' => $lastB['maidens'] ?? $lastB['maiden'] ?? 0,
                        'runs' => $lastB['runs'] ?? $lastB['run'] ?? 0,
                        'run' => $lastB['runs'] ?? $lastB['run'] ?? 0,
                        'wickets' => $lastB['wickets'] ?? $lastB['wicket'] ?? 0,
                        'wicket' => $lastB['wickets'] ?? $lastB['wicket'] ?? 0,
                        'economy' => $lastB['economy'] ?? '0.00',
                    ];
                }
            }
        }

        if (empty($liveBowler) && !empty($matchData['latest_ball']['bowler'])) {
            $liveBowler = [
                'name' => $matchData['latest_ball']['bowler'],
                'overs' => '0.0',
                'over' => '0.0',
                'maidens' => 0,
                'maiden' => 0,
                'runs' => 0,
                'run' => 0,
                'wickets' => 0,
                'wicket' => 0,
                'economy' => '0.00',
            ];
        }

        // Attach live batters & bowler to match array
        $match['live_batters'] = $liveBatters;
        $match['live_bowler'] = $liveBowler;

        // Format and poll interval calculation
        $format = strtoupper($match['format'] ?? 'ODI');
        $pollSeconds = match ($format) {
            'T20' => \App\Support\LiveEngineConfig::pollIntervalT20(),
            'TEST' => \App\Support\LiveEngineConfig::pollIntervalTest(),
            default => \App\Support\LiveEngineConfig::pollIntervalOdi(),
        };

        // Squads
        $squads = [];
        if ($this->tab === 'squad') {
            try {
                $squads = $apiService->getSquads($this->matchId);
            } catch (\Throwable $e) {
                $squads = [];
            }
        }

        // Live Batters and Current Bowler
        $liveBatters = $match['live_batters'] ?? [];
        $liveBowler = $match['live_bowler'] ?? [];

        // Status determination
        $state = strtolower($match['state'] ?? $match['status'] ?? 'live');
        $result = $match['result'] ?? '';
        $isLive = in_array($state, ['live', 'in progress', 'innings break', '1', '2']);
        $isFinished = !empty($result) || in_array($state, ['finished', 'completed', 'complete', '3']);
        $isUpcoming = !$isLive && !$isFinished;

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
            'pollSeconds' => $pollSeconds,
            'scorecardData' => $scorecardData,
            'commentaryData' => $commentaryData,
            'highlights' => $highlights,
            'matchFacts' => $matchFacts,
            'squads' => $squads,
            'seriesSlug' => $this->seriesSlug,
            'matchSlug' => $this->matchSlug,
            'teamASquad' => $squads['team_a']['playing_xi'] ?? [],
            'teamBSquad' => $squads['team_b']['playing_xi'] ?? [],
            'matchStartTime' => $match['start_time'] ?? null,
            'isUpcoming' => $isUpcoming,
            'isLive' => $isLive,
            'isFinished' => $isFinished,
            'polls' => $polls,
            'featuredArticles' => $featuredArticles,
            'sidebarBanner' => $sidebarBanner,
            'socialLinks' => $socialLinks,
            'seoData' => $seoData,
            'faqs' => $seoData['faqs'] ?? [],
        ])->layout('components.layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'],
        ]);
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
