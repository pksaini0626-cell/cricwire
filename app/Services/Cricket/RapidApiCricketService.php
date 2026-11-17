<?php

namespace App\Services\Cricket;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RapidApiCricketService
{
    protected string $apiKey;
    protected string $apiHost;
    protected string $baseUrl;

    public function __construct(
        ?string $apiKey = null,
        ?string $apiHost = null,
        ?string $baseUrl = null
    ) {
        $this->apiKey = $apiKey ?? (string) config('services.rapidapi_cricket.key');
        $this->apiHost = $apiHost ?? (string) config('services.rapidapi_cricket.host', 'cricket-live-line1.p.rapidapi.com');
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.rapidapi_cricket.base_url', 'https://cricket-live-line1.p.rapidapi.com'), '/');
    }

    /**
     * Get home matches (live, upcoming, finished) cached for 45 seconds.
     * Returns array of match objects.
     *
     * @return array
     */
    public function getHomeMatches(): array
    {
        return Cache::remember('cricket:home_matches', 45, function () {
            $resp = $this->fetchFromApi('home');
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get live matches cached for 15 seconds.
     *
     * @return array
     */
    public function getLiveMatches(): array
    {
        return Cache::remember('cricket:live_matches', 15, function () {
            $resp = $this->fetchFromApi('liveMatches');
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get upcoming matches cached for 300 seconds (5 minutes).
     *
     * @return array
     */
    public function getUpcomingMatches(): array
    {
        return Cache::remember('cricket:upcoming_matches', 300, function () {
            $resp = $this->fetchFromApi('upcomingMatches');
            $data = $this->extractDataArray($resp);
            return $this->filterMatchesByDateWindow($data, 'upcoming');
        }) ?? [];
    }

    /**
     * Get recent/finished matches cached for 300 seconds (5 minutes).
     *
     * @return array
     */
    public function getRecentMatches(): array
    {
        return Cache::remember('cricket:recent_matches', 300, function () {
            $resp = $this->fetchFromApi('recentMatches');
            $data = $this->extractDataArray($resp);
            return $this->filterMatchesByDateWindow($data, 'recent');
        }) ?? [];
    }

    /**
     * Get schedule matches categorized as upcoming and recent cached for 10 minutes (600 seconds).
     *
     * @return array{upcoming: array, recent: array}
     */
    public function getScheduleMatches(): array
    {
        return Cache::remember('cricket:schedule_matches', 600, function () {
            $upcoming = $this->getUpcomingMatches();
            $recent = $this->getRecentMatches();

            // Fallback: Partition matches from getHomeMatches() if dedicated endpoints return empty
            if (empty($upcoming) && empty($recent)) {
                $home = $this->getHomeMatches();
                if (is_array($home)) {
                    foreach ($home as $match) {
                        if (!is_array($match)) {
                            continue;
                        }

                        $status = strtolower(trim((string) ($match['match_status'] ?? $match['status'] ?? '')));
                        $result = trim((string) ($match['result'] ?? ''));

                        if ($status === 'finished' || $status === 'completed' || $status === 'result' || !empty($result)) {
                            $recent[] = $match;
                        } elseif ($status === 'upcoming' || $status === 'scheduled' || $status === 'not started') {
                            $upcoming[] = $match;
                        } elseif ($status !== 'live' && $status !== 'in progress' && $status !== 'innings break') {
                            $upcoming[] = $match;
                        }
                    }
                }
            }

            return [
                'upcoming' => $this->filterMatchesByDateWindow(array_values($upcoming), 'upcoming'),
                'recent' => $this->filterMatchesByDateWindow(array_values($recent), 'recent'),
            ];
        }) ?? ['upcoming' => [], 'recent' => []];
    }

    /**
     * Get single match detail/info cached for 120 seconds.
     * Calls GET /match/{matchId}/info (with fallback to /match/{matchId}).
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchInfo(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:info", 120, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/info");
            $data = $this->extractDataArray($resp);

            // Fallback to /match/{matchId} if /info is empty
            if (empty($data)) {
                $fallback = $this->fetchFromApi("match/{$matchId}");
                $data = $this->extractDataArray($fallback);
            }

            return $data;
        }) ?? [];
    }

    /**
     * Backward-compatible alias for getMatchInfo.
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchDetail(string|int $matchId): array
    {
        return $this->getMatchInfo($matchId);
    }

    /**
     * Get match live line cached for 10 seconds.
     * Calls GET /match/{matchId}/live (with fallback to /match/{matchId}/liveLine).
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchLiveLine(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:live_line", 10, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/live");
            $data = $this->extractDataArray($resp);

            // Fallback to /match/{matchId}/liveLine if empty
            if (empty($data)) {
                $fallback = $this->fetchFromApi("match/{$matchId}/liveLine");
                $data = $this->extractDataArray($fallback);
            }

            return $data;
        }) ?? [];
    }

    /**
     * Get match scorecard cached for 20 seconds.
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchScorecard(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:scorecard", 20, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/scorecard");
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get match Playing XI cached for 300 seconds (5 minutes).
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchPlayingXI(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:playing_xi", 300, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/playingXI");
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get match impact players cached for 300 seconds (5 minutes).
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchImpactPlayers(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:impact_players", 300, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/impactPlayers");
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get match tracker stats (total_runs, total_wkts, total_fours, total_sixes, etc.) cached for 60 seconds.
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchTracker(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:tracker", 60, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/matchTracker");
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get match commentary cached for 15 seconds.
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchCommentary(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:commentary", 15, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/commentary");
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get match fancy/session data cached for 15 seconds.
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchFancy(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:fancy", 15, function () use ($matchId) {
            $data = $this->fetchFromApi("match/{$matchId}/fancy");
            Log::info("RapidApiCricketService: Fancy response for match {$matchId}", [
                'has_data' => !empty($data['data']),
                'count' => is_array($data['data'] ?? null) ? count($data['data']) : 0,
                'status' => $data['status'] ?? null,
            ]);
            return $data;
        }) ?? [];
    }

    /**
     * Get match over history cached for 20 seconds.
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchOverHistory(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:over_history", 20, function () use ($matchId) {
            $data = $this->fetchFromApi("match/{$matchId}/overHistory");
            Log::info("RapidApiCricketService: OverHistory response for match {$matchId}", [
                'has_data' => !empty($data['data']),
                'status' => $data['status'] ?? null,
            ]);
            return $data;
        }) ?? [];
    }

    /**
     * Helper to safely extract payload data array from upstream response envelope.
     */
    protected function extractDataArray(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        return is_array($response) ? $response : [];
    }

    /**
     * Perform HTTP GET request to upstream RapidAPI with timeout, retries, and error handling.
     *
     * @param string $endpoint
     * @param array $queryParams
     * @return array
     */
    protected function fetchFromApi(string $endpoint, array $queryParams = []): array
    {
        if (empty($this->apiKey)) {
            Log::warning('RapidApiCricketService: RapidAPI key is missing or not configured.');
            return [];
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        try {
            $response = Http::withHeaders([
                'x-rapidapi-key' => $this->apiKey,
                'x-rapidapi-host' => $this->apiHost,
                'Accept' => 'application/json',
            ])
            ->timeout(8)
            ->retry(2, 200, function ($exception) {
                return true;
            }, throw: false)
            ->get($url, $queryParams);

            if ($response->successful()) {
                $payload = $response->json();
                return is_array($payload) ? $payload : [];
            }

            Log::error('RapidApiCricketService: Upstream API returned non-200 status code', [
                'status' => $response->status(),
                'url' => $url,
                'response' => $response->body(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('RapidApiCricketService: HTTP exception while calling RapidAPI', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Filter and cap matches by date window and limit.
     * Upcoming: next 3 days, sorted chronologically (earliest first), max 15-20 (fallback: nearest 10).
     * Recent: previous 2 days, sorted reverse-chronologically (latest first), max 15-20 (fallback: latest 10).
     *
     * @param array $matches
     * @param string $type 'upcoming'|'recent'
     * @param int $fallbackLimit
     * @return array
     */
    public function filterMatchesByDateWindow(array $matches, string $type = 'upcoming', int $fallbackLimit = 10): array
    {
        if (empty($matches)) {
            return [];
        }

        $now = \Carbon\Carbon::now('Asia/Kolkata');
        $windowStart = $type === 'upcoming'
            ? $now->copy()->subHours(2) // include matches starting very soon
            : $now->copy()->subDays(2)->startOfDay();

        $windowEnd = $type === 'upcoming'
            ? $now->copy()->addDays(3)->endOfDay()
            : $now->copy()->endOfDay();

        $filtered = collect($matches)->filter(function ($match) use ($windowStart, $windowEnd) {
            if (!is_array($match)) {
                return false;
            }

            $rawDate = $match['date_wise']
                ?? $match['match_date']
                ?? $match['start_date']
                ?? $match['date']
                ?? $match['dateTimeGMT']
                ?? null;

            if (empty($rawDate)) {
                return false;
            }

            try {
                if (is_numeric($rawDate)) {
                    $ts = (int) $rawDate;
                    if (strlen((string) $rawDate) > 10) {
                        $ts = (int) ($ts / 1000);
                    }
                    $parsedDate = \Carbon\Carbon::createFromTimestamp($ts, 'Asia/Kolkata');
                } else {
                    $rawTime = $match['match_time'] ?? $match['time'] ?? '';
                    $dateString = trim("{$rawDate} {$rawTime}");
                    try {
                        $parsedDate = \Carbon\Carbon::parse($dateString, 'Asia/Kolkata');
                    } catch (\Throwable) {
                        $parsedDate = \Carbon\Carbon::parse((string) $rawDate, 'Asia/Kolkata');
                    }
                }

                return $parsedDate->between($windowStart, $windowEnd);
            } catch (\Throwable $e) {
                return false;
            }
        });

        // Smart Fallback: If date parsing yields < 3 items (e.g. off-season or non-standard date format),
        // return the first $fallbackLimit (10) items from the API list so the tab is never blank.
        if ($filtered->count() < 3) {
            return collect($matches)->take($fallbackLimit)->values()->all();
        }

        // Sort: upcoming chronologically (earliest first), recent reverse-chronologically (latest first)
        if ($type === 'upcoming') {
            $filtered = $filtered->sortBy(function ($match) {
                $rawDate = $match['date_wise'] ?? $match['match_date'] ?? $match['start_date'] ?? $match['date'] ?? null;
                try {
                    if (is_numeric($rawDate)) {
                        $ts = (int) $rawDate;
                        return strlen((string) $rawDate) > 10 ? (int) ($ts / 1000) : $ts;
                    }
                    $rawTime = $match['match_time'] ?? $match['time'] ?? '';
                    return \Carbon\Carbon::parse(trim("{$rawDate} {$rawTime}"), 'Asia/Kolkata')->timestamp;
                } catch (\Throwable) {
                    return PHP_INT_MAX;
                }
            });
        } else {
            $filtered = $filtered->sortByDesc(function ($match) {
                $rawDate = $match['date_wise'] ?? $match['match_date'] ?? $match['start_date'] ?? $match['date'] ?? null;
                try {
                    if (is_numeric($rawDate)) {
                        $ts = (int) $rawDate;
                        return strlen((string) $rawDate) > 10 ? (int) ($ts / 1000) : $ts;
                    }
                    $rawTime = $match['match_time'] ?? $match['time'] ?? '';
                    return \Carbon\Carbon::parse(trim("{$rawDate} {$rawTime}"), 'Asia/Kolkata')->timestamp;
                } catch (\Throwable) {
                    return 0;
                }
            });
        }

        // Cap at max 20 items even on busy weekend tournament days to keep DOM fast
        return $filtered->take(20)->values()->all();
    }
}
