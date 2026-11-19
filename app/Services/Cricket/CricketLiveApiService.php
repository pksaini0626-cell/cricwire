<?php

namespace App\Services\Cricket;

use App\Models\ManagedMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        return $output;
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

        return $output;
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

        return $output;
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
        return [
            'match_id' => (int) $matchId,
            'team_a' => [
                'name' => $data['team_a']['name'] ?? $data['team1']['name'] ?? 'Team A',
                'playing_xi' => $data['team_a']['playing_xi'] ?? $data['team1']['playing_xi'] ?? $data['team_a']['squad'] ?? [],
                'bench' => $data['team_a']['bench'] ?? $data['team1']['bench'] ?? [],
            ],
            'team_b' => [
                'name' => $data['team_b']['name'] ?? $data['team2']['name'] ?? 'Team B',
                'playing_xi' => $data['team_b']['playing_xi'] ?? $data['team2']['playing_xi'] ?? $data['team_b']['squad'] ?? [],
                'bench' => $data['team_b']['bench'] ?? $data['team2']['bench'] ?? [],
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
     * Upserts live, upcoming, and recent matches into managed_matches table
     * (preserving existing is_covered admin choices!)
     */
    public function syncMatchCatalog(): void
    {
        $live = $this->getLiveMatches();
        $upcoming = $this->getUpcomingMatches();
        $recent = $this->getRecentMatches();

        $all = [
            ...$live,
            ...$upcoming,
            ...$recent,
        ];

        foreach ($all as $m) {
            $matchId = (int) ($m['match_id'] ?? 0);
            if ($matchId <= 0) {
                continue;
            }

            // Find existing to preserve is_covered choice
            $existing = ManagedMatch::where('match_id', $matchId)->first();

            ManagedMatch::updateOrCreate(
                ['match_id' => $matchId],
                [
                    'series_name' => $m['series_name'] ?? null,
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
    }
}
