<?php

namespace App\Services\Cricket;

use App\Models\CompletedMatchScorecard;
use App\Models\ManagedMatch;
use App\Models\SkippedSeries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CricketLiveApiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.cricketliveapi.base_url', 'https://cricketliveapi.com/api/v1'), '/');
        $this->token = (string) config('services.cricketliveapi.token', '');
    }

    /**
     * Track and increment daily API call counter.
     */
    protected function recordApiCall(): int
    {
        $cacheKey = 'cricketliveapi:calls:' . now()->format('Y-m-d');
        // TTL 2 days
        return Cache::increment($cacheKey);
    }

    /**
     * Get current day call count.
     */
    public function getDailyCallCount(): int
    {
        $cacheKey = 'cricketliveapi:calls:' . now()->format('Y-m-d');
        return (int) Cache::get($cacheKey, 0);
    }

    /**
     * Check if Eco Mode is active (calls >= 80% of daily quota limit 5000 = 4000).
     */
    public function isEcoModeActive(): bool
    {
        return $this->getDailyCallCount() >= 4000;
    }

    /**
     * Check if Hard Stop is active (calls >= 96% of daily quota limit = 4800).
     */
    public function isHardStopActive(): bool
    {
        return $this->getDailyCallCount() >= 4800;
    }

    /**
     * Check if a series is skipped using cached lookup.
     */
    public function isSeriesSkipped(array|string $matchOrSeries, string|int|null $seriesId = null): bool
    {
        $lookup = SkippedSeries::getSkippedLookup();

        $sId = (string) ($seriesId ?? (is_array($matchOrSeries) ? ($matchOrSeries['series_id'] ?? $matchOrSeries['seriesId'] ?? '') : ''));
        $sName = is_array($matchOrSeries) ? ($matchOrSeries['series_name'] ?? $matchOrSeries['series'] ?? '') : $matchOrSeries;
        $sSlug = Str::slug((string) $sName);

        if ($sId !== '' && in_array($sId, $lookup['ids'], true)) {
            return true;
        }

        if ($sSlug !== '' && in_array($sSlug, $lookup['slugs'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Filter allowed matches based on series skip list and blocked individual matches.
     */
    public function filterAllowedMatches(array $matches): array
    {
        if (empty($matches)) {
            return [];
        }

        $blockedMatchIds = Cache::remember('cricket:managed_matches:blocked_ids', 300, function () {
            return ManagedMatch::where('is_covered', false)
                ->pluck('match_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        });

        $blockedLookup = !empty($blockedMatchIds) ? array_flip($blockedMatchIds) : [];

        $filtered = [];
        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $mId = (int) ($match['match_id'] ?? $match['id'] ?? 0);
            if ($mId > 0 && isset($blockedLookup[$mId])) {
                continue;
            }

            if ($this->isSeriesSkipped($match)) {
                continue;
            }

            $filtered[] = $match;
        }

        return array_values($filtered);
    }

    /**
     * Execute an HTTP GET request against cricketliveapi with timeout and retry.
     */
    protected function get(string $endpoint, array $queryParams = []): ?array
    {
        $this->recordApiCall();

        try {
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
            $response = Http::withToken($this->token)
                ->timeout(8)
                ->retry(2, 150)
                ->get($url, $queryParams);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("CricketLiveApi HTTP failure on {$endpoint}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error("CricketLiveApi connection exception on {$endpoint}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Generate smart short code for a team name.
     * e.g., for multi-word names like "New Zealand" or "South Africa" take initials (NZ, SA).
     * for single-word names like "India" or "Australia" take uppercase first 3 letters (IND, AUS).
     * NEVER output TMA or TMB!
     */
    public static function generateTeamShort(string $name, ?string $fallback = null): string
    {
        $name = trim($name);
        if ($name === '' || strtoupper($name) === 'TMA' || strtoupper($name) === 'TMB' || strtoupper($name) === 'TEAM A' || strtoupper($name) === 'TEAM B') {
            return $fallback && $fallback !== 'TMA' && $fallback !== 'TMB' ? $fallback : 'TBD';
        }

        // Clean punctuation
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
        $words = preg_split('/\s+/', trim($clean));

        if (count($words) >= 2) {
            $initials = '';
            foreach ($words as $w) {
                if (!empty($w)) {
                    $initials .= mb_substr($w, 0, 1);
                }
            }
            if (mb_strlen($initials) >= 2) {
                return strtoupper(mb_substr($initials, 0, 4));
            }
        }

        return strtoupper(mb_substr($name, 0, 3));
    }

    /**
     * Helper to detect cricket format from string.
     */
    protected function normalizeFormat(?string $format, ?string $desc = ''): string
    {
        $text = strtoupper(($format ?? '') . ' ' . ($desc ?? ''));
        if (str_contains($text, 'T20') || str_contains($text, 'T10') || str_contains($text, 'TWENTY20') || str_contains($text, 'THE HUNDRED') || str_contains($text, '100')) {
            return 'T20';
        }
        if (str_contains($text, 'TEST') || str_contains($text, 'FIRST-CLASS') || str_contains($text, 'FC')) {
            return 'Test';
        }
        return 'ODI';
    }

    /**
     * Universal Match Payload Normalizer
     * Handles nested first_team/second_team structures from cricketliveapi.com,
     * smart team short codes (never TMA/TMB), clean scores, and standardized keys.
     */
    public function normalizeMatchPayload(array $m, string $defaultState = 'Scheduled'): array
    {
        $matchId = (int) ($m['match_id'] ?? $m['id'] ?? 0);
        $series = trim((string) ($m['series_name'] ?? $m['series'] ?? $m['tournament'] ?? 'Cricket Series'));
        $matchDesc = trim((string) ($m['match_desc'] ?? $m['description'] ?? $m['title'] ?? $m['matchs'] ?? 'Match'));
        $format = $this->normalizeFormat($m['format'] ?? null, $matchDesc . ' ' . ($m['match_format'] ?? ''));

        // Extract first_team and second_team objects if present
        $firstTeamObj = is_array($m['first_team'] ?? null) ? $m['first_team'] : [];
        $secondTeamObj = is_array($m['second_team'] ?? null) ? $m['second_team'] : [];

        // Team A (Name, Short, Img, Score)
        $teamAName = trim((string) (
            $m['team_a'] ?? 
            $firstTeamObj['full_name'] ?? 
            $firstTeamObj['name'] ?? 
            $m['team1'] ?? 
            $m['team_a_name'] ?? 
            'Team A'
        ));
        $teamAShort = trim((string) (
            $firstTeamObj['name'] ?? 
            $firstTeamObj['short_name'] ?? 
            $m['team_a_short'] ?? 
            $m['team1_short'] ?? 
            ''
        ));
        $teamAShort = self::generateTeamShort($teamAName, $teamAShort);

        $teamAImg = $firstTeamObj['team_image_url'] ?? $m['team_a_img'] ?? $m['team1_img'] ?? $m['team_a_flag'] ?? '';

        // Team B (Name, Short, Img, Score)
        $teamBName = trim((string) (
            $m['team_b'] ?? 
            $secondTeamObj['full_name'] ?? 
            $secondTeamObj['name'] ?? 
            $m['team2'] ?? 
            $m['team_b_name'] ?? 
            'Team B'
        ));
        $teamBShort = trim((string) (
            $secondTeamObj['name'] ?? 
            $secondTeamObj['short_name'] ?? 
            $m['team_b_short'] ?? 
            $m['team2_short'] ?? 
            ''
        ));
        $teamBShort = self::generateTeamShort($teamBName, $teamBShort);

        $teamBImg = $secondTeamObj['team_image_url'] ?? $m['team_b_img'] ?? $m['team2_img'] ?? $m['team_b_flag'] ?? '';

        // Scores normalization
        $scoreA = trim((string) (
            $firstTeamObj['score'] ?? 
            $m['score_a'] ?? 
            $m['team_a_scores'] ?? 
            $m['team1_score'] ?? 
            $m['score'] ?? 
            ''
        ));
        $scoreB = trim((string) (
            $secondTeamObj['score'] ?? 
            $m['score_b'] ?? 
            $m['team_b_scores'] ?? 
            $m['team2_score'] ?? 
            ''
        ));

        // State & Result
        $rawState = $m['state'] ?? $m['status'] ?? $defaultState;
        $statusDetail = $m['status_detail'] ?? $m['status_note'] ?? $m['status'] ?? '';
        $result = $m['result'] ?? ($defaultState === 'Complete' ? $statusDetail : '');

        return [
            'match_id' => $matchId,
            'id' => $matchId,
            'series_name' => $series,
            'series' => $series,
            'match_desc' => $matchDesc,
            'matchs' => $matchDesc,
            'format' => $format,
            'match_type' => $format,
            'state' => $defaultState,
            'team_a' => $teamAName,
            'team_b' => $teamBName,
            'team_a_short' => $teamAShort,
            'team_b_short' => $teamBShort,
            'team_a_img' => $teamAImg,
            'team_b_img' => $teamBImg,
            'score' => $m['score'] ?? $scoreA,
            'team_a_scores' => $scoreA,
            'team_b_scores' => $scoreB,
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'team_a_over' => $m['team_a_over'] ?? $m['team1_over'] ?? '',
            'team_b_over' => $m['team_b_over'] ?? $m['team2_over'] ?? '',
            'live_inning' => $m['live_inning'] ?? $m['current_inning'] ?? '',
            'status_note' => $statusDetail,
            'status' => $m['status'] ?? $defaultState,
            'result' => $result,
            'venue' => $m['venue'] ?? '',
            'start_time' => $m['date'] ?? $m['start_time'] ?? '',
            'match_date' => $m['date'] ?? $m['start_time'] ?? '',
            'match_time' => $m['match_time'] ?? '',
            'raw' => $m,
        ];
    }

    /**
     * Strict completed match detector.
     * Inspects state, status, match_status, and result (lowercased & trimmed).
     */
    public static function isCompletedMatch(array $match): bool
    {
        $state = strtolower(trim((string) ($match['state'] ?? '')));
        $status = strtolower(trim((string) ($match['status'] ?? '')));
        $matchStatus = strtolower(trim((string) ($match['match_status'] ?? '')));
        $result = strtolower(trim((string) ($match['result'] ?? $match['status_note'] ?? '')));

        // Check state and match_status for completion keywords
        $completedStates = ['complete', 'finished', 'result', 'abandon', 'cancel', 'draw', 'tie', 'no result', '3'];
        foreach ($completedStates as $term) {
            if ($term !== '' && (str_contains($state, $term) || str_contains($matchStatus, $term))) {
                return true;
            }
        }

        // Check result or status text for completed result patterns
        $resultPatterns = [
            'won by',
            'match drawn',
            'match tied',
            'no result',
            'abandoned',
            'match ended',
            'beat ',
            'defeated',
            'series drawn',
            'awarded',
        ];

        foreach ($resultPatterns as $pattern) {
            if (str_contains($result, $pattern) || str_contains($status, $pattern)) {
                return true;
            }
        }

        // Any non-empty result string that signifies an outcome
        if (!empty($result) && (str_contains($result, 'won') || str_contains($result, 'tie') || str_contains($result, 'draw') || str_contains($result, 'abandon') || str_contains($result, 'lost'))) {
            return true;
        }

        return false;
    }

    /**
     * Strict upcoming match detector.
     * Returns true if the match has not started yet (Upcoming, Scheduled, Not started, or Yet to bat with no live score/overs).
     */
    public static function isUpcomingMatch(array $match): bool
    {
        if (self::isCompletedMatch($match)) {
            return false;
        }

        return !self::isLiveMatch($match);
    }

    /**
     * Strict live match detector.
     * Returns false if completed or upcoming/not-started; true only for genuinely in-progress matches.
     */
    public static function isLiveMatch(array $match): bool
    {
        if (self::isCompletedMatch($match)) {
            return false;
        }

        $state = strtolower(trim((string) ($match['state'] ?? '')));
        $status = strtolower(trim((string) ($match['status'] ?? '')));
        $matchStatus = strtolower(trim((string) ($match['match_status'] ?? '')));
        $statusNote = strtolower(trim((string) ($match['status_note'] ?? '')));
        $combinedStatus = trim($state . ' ' . $status . ' ' . $matchStatus . ' ' . $statusNote);

        // Live scores exist without a final result
        $scoreA = trim((string) ($match['score_a'] ?? $match['team_a_scores'] ?? $match['team_a_score'] ?? ''));
        $scoreB = trim((string) ($match['score_b'] ?? $match['team_b_scores'] ?? $match['team_b_score'] ?? ''));
        $score = trim((string) ($match['score'] ?? ''));

        $isRealScore = function (string $s): bool {
            if ($s === '') return false;
            $lower = strtolower($s);
            if ($lower === 'yet to bat' || $lower === 'null' || $lower === '0/0 (0)' || $lower === '0/0') {
                return false;
            }
            return preg_match('/[0-9]/', $s) === 1;
        };

        $hasRealScore = $isRealScore($scoreA) || $isRealScore($scoreB) || $isRealScore($score);

        // Explicit upcoming / not-started indicators
        $upcomingTerms = [
            'upcoming',
            'scheduled',
            'fixture',
            'not started',
            'match starts at',
            'starts at',
            'preview',
        ];

        foreach ($upcomingTerms as $term) {
            if (str_contains($combinedStatus, $term) && !$hasRealScore) {
                return false;
            }
        }

        if ($hasRealScore) {
            return true;
        }

        // Exact numeric live codes (avoid matching dates/times like "Nov 25" or "15th")
        if (in_array($state, ['1', '2'], true) || in_array($matchStatus, ['1', '2'], true)) {
            return true;
        }

        $liveTerms = [
            'in progress',
            'in_progress',
            'inprogress',
            'playing',
            'innings break',
            'innings_break',
            'stumps',
            'lunch',
            'tea',
            'drinks',
            'day ',
            'rain',
            'delay',
            'toss',
            'opt to',
            'elected to',
        ];

        foreach ($liveTerms as $term) {
            if (str_contains($combinedStatus, $term)) {
                return true;
            }
        }

        // Only treat 'live' as live if not accompanied by 'yet to bat' on both sides with no active play
        if ($state === 'live' || $matchStatus === 'live') {
            $bothYetToBat = (strtolower($scoreA) === 'yet to bat' || $scoreA === '')
                && (strtolower($scoreB) === 'yet to bat' || $scoreB === '')
                && $score === '';
            if (!$bothYetToBat) {
                return true;
            }
        }

        return false;
    }

    /**
     * Universal Match Bucket Classifier.
     * Classifies a match into 'finished', 'live', or 'upcoming'.
     */
    public static function classifyMatchBucket(array $m): string
    {
        if (self::isCompletedMatch($m)) {
            return 'finished';
        }

        if (self::isLiveMatch($m)) {
            return 'live';
        }

        return 'upcoming';
    }

    /**
     * Check if a match is finished / completed.
     */
    public function isFinishedMatch(int|string $matchId): bool
    {
        $matchId = (int) $matchId;
        if ($matchId <= 0) return false;

        $managed = ManagedMatch::where('match_id', $matchId)->first();
        if ($managed && ($managed->lifecycle_stage === 'completed' || strtolower((string)$managed->state) === 'complete')) {
            return true;
        }

        // Check if completed scorecard exists
        if (class_exists(\App\Models\CompletedMatchScorecard::class) && CompletedMatchScorecard::where('match_id', $matchId)->exists()) {
            return true;
        }

        // Check cached match detail
        $cached = Cache::get("cricket:redis:match:{$matchId}");
        if (is_array($cached) && !empty($cached)) {
            $bucket = self::classifyMatchBucket($cached);
            if ($bucket === 'finished') return true;
        }

        return false;
    }

    /**
     * Sanitize and strictly segregate matches into live, upcoming, and finished buckets.
     * Guarantees zero completed or in-progress matches leak into upcoming.
     */
    public static function sanitizeMatchBuckets(array $live, array $upcoming, array $finished): array
    {
        $all = [];
        // Process in order so latest/live takes precedence during merge
        foreach ([$finished, $upcoming, $live] as $list) {
            foreach ($list as $m) {
                if (!is_array($m)) continue;
                $mId = (int) ($m['match_id'] ?? $m['id'] ?? 0);
                if ($mId > 0) {
                    $all[$mId] = array_merge($all[$mId] ?? [], $m);
                }
            }
        }

        $cleanLive = [];
        $cleanUpcoming = [];
        $cleanFinished = [];

        foreach ($all as $m) {
            if (self::isCompletedMatch($m)) {
                $m['state'] = (isset($m['state']) && strtolower((string)$m['state']) === 'live') ? 'Complete' : ($m['state'] ?? 'Complete');
                $cleanFinished[] = $m;
            } elseif (self::isLiveMatch($m)) {
                $m['state'] = 'Live';
                $cleanLive[] = $m;
            } else {
                // Strictly upcoming: ensure no score exists other than empty/Yet to bat
                $scoreA = trim((string) ($m['score_a'] ?? $m['team_a_scores'] ?? $m['team_a_score'] ?? ''));
                $scoreB = trim((string) ($m['score_b'] ?? $m['team_b_scores'] ?? $m['team_b_score'] ?? ''));
                $hasScoreA = $scoreA !== '' && strtolower($scoreA) !== 'yet to bat' && strtolower($scoreA) !== 'null';
                $hasScoreB = $scoreB !== '' && strtolower($scoreB) !== 'yet to bat' && strtolower($scoreB) !== 'null';

                if ($hasScoreA || $hasScoreB) {
                    // Has score; if result not set, it's live; if result set, it's finished
                    $result = trim((string) ($m['result'] ?? ''));
                    if (!empty($result)) {
                        $cleanFinished[] = $m;
                    } else {
                        $m['state'] = 'Live';
                        $cleanLive[] = $m;
                    }
                } else {
                    $m['state'] = $m['state'] ?? 'Scheduled';
                    $cleanUpcoming[] = $m;
                }
            }
        }

        return [
            'live' => array_values($cleanLive),
            'upcoming' => array_values($cleanUpcoming),
            'recent' => array_values($cleanFinished),
            'finished' => array_values($cleanFinished),
        ];
    }

    /**
     * Get Fast Home Data partitioned into live, upcoming, and recent strictly backed by Redis / Cache.
     */
    public function getHomeFromRedis(): array
    {
        $redisHome = Cache::get('cricket:redis:home');
        if (is_array($redisHome) && (!empty($redisHome['live']) || !empty($redisHome['upcoming']) || !empty($redisHome['recent']))) {
            $sanitized = self::sanitizeMatchBuckets(
                $redisHome['live'] ?? [],
                $redisHome['upcoming'] ?? [],
                $redisHome['recent'] ?? ($redisHome['finished'] ?? [])
            );

            return [
                'live' => $this->filterAllowedMatches($sanitized['live']),
                'upcoming' => $this->filterAllowedMatches($sanitized['upcoming']),
                'recent' => $this->filterAllowedMatches($sanitized['recent']),
                'finished' => $this->filterAllowedMatches($sanitized['finished']),
            ];
        }

        return $this->getFastHomeData();
    }

    /**
     * Write Home Payload to Redis.
     */
    public function writeHomeToRedis(array $payload): void
    {
        Cache::put('cricket:redis:home', $payload, 3600);
    }

    /**
     * Write Match Payload to Redis.
     */
    public function writeMatchToRedis(int|string $matchId, array $payload): void
    {
        $ttl = self::classifyMatchBucket($payload) === 'finished' ? 604800 : 300;
        Cache::put("cricket:redis:match:{$matchId}", $payload, $ttl);
    }

    /**
     * Get Fast Match Data for real-time Live Line & Match Center.
     */
    public function getFastMatchData(int|string $matchId, bool $includeCommentary = false): array
    {
        $matchId = (int) $matchId;
        $cacheKey = "cricket:redis:match:{$matchId}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && !empty($cached)) {
            if ($includeCommentary && empty($cached['commentary'])) {
                $comm = $this->getCommentary($matchId);
                $cached['commentary'] = $comm['commentary'] ?? [];
            }
            if (empty($cached['batters']) || empty($cached['bowler'])) {
                $cached = $this->enrichFastMatchPayload($cached);
            }
            return $cached;
        }

        // Gather from live/upcoming/recent
        $homeData = $this->getFastHomeData();
        $allMatches = array_merge(
            $homeData['live'] ?? [],
            $homeData['upcoming'] ?? [],
            $homeData['recent'] ?? []
        );

        $baseMatch = [];
        foreach ($allMatches as $m) {
            $mId = (int) ($m['match_id'] ?? $m['id'] ?? 0);
            if ($mId === $matchId) {
                $baseMatch = $m;
                break;
            }
        }

        if (empty($baseMatch)) {
            $baseMatch = [
                'match_id' => $matchId,
                'id' => $matchId,
                'state' => 'Scheduled',
                'team_a' => 'Team A',
                'team_b' => 'Team B',
            ];
        }

        $scorecard = $this->getScorecard($matchId);
        $comm = $includeCommentary ? $this->getCommentary($matchId) : [];
        $facts = $this->getMatchFacts($matchId);

        $payload = array_merge($baseMatch, [
            'scorecard' => $scorecard['innings'] ?? [],
            'commentary' => $comm['commentary'] ?? [],
            'facts' => $facts,
            'updated_at' => now()->toIso8601String(),
        ]);

        $payload = $this->enrichFastMatchPayload($payload);

        $this->writeMatchToRedis($matchId, $payload);
        return $payload;
    }

    /**
     * Enrich fast match payload with active batters on crease, current & previous bowlers,
     * normalized overs, and 2nd innings run-chase target metrics.
     */
    public function enrichFastMatchPayload(array $payload): array
    {
        // 1. Normalize scores and overs across payload
        if (!empty($payload['team_a_scores'])) {
            $payload['team_a_scores'] = \App\Helpers\CricketMatchFormatter::normalizeScoreString($payload['team_a_scores']);
        }
        if (!empty($payload['score_a'])) {
            $payload['score_a'] = \App\Helpers\CricketMatchFormatter::normalizeScoreString($payload['score_a']);
        }
        if (!empty($payload['team_b_scores'])) {
            $payload['team_b_scores'] = \App\Helpers\CricketMatchFormatter::normalizeScoreString($payload['team_b_scores']);
        }
        if (!empty($payload['score_b'])) {
            $payload['score_b'] = \App\Helpers\CricketMatchFormatter::normalizeScoreString($payload['score_b']);
        }
        if (!empty($payload['score'])) {
            $payload['score'] = \App\Helpers\CricketMatchFormatter::normalizeScoreString($payload['score']);
        }
        if (!empty($payload['team_a_over'])) {
            $payload['team_a_over'] = \App\Helpers\CricketMatchFormatter::normalizeOvers($payload['team_a_over']);
        }
        if (!empty($payload['team_b_over'])) {
            $payload['team_b_over'] = \App\Helpers\CricketMatchFormatter::normalizeOvers($payload['team_b_over']);
        }

        // 2. Extract active batters and bowlers from scorecard if available
        $scorecardInnings = $payload['scorecard'] ?? [];
        $activeInning = !empty($scorecardInnings) && is_array($scorecardInnings) ? end($scorecardInnings) : null;

        $liveBatters = $payload['batters'] ?? $payload['live_batters'] ?? [];
        if (empty($liveBatters) && $activeInning) {
            $allBatsmen = $activeInning['batsmen'] ?? $activeInning['batsman'] ?? [];
            foreach ($allBatsmen as $b) {
                $out = strtolower(trim($b['out_desc'] ?? $b['out_by'] ?? ''));
                if ($out === 'batting' || $out === 'not out' || $out === '' || str_contains($out, 'not out')) {
                    $liveBatters[] = [
                        'name' => trim($b['name'] ?? $b['short_name'] ?? 'Batter'),
                        'runs' => (int) ($b['runs'] ?? $b['run'] ?? 0),
                        'balls' => (int) ($b['balls'] ?? $b['ball'] ?? 0),
                        'fours' => (int) ($b['fours'] ?? $b['four'] ?? 0),
                        'sixes' => (int) ($b['sixes'] ?? $b['six'] ?? 0),
                        'strike_rate' => (string) ($b['strike_rate'] ?? $b['sr'] ?? '0.00'),
                        'is_striker' => count($liveBatters) === 0,
                    ];
                }
            }

            // If fewer than 2 active batters found, supplement with partner from yet_to_bat or last batsman
            if (count($liveBatters) < 2) {
                if (!empty($activeInning['yet_to_bat'][0])) {
                    $nextName = is_array($activeInning['yet_to_bat'][0]) ? ($activeInning['yet_to_bat'][0]['name'] ?? 'Batter') : $activeInning['yet_to_bat'][0];
                    $liveBatters[] = [
                        'name' => trim($nextName),
                        'runs' => 0,
                        'balls' => 0,
                        'fours' => 0,
                        'sixes' => 0,
                        'strike_rate' => '0.00',
                        'is_striker' => false,
                    ];
                } elseif (count($allBatsmen) >= 2) {
                    $lastTwo = array_slice($allBatsmen, -2);
                    foreach ($lastTwo as $cand) {
                        $cName = trim($cand['name'] ?? $cand['short_name'] ?? '');
                        $exists = false;
                        foreach ($liveBatters as $lb) {
                            if (($lb['name'] ?? '') === $cName) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $liveBatters[] = [
                                'name' => $cName ?: 'Batter',
                                'runs' => (int) ($cand['runs'] ?? 0),
                                'balls' => (int) ($cand['balls'] ?? 0),
                                'fours' => (int) ($cand['fours'] ?? 0),
                                'sixes' => (int) ($cand['sixes'] ?? 0),
                                'strike_rate' => (string) ($cand['strike_rate'] ?? '0.00'),
                                'is_striker' => false,
                            ];
                            if (count($liveBatters) >= 2) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        // 3. Extract bowlers: Current bowler (last) and Previous bowler (second-to-last)
        $liveBowlers = [];
        if ($activeInning) {
            $allBowlers = $activeInning['bowlers'] ?? $activeInning['bowler'] ?? [];
            if (!empty($allBowlers)) {
                $lastTwo = array_reverse(array_slice($allBowlers, -2));
                foreach ($lastTwo as $idx => $bo) {
                    $liveBowlers[] = [
                        'name' => trim($bo['name'] ?? $bo['player_name'] ?? 'Bowler'),
                        'overs' => \App\Helpers\CricketMatchFormatter::normalizeOvers($bo['overs'] ?? $bo['over'] ?? '0.0'),
                        'maidens' => (int) ($bo['maidens'] ?? $bo['maiden'] ?? 0),
                        'runs' => (int) ($bo['runs'] ?? $bo['run'] ?? 0),
                        'wickets' => (int) ($bo['wickets'] ?? $bo['wicket'] ?? 0),
                        'economy' => (string) ($bo['economy'] ?? $bo['econ'] ?? '0.00'),
                        'is_current' => $idx === 0,
                    ];
                }
            }
        }

        // 4. Calculate target, run chase metrics for 2nd innings
        $target = $payload['target'] ?? null;
        $runsNeeded = null;
        $ballsRem = null;
        $rrr = null;

        $is2ndInnings = false;
        if (count($scorecardInnings) >= 2) {
            $is2ndInnings = true;
        } elseif (!empty($payload['team_a_scores']) && !empty($payload['team_b_scores'])) {
            $is2ndInnings = true;
        } elseif (preg_match('/need\s+(\d+)\s+runs/i', (string)($payload['status_note'] ?? $payload['status'] ?? ''))) {
            $is2ndInnings = true;
        }

        if ($is2ndInnings) {
            if (!$target) {
                // Parse 1st inning score
                $inn1Score = $scorecardInnings[0]['score'] ?? $payload['team_a_scores'] ?? $payload['score_a'] ?? '';
                if (preg_match('/^(\d+)/', (string)$inn1Score, $mInn1)) {
                    $target = ((int)$mInn1[1]) + 1;
                }
            }

            // Current score
            $inn2Score = $scorecardInnings[1]['score'] ?? $payload['team_b_scores'] ?? $payload['score_b'] ?? '';
            $currRuns = 0;
            if (preg_match('/^(\d+)/', (string)$inn2Score, $mInn2)) {
                $currRuns = (int)$mInn2[1];
            }

            // Status note extraction e.g. "Zimbabwe Women need 81 runs in 36 balls"
            $statusText = (string)($payload['status_note'] ?? $payload['status'] ?? '');
            if (preg_match('/need\s+(\d+)\s+runs/i', $statusText, $mNeed)) {
                $runsNeeded = (int)$mNeed[1];
                if (!$target && $currRuns > 0) {
                    $target = $currRuns + $runsNeeded;
                }
            } elseif ($target && $currRuns > 0) {
                $runsNeeded = max(0, $target - $currRuns);
            }

            if (preg_match('/in\s+(\d+)\s+balls/i', $statusText, $mBalls)) {
                $ballsRem = (int)$mBalls[1];
            } elseif (preg_match('/in\s+([\d\.]+)\s+ov/i', $statusText, $mOv)) {
                $ovParts = explode('.', $mOv[1]);
                $ballsRem = (((int)($ovParts[0] ?? 0)) * 6) + ((int)($ovParts[1] ?? 0));
            }

            if ($runsNeeded !== null && $ballsRem !== null && $ballsRem > 0) {
                $rrr = number_format(($runsNeeded / $ballsRem) * 6, 2);
            }
        }

        $payload['batters'] = $liveBatters;
        $payload['live_batters'] = $liveBatters;
        $payload['batsman'] = $liveBatters;
        $payload['bowler'] = $liveBowlers;
        $payload['live_bowler'] = $liveBowlers[0] ?? null;
        $payload['live_bowlers'] = $liveBowlers;
        if ($target) {
            $payload['target'] = $target;
        }
        if ($runsNeeded !== null) {
            $payload['run_needed'] = $runsNeeded;
        }
        if ($ballsRem !== null) {
            $payload['balls_rem'] = $ballsRem;
        }
        if ($rrr !== null) {
            $payload['rrr'] = $rrr;
        }
        $payload['current_inning'] = count($scorecardInnings) ?: 1;

        return $payload;
    }

    /**
     * Dynamic format and break-aware TTL configuration for live poller daemon.
     */
    public function getDynamicTtls(int|string $matchId, array $baseMatch = []): array
    {
        $format = strtoupper($baseMatch['format'] ?? 'T20');
        $state = strtolower($baseMatch['state'] ?? $baseMatch['status'] ?? '');
        $inBreak = str_contains($state, 'break') || str_contains($state, 'delay') || str_contains($state, 'rain') || str_contains($state, 'tea') || str_contains($state, 'lunch') || str_contains($state, 'stumps');

        $pollIntervalMs = match ($format) {
            'TEST' => $inBreak ? 45000 : 20000,
            'ODI' => $inBreak ? 30000 : 15000,
            default => $inBreak ? 20000 : 8000,
        };

        return [
            'format' => $format,
            'in_break' => $inBreak,
            'poll_interval_ms' => $pollIntervalMs,
            'scorecard_ttl' => 20,
        ];
    }

    /**
     * Get or Archive Completed Match Scorecard in database for permanent 0-API call serving.
     */
    public function getOrArchiveCompletedScorecard(int|string $matchId, array $matchMeta = []): array
    {
        $matchId = (int) $matchId;
        if (class_exists(\App\Models\CompletedMatchScorecard::class)) {
            $record = CompletedMatchScorecard::where('match_id', $matchId)->first();
            if ($record && !empty($record->scorecard_json)) {
                return $record->scorecard_json;
            }
        }

        $scorecard = $this->getScorecard($matchId);
        $innings = $scorecard['innings'] ?? $scorecard;

        if (class_exists(\App\Models\CompletedMatchScorecard::class) && !empty($innings)) {
            try {
                CompletedMatchScorecard::updateOrCreate(
                    ['match_id' => $matchId],
                    [
                        'series_name' => $matchMeta['series_name'] ?? null,
                        'team_a' => $matchMeta['team_a'] ?? null,
                        'team_b' => $matchMeta['team_b'] ?? null,
                        'result_text' => $matchMeta['result_text'] ?? null,
                        'scorecard_json' => ['innings' => $innings],
                    ]
                );
            } catch (\Throwable $e) {}
        }

        return ['innings' => $innings];
    }

    /**
     * Get normalized recent overs history from commentary data.
     */
    public function getNormalizedRecentOvers(int|string $matchId, array $commentaryData = []): array
    {
        $overs = [];
        foreach ($commentaryData as $comm) {
            $overNum = (int) ($comm['over'] ?? 0);
            if (!isset($overs[$overNum])) {
                $overs[$overNum] = [
                    'over_number' => $overNum,
                    'balls' => [],
                ];
            }
            $overs[$overNum]['balls'][] = [
                'label' => (string) ($comm['runs'] ?? $comm['text'] ?? '•'),
                'runs' => $comm['runs'] ?? 0,
            ];
        }

        return array_values($overs);
    }

    /**
     * Pre-Match Data & Playing XI Archiving
     */
    public function archivePreMatchData(ManagedMatch $match): void
    {
        $matchId = (string) $match->match_id;
        $facts = $this->getMatchFacts($matchId);
        $squads = $this->getSquads($matchId);

        $payload = [
            'match_id' => $matchId,
            'venue' => $facts['venue'] ?? '',
            'toss' => $facts['toss'] ?? '',
            'facts' => $facts,
            'squads' => $squads,
            'archived_at' => now()->toIso8601String(),
        ];

        Storage::disk('local')->put("match-cache/pre-match-{$matchId}.json", json_encode($payload, JSON_PRETTY_PRINT));
        $match->update(['pre_match_archived' => true]);
    }

    public function getPreMatchArchivedData(int|string $matchId): ?array
    {
        $path = "match-cache/pre-match-{$matchId}.json";
        if (Storage::disk('local')->exists($path)) {
            return json_decode(Storage::disk('local')->get($path), true) ?: null;
        }
        return null;
    }

    public function getArchivedPlayingXI(int|string $matchId): ?array
    {
        $cached = Cache::get("match:xi:{$matchId}");
        if (is_array($cached)) return $cached;

        $path = "match-cache/playing-xi-{$matchId}.json";
        if (Storage::disk('local')->exists($path)) {
            $data = json_decode(Storage::disk('local')->get($path), true) ?: null;
            if ($data) {
                Cache::put("match:xi:{$matchId}", $data, 86400);
                return $data;
            }
        }
        return null;
    }

    /**
     * Get Fast Home Data partitioned into live, upcoming, and recent.
     * Cached in Redis with TTLs: 30s for live, 600s for upcoming & recent.
     */
    public function getFastHomeData(): array
    {
        $cacheKey = 'cricket:redis:fast_home_data';
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            $sanitized = self::sanitizeMatchBuckets(
                $cached['live'] ?? [],
                $cached['upcoming'] ?? [],
                $cached['recent'] ?? ($cached['finished'] ?? [])
            );

            return [
                'live' => $this->filterAllowedMatches($sanitized['live']),
                'upcoming' => $this->filterAllowedMatches($sanitized['upcoming']),
                'recent' => $this->filterAllowedMatches($sanitized['recent']),
                'finished' => $this->filterAllowedMatches($sanitized['finished']),
            ];
        }

        $live = $this->getLiveMatches();
        $upcoming = $this->getUpcomingMatches();
        $recent = $this->getRecentMatches();

        $sanitized = self::sanitizeMatchBuckets($live, $upcoming, $recent);

        $data = [
            'live' => $sanitized['live'],
            'upcoming' => $sanitized['upcoming'],
            'recent' => $sanitized['recent'],
            'finished' => $sanitized['finished'],
        ];

        $ttl = !empty($sanitized['live']) ? 30 : 180;
        Cache::put($cacheKey, $data, $ttl);

        return [
            'live' => $this->filterAllowedMatches($data['live']),
            'upcoming' => $this->filterAllowedMatches($data['upcoming']),
            'recent' => $this->filterAllowedMatches($data['recent']),
            'finished' => $this->filterAllowedMatches($data['finished']),
        ];
    }

    /**
     * GET /cricket/matches/live
     */
    public function getLiveMatches(): array
    {
        $res = $this->get('/cricket/matches/live');
        $matches = $res['data'] ?? $res['matches'] ?? (is_array($res) && array_is_list($res) ? $res : []);
        $output = [];

        foreach ($matches as $m) {
            if (!is_array($m)) continue;
            $output[] = $this->normalizeMatchPayload($m, 'Live');
        }

        return $this->filterAllowedMatches($output);
    }

    /**
     * GET /cricket/matches/recent
     */
    public function getRecentMatches(): array
    {
        $res = $this->get('/cricket/matches/recent');
        $matches = $res['data'] ?? $res['matches'] ?? (is_array($res) && array_is_list($res) ? $res : []);
        $output = [];

        foreach ($matches as $m) {
            if (!is_array($m)) continue;
            $output[] = $this->normalizeMatchPayload($m, 'Complete');
        }

        return $this->filterAllowedMatches($output);
    }

    /**
     * GET /cricket/matches/upcoming
     */
    public function getUpcomingMatches(): array
    {
        $res = $this->get('/cricket/matches/upcoming');
        $matches = $res['data'] ?? $res['matches'] ?? (is_array($res) && array_is_list($res) ? $res : []);
        $output = [];

        foreach ($matches as $m) {
            if (!is_array($m)) continue;
            $output[] = $this->normalizeMatchPayload($m, 'Scheduled');
        }

        return $this->filterAllowedMatches($output);
    }

    /**
     * GET /cricket/scorecard/{$matchId}
     * Maps innings (team, runs, wickets, overs, batters, bowlers).
     */
    public function getScorecard(int|string $matchId): array
    {
        $res = $this->get("/cricket/scorecard/{$matchId}");
        if (!$res) {
            return [];
        }

        $innings = $res['data']['innings'] ?? $res['innings'] ?? $res['scorecard'] ?? [];
        return [
            'match_id' => (int) $matchId,
            'innings' => is_array($innings) ? $innings : [],
            'raw' => $res,
        ];
    }

    /**
     * GET /cricket/commentary/{$matchId}
     * Maps over, ball, runs, type, text, batsman, bowler.
     */
    public function getCommentary(int|string $matchId): array
    {
        $res = $this->get("/cricket/commentary/{$matchId}");
        if (!$res) {
            return [];
        }

        $items = $res['data']['commentary'] ?? $res['commentary'] ?? $res['data'] ?? [];
        $mapped = [];

        if (is_array($items)) {
            foreach ($items as $c) {
                if (!is_array($c)) continue;

                $over = $c['over'] ?? $c['over_number'] ?? '';
                $ball = $c['ball'] ?? $c['ball_number'] ?? '';
                if ($over === '' && isset($c['ball_metric']) && is_numeric($c['ball_metric'])) {
                    $parts = explode('.', (string) $c['ball_metric']);
                    $over = $parts[0] ?? '';
                    $ball = $parts[1] ?? '';
                }

                $mapped[] = [
                    'over' => $over,
                    'ball' => $ball,
                    'runs' => $c['runs'] ?? $c['run'] ?? 0,
                    'type' => $c['type'] ?? $c['event'] ?? 'ball',
                    'text' => $c['text'] ?? $c['commentary'] ?? $c['comm'] ?? '',
                    'batsman' => $c['batsman'] ?? $c['batsman_name'] ?? $c['batter'] ?? '',
                    'bowler' => $c['bowler'] ?? $c['bowler_name'] ?? '',
                ];
            }
        }

        return [
            'match_id' => (int) $matchId,
            'commentary' => $mapped,
            'miniscore' => $res['data']['miniscore'] ?? $res['miniscore'] ?? null,
            'match_header' => $res['data']['match_header'] ?? $res['match_header'] ?? null,
            'raw' => $res,
        ];
    }

    /**
     * GET /cricket/squads/{$matchId}
     * Maps team_a (name, playing_xi, bench) and team_b (name, playing_xi, bench).
     */
    public function getSquads(int|string $matchId): array
    {
        $res = $this->get("/cricket/squads/{$matchId}");
        if (!$res) {
            return [];
        }

        $data = $res['data'] ?? $res;
        $teamAData = [];
        $teamBData = [];

        if (is_array($data) && array_is_list($data) && count($data) >= 2) {
            $teamAData = $data[0] ?? [];
            $teamBData = $data[1] ?? [];
        } else {
            $teamAData = $data['team_a'] ?? $data['team1'] ?? [];
            $teamBData = $data['team_b'] ?? $data['team2'] ?? [];
        }

        return [
            'match_id' => (int) $matchId,
            'team_a' => [
                'name' => $teamAData['name'] ?? $teamAData['team_name'] ?? 'Team A',
                'short' => $teamAData['short_name'] ?? $teamAData['team_short'] ?? '',
                'playing_xi' => $teamAData['playing_xi'] ?? $teamAData['squad'] ?? [],
                'bench' => $teamAData['bench'] ?? [],
            ],
            'team_b' => [
                'name' => $teamBData['name'] ?? $teamBData['team_name'] ?? 'Team B',
                'short' => $teamBData['short_name'] ?? $teamBData['team_short'] ?? '',
                'playing_xi' => $teamBData['playing_xi'] ?? $teamBData['squad'] ?? [],
                'bench' => $teamBData['bench'] ?? [],
            ],
            'raw' => $res,
        ];
    }

    /**
     * GET /cricket/highlights/{$matchId}
     * Maps title, thumbnail, hls_url, duration.
     */
    public function getHighlights(int|string $matchId): array
    {
        $res = $this->get("/cricket/highlights/{$matchId}");
        if (!$res) {
            return [];
        }

        $items = $res['data']['highlights'] ?? $res['highlights'] ?? $res['data'] ?? (array_is_list($res) ? $res : [$res]);
        $output = [];

        if (is_array($items)) {
            foreach ($items as $h) {
                if (!is_array($h)) continue;
                $output[] = [
                    'title' => $h['title'] ?? 'Match Highlight',
                    'thumbnail' => $h['thumbnail'] ?? $h['thumb'] ?? $h['image'] ?? '',
                    'hls_url' => $h['hls_url'] ?? $h['stream_url'] ?? $h['video_url'] ?? '',
                    'duration' => $h['duration'] ?? $h['time'] ?? '',
                ];
            }
        }

        return $output;
    }

    /**
     * GET /cricket/match-facts/{$matchId}
     * Maps toss, venue, umpires, referee, day_night, series.
     */
    public function getMatchFacts(int|string $matchId): array
    {
        $res = $this->get("/cricket/match-facts/{$matchId}");
        if (!$res) {
            return [];
        }

        $d = $res['data'] ?? $res;
        return [
            'match_id' => (int) $matchId,
            'toss' => $d['toss'] ?? $d['toss_winner'] ?? '',
            'venue' => $d['venue'] ?? $d['stadium'] ?? '',
            'umpires' => $d['umpires'] ?? '',
            'referee' => $d['referee'] ?? '',
            'day_night' => (bool) ($d['day_night'] ?? false),
            'series' => $d['series'] ?? $d['series_name'] ?? '',
            'raw' => $res,
        ];
    }

    /**
     * GET /cricket/schedule/{$category}
     */
    public function getScheduleByCategory(string $category = 'all'): array
    {
        $res = $this->get("/cricket/schedule/{$category}");
        $items = $res['data'] ?? $res ?? [];
        if (!is_array($items)) return [];

        $output = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $output[] = $this->normalizeMatchPayload($item, 'Scheduled');
        }
        return $output;
    }

    /**
     * GET /cricket/series/{$category}
     */
    public function getSeriesByCategory(string $category = 'all'): array
    {
        $res = $this->get("/cricket/series/{$category}");
        return $res['data'] ?? $res ?? [];
    }

    /**
     * GET /cricket/teams/{$category}
     */
    public function getTeamsByCategory(string $category = 'all'): array
    {
        $res = $this->get("/cricket/teams/{$category}");
        return $res['data'] ?? $res ?? [];
    }

    /**
     * GET /player/{$playerId}/{$slug}
     */
    public function getPlayerProfile(int|string $playerId, string $slug): array
    {
        $res = $this->get("/player/{$playerId}/{$slug}");
        return $res['data'] ?? $res ?? [];
    }

    /**
     * GET /cricket/icc-rankings/{$category}/{$format}
     */
    public function getIccRankings(string $category = 'men', string $format = 't20'): array
    {
        $res = $this->get("/cricket/icc-rankings/{$category}/{$format}");
        return $res['data'] ?? $res ?? [];
    }

    /**
     * syncMatchCatalog(): void
     * Upserts live, upcoming, and recent matches into managed_matches and skipped_series tables.
     * Raw matches are fetched prior to skip filtering to ensure skipped series can be un-skipped in Admin.
     */
    public function syncMatchCatalog(): void
    {
        // 1. Fetch raw API feeds before skip filtering
        $liveRes = $this->get('/cricket/matches/live');
        $liveRaw = $liveRes['data'] ?? $liveRes['matches'] ?? (is_array($liveRes) && array_is_list($liveRes) ? $liveRes : []);
        $liveNormalized = [];
        foreach ($liveRaw as $m) {
            if (is_array($m)) {
                $liveNormalized[] = $this->normalizeMatchPayload($m, 'Live');
            }
        }

        $recentRes = $this->get('/cricket/matches/recent');
        $recentRaw = $recentRes['data'] ?? $recentRes['matches'] ?? (is_array($recentRes) && array_is_list($recentRes) ? $recentRes : []);
        $recentNormalized = [];
        foreach ($recentRaw as $m) {
            if (is_array($m)) {
                $recentNormalized[] = $this->normalizeMatchPayload($m, 'Complete');
            }
        }

        $upcomingRes = $this->get('/cricket/matches/upcoming');
        $upcomingRaw = $upcomingRes['data'] ?? $upcomingRes['matches'] ?? (is_array($upcomingRes) && array_is_list($upcomingRes) ? $upcomingRes : []);
        $upcomingNormalized = [];
        foreach ($upcomingRaw as $m) {
            if (is_array($m)) {
                $upcomingNormalized[] = $this->normalizeMatchPayload($m, 'Scheduled');
            }
        }

        // 2. Sync to managed_matches table (preserving admin is_covered choices)
        $all = [
            ...$liveNormalized,
            ...$upcomingNormalized,
            ...$recentNormalized,
        ];

        foreach ($all as $m) {
            $matchId = (int) ($m['match_id'] ?? 0);
            if ($matchId <= 0) {
                continue;
            }

            $existing = ManagedMatch::where('match_id', $matchId)->first();

            ManagedMatch::updateOrCreate(
                ['match_id' => $matchId],
                [
                    'series_name' => $m['series_name'] ?? null,
                    'series_id' => $m['series_id'] ?? ($m['seriesId'] ?? null),
                    'match_desc' => $m['match_desc'] ?? null,
                    'format' => $m['format'] ?? 'ODI',
                    'team_a' => $m['team_a'] ?? null,
                    'team_b' => $m['team_b'] ?? null,
                    'state' => $m['state'] ?? 'Scheduled',
                    'start_time' => $m['start_time'] ?? null,
                    'is_covered' => $existing ? $existing->is_covered : true,
                ]
            );
        }

        // 3. Group matches by normalized series_slug and upsert into SkippedSeries
        $seriesGroups = [];

        $processSeriesMatch = function (array $m, string $bucket) use (&$seriesGroups) {
            $seriesName = trim((string) ($m['series_name'] ?? $m['series'] ?? ''));
            if ($seriesName === '') {
                return;
            }

            $slug = Str::slug($seriesName);
            if ($slug === '') {
                return;
            }

            if (!isset($seriesGroups[$slug])) {
                $seriesGroups[$slug] = [
                    'series_id' => $m['series_id'] ?? ($m['seriesId'] ?? null),
                    'series_name' => $seriesName,
                    'series_slug' => $slug,
                    'format' => $m['format'] ?? 'T20',
                    'live_matches_count' => 0,
                    'upcoming_matches_count' => 0,
                    'recent_matches_count' => 0,
                ];
            }

            $mSeriesId = $m['series_id'] ?? ($m['seriesId'] ?? null);
            if (empty($seriesGroups[$slug]['series_id']) && !empty($mSeriesId)) {
                $seriesGroups[$slug]['series_id'] = (string) $mSeriesId;
            }

            if ($bucket === 'live') {
                $seriesGroups[$slug]['live_matches_count']++;
            } elseif ($bucket === 'upcoming') {
                $seriesGroups[$slug]['upcoming_matches_count']++;
            } elseif ($bucket === 'recent') {
                $seriesGroups[$slug]['recent_matches_count']++;
            }
        };

        foreach ($liveNormalized as $m) {
            $processSeriesMatch($m, 'live');
        }
        foreach ($upcomingNormalized as $m) {
            $processSeriesMatch($m, 'upcoming');
        }
        foreach ($recentNormalized as $m) {
            $processSeriesMatch($m, 'recent');
        }

        // Upsert series: NEVER overwrite existing is_skipped choice
        foreach ($seriesGroups as $slug => $group) {
            $record = SkippedSeries::firstOrCreate(
                ['series_slug' => $slug],
                [
                    'series_id' => $group['series_id'] ? (string) $group['series_id'] : null,
                    'series_name' => $group['series_name'],
                    'format' => $group['format'],
                    'live_matches_count' => $group['live_matches_count'],
                    'upcoming_matches_count' => $group['upcoming_matches_count'],
                    'recent_matches_count' => $group['recent_matches_count'],
                    'is_skipped' => false,
                ]
            );

            // Update match counts, series_id, format, series_name without resetting is_skipped
            $updates = [
                'series_name' => $group['series_name'],
                'format' => $group['format'] ?: ($record->format ?: 'T20'),
                'live_matches_count' => $group['live_matches_count'],
                'upcoming_matches_count' => $group['upcoming_matches_count'],
                'recent_matches_count' => $group['recent_matches_count'],
            ];
            if (!empty($group['series_id'])) {
                $updates['series_id'] = (string) $group['series_id'];
            }

            $record->update($updates);
        }
    }

    /**
     * Get Active Series Directory for /cricket-series
     * Merges active/upcoming series from SkippedSeries + cached series list + home catalog items.
     * Strictly excludes any series marked is_skipped in SkippedSeries.
     */
    public function getActiveSeriesDirectory(): array
    {
        $cacheKey = 'cricket:redis:series:directory';
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $seriesMap = [];

        // 1. Gather from SkippedSeries where is_skipped is false
        $coveredSeries = SkippedSeries::where('is_skipped', false)->get();
        foreach ($coveredSeries as $cs) {
            $name = trim((string) $cs->series_name);
            if ($name === '' || $this->isSeriesSkipped($name, $cs->series_id)) continue;
            $slug = $cs->series_slug ?: Str::slug($name);
            $seriesMap[$slug] = [
                'series_id' => $cs->series_id ?: null,
                'series_name' => $name,
                'series_slug' => $slug,
                'format' => $cs->format ?: 'T20',
                'date_range' => null,
                'live_count' => (int) ($cs->live_matches_count ?? 0),
                'upcoming_count' => (int) ($cs->upcoming_matches_count ?? 0),
                'completed_count' => (int) ($cs->recent_matches_count ?? 0),
                'is_live' => ($cs->live_matches_count ?? 0) > 0,
            ];
        }

        // 2. Gather from live, upcoming, recent matches in Redis (already filtered via getFastHomeData)
        $homeData = $this->getFastHomeData();
        $liveMatches = $homeData['live'] ?? [];
        $upcomingMatches = $homeData['upcoming'] ?? [];
        $recentMatches = $homeData['recent'] ?? $homeData['finished'] ?? [];

        foreach ($liveMatches as $m) {
            $name = trim((string) ($m['series_name'] ?? $m['series'] ?? ''));
            if ($name === '' || $this->isSeriesSkipped($m)) continue;
            $slug = Str::slug($name);
            if (!isset($seriesMap[$slug])) {
                $seriesMap[$slug] = [
                    'series_id' => $m['series_id'] ?? null,
                    'series_name' => $name,
                    'series_slug' => $slug,
                    'format' => $m['format'] ?? 'T20',
                    'date_range' => $m['date_range'] ?? null,
                    'live_count' => 0,
                    'upcoming_count' => 0,
                    'completed_count' => 0,
                    'is_live' => false,
                ];
            }
            $seriesMap[$slug]['live_count']++;
            $seriesMap[$slug]['is_live'] = true;
            if (empty($seriesMap[$slug]['series_id']) && !empty($m['series_id'])) {
                $seriesMap[$slug]['series_id'] = $m['series_id'];
            }
        }

        foreach ($upcomingMatches as $m) {
            $name = trim((string) ($m['series_name'] ?? $m['series'] ?? ''));
            if ($name === '' || $this->isSeriesSkipped($m)) continue;
            $slug = Str::slug($name);
            if (!isset($seriesMap[$slug])) {
                $seriesMap[$slug] = [
                    'series_id' => $m['series_id'] ?? null,
                    'series_name' => $name,
                    'series_slug' => $slug,
                    'format' => $m['format'] ?? 'T20',
                    'date_range' => $m['date_range'] ?? null,
                    'live_count' => 0,
                    'upcoming_count' => 0,
                    'completed_count' => 0,
                    'is_live' => false,
                ];
            }
            $seriesMap[$slug]['upcoming_count']++;
            if (empty($seriesMap[$slug]['series_id']) && !empty($m['series_id'])) {
                $seriesMap[$slug]['series_id'] = $m['series_id'];
            }
        }

        foreach ($recentMatches as $m) {
            $name = trim((string) ($m['series_name'] ?? $m['series'] ?? ''));
            if ($name === '' || $this->isSeriesSkipped($m)) continue;
            $slug = Str::slug($name);
            if (!isset($seriesMap[$slug])) {
                $seriesMap[$slug] = [
                    'series_id' => $m['series_id'] ?? null,
                    'series_name' => $name,
                    'series_slug' => $slug,
                    'format' => $m['format'] ?? 'T20',
                    'date_range' => $m['date_range'] ?? null,
                    'live_count' => 0,
                    'upcoming_count' => 0,
                    'completed_count' => 0,
                    'is_live' => false,
                ];
            }
            $seriesMap[$slug]['completed_count']++;
            if (empty($seriesMap[$slug]['series_id']) && !empty($m['series_id'])) {
                $seriesMap[$slug]['series_id'] = $m['series_id'];
            }
        }

        // 3. Integrate cached /cricket/series/all list for date ranges and IDs
        $apiSeriesData = $this->getSeriesByCategory('all');
        if (isset($apiSeriesData['months']) && is_array($apiSeriesData['months'])) {
            foreach ($apiSeriesData['months'] as $monthBlock) {
                foreach ($monthBlock['series'] ?? [] as $s) {
                    $name = trim((string) ($s['name'] ?? ''));
                    if ($name === '' || $this->isSeriesSkipped($name, $s['id'] ?? null)) continue;
                    $slug = Str::slug($name);
                    $dateRange = null;
                    if (!empty($s['start_date']) && !empty($s['end_date'])) {
                        try {
                            $d1 = \Carbon\Carbon::parse($s['start_date'])->format('M j');
                            $d2 = \Carbon\Carbon::parse($s['end_date'])->format('M j, Y');
                            $dateRange = "{$d1} - {$d2}";
                        } catch (\Throwable $e) {
                            $dateRange = "{$s['start_date']} to {$s['end_date']}";
                        }
                    }

                    if (isset($seriesMap[$slug])) {
                        if (empty($seriesMap[$slug]['series_id']) && !empty($s['id'])) {
                            $seriesMap[$slug]['series_id'] = $s['id'];
                        }
                        if (empty($seriesMap[$slug]['date_range']) && $dateRange) {
                            $seriesMap[$slug]['date_range'] = $dateRange;
                        }
                    }
                }
            }
        }

        // Clean & sort: Live series first, then upcoming, then total match counts
        $result = array_values(array_filter($seriesMap, function ($item) {
            if ($this->isSeriesSkipped($item['series_name'], $item['series_id'] ?? null)) return false;
            return ($item['live_count'] + $item['upcoming_count'] + $item['completed_count']) > 0;
        }));

        usort($result, function ($a, $b) {
            if ($a['is_live'] !== $b['is_live']) {
                return $a['is_live'] ? -1 : 1;
            }
            if ($a['live_count'] !== $b['live_count']) {
                return $b['live_count'] <=> $a['live_count'];
            }
            if ($a['upcoming_count'] !== $b['upcoming_count']) {
                return $b['upcoming_count'] <=> $a['upcoming_count'];
            }
            return ($b['completed_count'] <=> $a['completed_count']);
        });

        Cache::put($cacheKey, $result, 900); // 15 min TTL

        return $result;
    }

    /**
     * Get Complete Cricbuzz-Style Series Hub Data for /cricket-series/{seriesSlug}
     * Zero API calls if skipped. Returns null if skipped or non-existent.
     */
    public function getSeriesHubData(string $seriesSlug): ?array
    {
        $cleanSlug = Str::slug(trim($seriesSlug));
        if ($cleanSlug === '') {
            return null;
        }

        // CRITICAL RULE 1: Strict Skipped Series Exclusion (0 API calls & immediate null)
        if ($this->isSeriesSkipped($cleanSlug)) {
            return null;
        }

        $cacheKey = "cricket:redis:series:{$cleanSlug}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        return Cache::lock("lock:cricket:redis:series:{$cleanSlug}", 10)->block(5, function () use ($cleanSlug, $cacheKey) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }

            // Gather all available matches from fast home catalog
            $homeData = $this->getFastHomeData();
            $allLive = $homeData['live'] ?? [];
            $allUpcoming = $homeData['upcoming'] ?? [];
            $allRecent = $homeData['recent'] ?? $homeData['finished'] ?? [];

            // Excluded managed match IDs
            $uncoveredMatchIds = class_exists(\App\Models\ManagedMatch::class)
                ? \App\Models\ManagedMatch::where('is_covered', false)->pluck('match_id')->flip()->all()
                : [];

            $matchesFilter = function (array $list) use ($cleanSlug, $uncoveredMatchIds): array {
                $filtered = [];
                foreach ($list as $m) {
                    $mId = $m['match_id'] ?? $m['id'] ?? null;
                    if ($mId && isset($uncoveredMatchIds[$mId])) {
                        continue;
                    }
                    $sName = $m['series_name'] ?? $m['series'] ?? '';
                    if (\Illuminate\Support\Str::slug($sName) === $cleanSlug) {
                        $filtered[] = $m;
                    }
                }
                return $filtered;
            };

            $liveMatches = $matchesFilter($allLive);
            $upcomingMatches = $matchesFilter($allUpcoming);
            $recentMatches = $matchesFilter($allRecent);

            // Deduplicate matches by ID across buckets
            $seenMatchIds = [];
            $dedupe = function (array $matches) use (&$seenMatchIds): array {
                $out = [];
                foreach ($matches as $m) {
                    $id = $m['match_id'] ?? $m['id'] ?? null;
                    if ($id) {
                        if (isset($seenMatchIds[$id])) continue;
                        $seenMatchIds[$id] = true;
                    }
                    $out[] = $m;
                }
                return $out;
            };

            $liveMatches = $dedupe($liveMatches);
            $upcomingMatches = $dedupe($upcomingMatches);
            $recentMatches = $dedupe($recentMatches);

            // Resolve series metadata (series_name, series_id, date_range, format)
            $seriesName = null;
            $seriesId = null;
            $format = 'T20';
            $dateRange = null;

            // Check if matches have series name
            $allFound = array_merge($liveMatches, $upcomingMatches, $recentMatches);
            if (!empty($allFound)) {
                $seriesName = $allFound[0]['series_name'] ?? $allFound[0]['series'] ?? null;
                $seriesId = $allFound[0]['series_id'] ?? null;
                $format = $allFound[0]['format'] ?? 'T20';
            }

            // Check SkippedSeries table if name not resolved yet
            if (!$seriesName) {
                $unskippedRecord = \App\Models\SkippedSeries::where('is_skipped', false)->get()
                    ->first(fn ($s) => \Illuminate\Support\Str::slug($s->series_name) === $cleanSlug);
                if ($unskippedRecord) {
                    $seriesName = $unskippedRecord->series_name;
                    $format = $unskippedRecord->format ?: 'T20';
                }
            }

            // Check cached /cricket/series/all list
            $apiSeriesData = $this->getSeriesByCategory('all');
            if (isset($apiSeriesData['months']) && is_array($apiSeriesData['months'])) {
                foreach ($apiSeriesData['months'] as $monthBlock) {
                    foreach ($monthBlock['series'] ?? [] as $s) {
                        $sTitle = trim((string) ($s['name'] ?? ''));
                        if (\Illuminate\Support\Str::slug($sTitle) === $cleanSlug) {
                            if (!$seriesName) $seriesName = $sTitle;
                            if (!$seriesId && !empty($s['id'])) $seriesId = $s['id'];
                            if (!empty($s['start_date']) && !empty($s['end_date'])) {
                                try {
                                    $d1 = \Carbon\Carbon::parse($s['start_date'])->format('M j');
                                    $d2 = \Carbon\Carbon::parse($s['end_date'])->format('M j, Y');
                                    $dateRange = "{$d1} - {$d2}";
                                } catch (\Throwable $e) {
                                    $dateRange = "{$s['start_date']} to {$s['end_date']}";
                                }
                            }
                            break 2;
                        }
                    }
                }
            }

            // If not found anywhere, return null
            if (!$seriesName && empty($allFound)) {
                return null;
            }

            if (!$seriesName) {
                $seriesName = ucwords(str_replace('-', ' ', $cleanSlug));
            }

            // Compute summary line
            $liveCount = count($liveMatches);
            $upcomingCount = count($upcomingMatches);
            $completedCount = count($recentMatches);

            $summaryParts = [];
            if ($liveCount > 0) $summaryParts[] = "{$liveCount} Live";
            if ($upcomingCount > 0) $summaryParts[] = "{$upcomingCount} Upcoming";
            if ($completedCount > 0) $summaryParts[] = "{$completedCount} Completed";
            $summaryLine = !empty($summaryParts) ? implode(' • ', $summaryParts) : 'No matches scheduled';

            $data = [
                'series_id' => $seriesId,
                'series_name' => $seriesName,
                'series_slug' => $cleanSlug,
                'format' => $format,
                'date_range' => $dateRange,
                'summary_line' => $summaryLine,
                'live_count' => $liveCount,
                'upcoming_count' => $upcomingCount,
                'completed_count' => $completedCount,
                'is_live' => $liveCount > 0,
                'matches' => [
                    'live' => $liveMatches,
                    'upcoming' => $upcomingMatches,
                    'completed' => $recentMatches,
                ],
            ];

            // Never cache empty invalid responses
            $ttl = ($liveCount > 0) ? 60 : 900;
            Cache::put($cacheKey, $data, $ttl);

            return $data;
        });
    }

    /**
     * Get Points Table for a Cricket Series.
     * Respects 3+ teams rule. Returns [] for bilateral tours or fewer than 3 teams.
     */
    public function getSeriesPointsTable(string|int|null $seriesId, string $seriesSlug): array
    {
        $cleanSlug = \Illuminate\Support\Str::slug(trim($seriesSlug));
        if ($cleanSlug === '') {
            return [];
        }

        $cacheKey = "cricket:redis:series:{$cleanSlug}:points";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        return Cache::lock("lock:cricket:redis:series:{$cleanSlug}:points", 10)->block(5, function () use ($seriesId, $cleanSlug, $cacheKey) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }

            $rawRows = [];

            // 1. Try upstream points table if seriesId is present
            if ($seriesId && !$this->isEcoModeActive() && !$this->isHardStopActive() && !app()->bound('crawler_read_only_mode')) {
                $res = $this->get("/cricket/series/{$seriesId}/points-table");
                $data = $res['data'] ?? $res['points_table'] ?? $res['standings'] ?? (is_array($res) && array_is_list($res) ? $res : []);
                if (is_array($data) && !empty($data)) {
                    $rawRows = $data;
                }
            }

            $table = [];

            // If upstream had structured rows
            if (!empty($rawRows)) {
                $rank = 1;
                foreach ($rawRows as $row) {
                    $teamName = $row['team_name'] ?? $row['team'] ?? $row['name'] ?? 'Team';
                    $short = $row['team_short'] ?? $row['short_name'] ?? $this->generateTeamShort($teamName);
                    $table[] = [
                        'rank' => (int) ($row['rank'] ?? $row['position'] ?? $rank++),
                        'team' => $teamName,
                        'team_short' => $short,
                        'played' => (int) ($row['played'] ?? $row['p'] ?? $row['matches'] ?? 0),
                        'won' => (int) ($row['won'] ?? $row['w'] ?? 0),
                        'lost' => (int) ($row['lost'] ?? $row['l'] ?? 0),
                        'nr' => (int) ($row['nr'] ?? $row['no_result'] ?? $row['tied'] ?? 0),
                        'points' => (int) ($row['points'] ?? $row['pts'] ?? 0),
                        'nrr' => (string) ($row['nrr'] ?? $row['net_run_rate'] ?? '0.000'),
                    ];
                }
            } else {
                // Synthesize standings from all recent & completed matches for this series
                $hubData = $this->getSeriesHubData($cleanSlug);
                $completed = $hubData['matches']['completed'] ?? [];

                // Track all teams playing in this series
                $teamStats = [];
                foreach ($completed as $m) {
                    $tA = trim((string) ($m['team_a'] ?? ''));
                    $tB = trim((string) ($m['team_b'] ?? ''));
                    if ($tA === '' || $tB === '') continue;

                    if (!isset($teamStats[$tA])) {
                        $teamStats[$tA] = [
                            'team' => $tA,
                            'team_short' => $m['team_a_short'] ?? $this->generateTeamShort($tA),
                            'played' => 0,
                            'won' => 0,
                            'lost' => 0,
                            'nr' => 0,
                            'points' => 0,
                            'nrr' => '0.000',
                        ];
                    }
                    if (!isset($teamStats[$tB])) {
                        $teamStats[$tB] = [
                            'team' => $tB,
                            'team_short' => $m['team_b_short'] ?? $this->generateTeamShort($tB),
                            'played' => 0,
                            'won' => 0,
                            'lost' => 0,
                            'nr' => 0,
                            'points' => 0,
                            'nrr' => '0.000',
                        ];
                    }

                    $teamStats[$tA]['played']++;
                    $teamStats[$tB]['played']++;

                    $result = strtolower((string) ($m['result'] ?? ''));
                    $winner = $m['winner'] ?? null;

                    if ($winner && strtolower($winner) === strtolower($tA)) {
                        $teamStats[$tA]['won']++;
                        $teamStats[$tA]['points'] += 2;
                        $teamStats[$tB]['lost']++;
                    } elseif ($winner && strtolower($winner) === strtolower($tB)) {
                        $teamStats[$tB]['won']++;
                        $teamStats[$tB]['points'] += 2;
                        $teamStats[$tA]['lost']++;
                    } elseif (str_contains($result, strtolower($tA) . ' won') || str_contains($result, strtolower($teamStats[$tA]['team_short']) . ' won')) {
                        $teamStats[$tA]['won']++;
                        $teamStats[$tA]['points'] += 2;
                        $teamStats[$tB]['lost']++;
                    } elseif (str_contains($result, strtolower($tB) . ' won') || str_contains($result, strtolower($teamStats[$tB]['team_short']) . ' won')) {
                        $teamStats[$tB]['won']++;
                        $teamStats[$tB]['points'] += 2;
                        $teamStats[$tA]['lost']++;
                    } elseif (str_contains($result, 'tied') || str_contains($result, 'draw') || str_contains($result, 'no result') || str_contains($result, 'abandoned')) {
                        $teamStats[$tA]['nr']++;
                        $teamStats[$tA]['points'] += 1;
                        $teamStats[$tB]['nr']++;
                        $teamStats[$tB]['points'] += 1;
                    }
                }

                // If not enough completed matches, check upcoming to see all participating teams
                if (count($teamStats) < 3) {
                    $upcoming = $hubData['matches']['upcoming'] ?? [];
                    foreach ($upcoming as $m) {
                        $tA = trim((string) ($m['team_a'] ?? ''));
                        $tB = trim((string) ($m['team_b'] ?? ''));
                        if ($tA !== '' && !isset($teamStats[$tA])) {
                            $teamStats[$tA] = [
                                'team' => $tA,
                                'team_short' => $m['team_a_short'] ?? $this->generateTeamShort($tA),
                                'played' => 0, 'won' => 0, 'lost' => 0, 'nr' => 0, 'points' => 0, 'nrr' => '0.000',
                            ];
                        }
                        if ($tB !== '' && !isset($teamStats[$tB])) {
                            $teamStats[$tB] = [
                                'team' => $tB,
                                'team_short' => $m['team_b_short'] ?? $this->generateTeamShort($tB),
                                'played' => 0, 'won' => 0, 'lost' => 0, 'nr' => 0, 'points' => 0, 'nrr' => '0.000',
                            ];
                        }
                    }
                }

                if (count($teamStats) >= 3) {
                    // Sort by points desc, then won desc
                    $rows = array_values($teamStats);
                    usort($rows, function ($a, $b) {
                        if ($a['points'] !== $b['points']) {
                            return $b['points'] <=> $a['points'];
                        }
                        return $b['won'] <=> $a['won'];
                    });

                    $rank = 1;
                    foreach ($rows as $r) {
                        $r['rank'] = $rank++;
                        $table[] = $r;
                    }
                }
            }

            // CRITICAL RULE 2: Only 3 or more teams. Return [] for bilateral tours
            if (count($table) < 3) {
                return [];
            }

            Cache::put($cacheKey, $table, 1800); // 30 min TTL
            return $table;
        });
    }

    /**
     * Get Squads for a Series.
     * Cached in Redis 86400s (24h).
     */
    public function getSeriesSquads(string|int|null $seriesId, string $seriesSlug): array
    {
        $cleanSlug = \Illuminate\Support\Str::slug(trim($seriesSlug));
        if ($cleanSlug === '') {
            return [];
        }

        $cacheKey = "cricket:redis:series:{$cleanSlug}:squads";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        return Cache::lock("lock:cricket:redis:series:{$cleanSlug}:squads", 10)->block(5, function () use ($cleanSlug, $cacheKey) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }

            $hubData = $this->getSeriesHubData($cleanSlug);
            if (!$hubData) {
                return [];
            }

            $allMatches = array_merge(
                $hubData['matches']['live'] ?? [],
                $hubData['matches']['upcoming'] ?? [],
                $hubData['matches']['completed'] ?? []
            );

            $teamsMap = [];

            // Extract squads from matches belonging to this series
            foreach ($allMatches as $match) {
                $mId = $match['match_id'] ?? $match['id'] ?? null;
                if (!$mId) continue;

                $squadData = $this->getSquads($mId);
                if (empty($squadData)) continue;

                foreach (['team_a', 'team_b'] as $side) {
                    $tInfo = $squadData[$side] ?? null;
                    if (!$tInfo || empty($tInfo['name'])) continue;

                    $tName = $tInfo['name'];
                    if (!isset($teamsMap[$tName])) {
                        $players = [];
                        foreach (array_merge($tInfo['playing_xi'] ?? [], $tInfo['bench'] ?? []) as $p) {
                            $pId = $p['id'] ?? $p['name'] ?? null;
                            if (!$pId) continue;
                            $players[$pId] = [
                                'id' => $p['id'] ?? null,
                                'name' => $p['name'] ?? $p['short_name'] ?? 'Player',
                                'role' => $p['role'] ?? 'Batter',
                                'batting_style' => $p['batting_style'] ?? '',
                                'bowling_style' => $p['bowling_style'] ?? '',
                                'is_captain' => (bool) ($p['is_captain'] ?? false),
                                'is_keeper' => (bool) ($p['is_keeper'] ?? false),
                                'image_url' => $p['player_image_url'] ?? null,
                            ];
                        }

                        $teamsMap[$tName] = [
                            'team_name' => $tName,
                            'team_short' => $tInfo['short'] ?? $this->generateTeamShort($tName),
                            'players' => array_values($players),
                        ];
                    }
                }

                // If we already collected sufficient teams, break early
                if (count($teamsMap) >= 10) break;
            }

            $result = array_values($teamsMap);
            if (!empty($result)) {
                Cache::put($cacheKey, $result, 86400); // 24h TTL
            }

            return $result;
        });
    }
}
