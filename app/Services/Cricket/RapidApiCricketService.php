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
     * Get home matches (live, upcoming, finished).
     *
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getHomeMatches(bool $allowUpstreamFetch = true): array
    {
        $key = 'cricket:home_matches';

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi('home');
            $data = $this->extractDataArray($resp);
            Cache::put($key, $data, now()->addMinutes(30));
            return $data;
        }

        return Cache::remember($key, 45, function () {
            $resp = $this->fetchFromApi('home');
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get live matches.
     *
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getLiveMatches(bool $allowUpstreamFetch = true): array
    {
        $key = 'cricket:live_matches';

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi('liveMatches');
            $data = $this->extractDataArray($resp);
            Cache::put($key, $data, now()->addMinutes(30));
            return $data;
        }

        return Cache::remember($key, 15, function () {
            $resp = $this->fetchFromApi('liveMatches');
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get upcoming matches.
     *
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getUpcomingMatches(bool $allowUpstreamFetch = true): array
    {
        $key = 'cricket:upcoming_matches';

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi('upcomingMatches');
            $data = $this->extractDataArray($resp);
            $filtered = $this->filterMatchesByDateWindow($data, 'upcoming');
            Cache::put($key, $filtered, now()->addMinutes(30));
            return $filtered;
        }

        return Cache::remember($key, 300, function () {
            $resp = $this->fetchFromApi('upcomingMatches');
            $data = $this->extractDataArray($resp);
            return $this->filterMatchesByDateWindow($data, 'upcoming');
        }) ?? [];
    }

    /**
     * Get recent/finished matches.
     *
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getRecentMatches(bool $allowUpstreamFetch = true): array
    {
        $key = 'cricket:recent_matches';

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi('recentMatches');
            $data = $this->extractDataArray($resp);
            $filtered = $this->filterMatchesByDateWindow($data, 'recent');
            Cache::put($key, $filtered, now()->addMinutes(30));
            return $filtered;
        }

        return Cache::remember($key, 300, function () {
            $resp = $this->fetchFromApi('recentMatches');
            $data = $this->extractDataArray($resp);
            return $this->filterMatchesByDateWindow($data, 'recent');
        }) ?? [];
    }

    /**
     * Get schedule matches categorized as upcoming and recent.
     *
     * @param bool $allowUpstreamFetch
     * @return array{upcoming: array, recent: array}
     */
    public function getScheduleMatches(bool $allowUpstreamFetch = true): array
    {
        $key = 'cricket:schedule_matches';

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, ['upcoming' => [], 'recent' => []]);
            }
        }

        return Cache::remember($key, $allowUpstreamFetch ? 600 : 1800, function () use ($allowUpstreamFetch) {
            $upcoming = $this->getUpcomingMatches($allowUpstreamFetch);
            $recent = $this->getRecentMatches($allowUpstreamFetch);

            // Fallback: Partition matches from getHomeMatches() if dedicated endpoints return empty
            if (empty($upcoming) && empty($recent)) {
                $home = $this->getHomeMatches($allowUpstreamFetch);
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
     * Get single match detail/info.
     * Calls GET /match/{matchId}/info (with fallback to /match/{matchId}).
     *
     * @param string|int $matchId
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchInfo(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        $key = "cricket:match:{$matchId}:info";

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi("match/{$matchId}/info");
            $data = $this->extractDataArray($resp);
            if (empty($data)) {
                $fallback = $this->fetchFromApi("match/{$matchId}");
                $data = $this->extractDataArray($fallback);
            }
            Cache::put($key, $data, now()->addMinutes(30));
            return $data;
        }

        return Cache::remember($key, 300, function () use ($matchId) {
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
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchDetail(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        return $this->getMatchInfo($matchId, $allowUpstreamFetch);
    }

    /**
     * Get match live line.
     * Calls GET /match/{matchId}/live (with fallback to /match/{matchId}/liveLine).
     *
     * @param string|int $matchId
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchLiveLine(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        $key = "cricket:match:{$matchId}:live";
        $secondaryKey = "cricket:match:{$matchId}:live_line";

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            if (Cache::has($secondaryKey)) {
                return (array) Cache::get($secondaryKey, []);
            }
            $resp = $this->fetchFromApi("match/{$matchId}/live");
            $data = $this->extractDataArray($resp);
            if (empty($data)) {
                $fallback = $this->fetchFromApi("match/{$matchId}/liveLine");
                $data = $this->extractDataArray($fallback);
            }
            Cache::put($key, $data, now()->addMinutes(30));
            Cache::put($secondaryKey, $data, now()->addMinutes(30));
            return $data;
        }

        return Cache::remember($key, 60, function () use ($matchId, $secondaryKey) {
            $resp = $this->fetchFromApi("match/{$matchId}/live");
            $data = $this->extractDataArray($resp);

            // Fallback to /match/{matchId}/liveLine if empty
            if (empty($data)) {
                $fallback = $this->fetchFromApi("match/{$matchId}/liveLine");
                $data = $this->extractDataArray($fallback);
            }

            if (!empty($data)) {
                Cache::put($secondaryKey, $data, 60);
            }

            return $data;
        }) ?? [];
    }

    /**
     * Get match scorecard.
     *
     * @param string|int $matchId
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchScorecard(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        $key = "cricket:match:{$matchId}:scorecard";

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi("match/{$matchId}/scorecard");
            $data = $this->extractDataArray($resp);
            Cache::put($key, $data, now()->addMinutes(30));
            return $data;
        }

        return Cache::remember($key, 50, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/scorecard");
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get match Playing XI.
     *
     * @param string|int $matchId
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchPlayingXI(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        $key = "cricket:match:{$matchId}:playing_xi";

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi("match/{$matchId}/playingXI");
            $data = $this->extractDataArray($resp);
            $ttl = !empty($data) ? 86400 : 300;
            Cache::put($key, $data, $ttl);
            return $data;
        }

        if (Cache::has($key)) {
            return (array) Cache::get($key, []);
        }

        $resp = $this->fetchFromApi("match/{$matchId}/playingXI");
        $data = $this->extractDataArray($resp);
        $ttl = !empty($data) ? 86400 : 300;
        Cache::put($key, $data, $ttl);

        return $data;
    }

    /**
     * Get match impact players.
     *
     * @param string|int $matchId
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchImpactPlayers(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        $key = "cricket:match:{$matchId}:impact_players";

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi("match/{$matchId}/impactPlayers");
            $data = $this->extractDataArray($resp);
            $ttl = !empty($data) ? 86400 : 300;
            Cache::put($key, $data, $ttl);
            return $data;
        }

        if (Cache::has($key)) {
            return (array) Cache::get($key, []);
        }

        $resp = $this->fetchFromApi("match/{$matchId}/impactPlayers");
        $data = $this->extractDataArray($resp);
        $ttl = !empty($data) ? 86400 : 300;
        Cache::put($key, $data, $ttl);

        return $data;
    }

    /**
     * Get match tracker stats (total_runs, total_wkts, total_fours, total_sixes, etc.).
     *
     * @param string|int $matchId
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchTracker(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        $key = "cricket:match:{$matchId}:tracker";

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi("match/{$matchId}/matchTracker");
            $data = $this->extractDataArray($resp);
            Cache::put($key, $data, now()->addMinutes(30));
            return $data;
        }

        return Cache::remember($key, 60, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/matchTracker");
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get match commentary.
     *
     * @param string|int $matchId
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchCommentary(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        $key = "cricket:match:{$matchId}:commentary";

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $resp = $this->fetchFromApi("match/{$matchId}/commentary");
            $data = $this->extractDataArray($resp);
            Cache::put($key, $data, now()->addMinutes(30));
            return $data;
        }

        return Cache::remember($key, 15, function () use ($matchId) {
            $resp = $this->fetchFromApi("match/{$matchId}/commentary");
            return $this->extractDataArray($resp);
        }) ?? [];
    }

    /**
     * Get match fancy/session data.
     *
     * @param string|int $matchId
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchFancy(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        $key = "cricket:match:{$matchId}:fancy";

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $data = $this->fetchFromApi("match/{$matchId}/fancy");
            Cache::put($key, $data, now()->addMinutes(30));
            return $data;
        }

        return Cache::remember($key, 60, function () use ($matchId) {
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
     * Get match over history.
     *
     * @param string|int $matchId
     * @param bool $allowUpstreamFetch
     * @return array
     */
    public function getMatchOverHistory(string|int $matchId, bool $allowUpstreamFetch = true): array
    {
        $key = "cricket:match:{$matchId}:over_history";

        if (!$allowUpstreamFetch) {
            if (Cache::has($key)) {
                return (array) Cache::get($key, []);
            }
            $data = $this->fetchFromApi("match/{$matchId}/overHistory");
            Cache::put($key, $data, now()->addMinutes(30));
            return $data;
        }

        return Cache::remember($key, 45, function () use ($matchId) {
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