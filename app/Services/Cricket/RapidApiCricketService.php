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
     * Get home matches (live, upcoming, finished) cached for 60 seconds.
     *
     * @return array
     */
    public function getHomeMatches(): array
    {
        return Cache::remember('cricket:home_matches', 60, function () {
            return $this->fetchFromApi('home');
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
            // Attempt dedicated upcoming matches endpoint first
            $upcomingResponse = $this->fetchFromApi('matches/upcoming');
            
            $upcoming = [];
            $recent = [];

            if (!empty($upcomingResponse) && (isset($upcomingResponse['data']) || isset($upcomingResponse['matches']))) {
                $upcoming = $upcomingResponse['data'] ?? $upcomingResponse['matches'] ?? [];
                
                // Also fetch recent/finished matches
                $recentResponse = $this->fetchFromApi('matches/recent');
                if (empty($recentResponse)) {
                    $recentResponse = $this->fetchFromApi('matches/finished');
                }
                $recent = $recentResponse['data'] ?? $recentResponse['matches'] ?? [];
            }

            // Fallback: Partition matches from getHomeMatches() if dedicated endpoints return empty
            if (empty($upcoming) && empty($recent)) {
                $home = $this->getHomeMatches();

                // Check pre-partitioned keys first
                if (!empty($home['upcoming_matches']) && is_array($home['upcoming_matches'])) {
                    $upcoming = $home['upcoming_matches'];
                }
                if (!empty($home['finished_matches']) && is_array($home['finished_matches'])) {
                    $recent = $home['finished_matches'];
                } elseif (!empty($home['recent_matches']) && is_array($home['recent_matches'])) {
                    $recent = $home['recent_matches'];
                }

                // If items are in unified 'data' or 'matches' array, categorize them
                $unifiedMatches = $home['data'] ?? $home['matches'] ?? (is_array($home) && isset($home[0]) ? $home : []);
                if (is_array($unifiedMatches)) {
                    foreach ($unifiedMatches as $match) {
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
                'upcoming' => array_values($upcoming),
                'recent' => array_values($recent),
            ];
        }) ?? ['upcoming' => [], 'recent' => []];
    }

    /**
     * Get single match detail cached for 15 seconds.
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchDetail(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}", 15, function () use ($matchId) {
            return $this->fetchFromApi("match/{$matchId}");
        }) ?? [];
    }

    /**
     * Get match scorecard cached for 15 seconds.
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchScorecard(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:scorecard", 15, function () use ($matchId) {
            return $this->fetchFromApi("match/{$matchId}/scorecard");
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
            return $this->fetchFromApi("match/{$matchId}/commentary");
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
     * Get match over history cached for 15 seconds.
     *
     * @param string|int $matchId
     * @return array
     */
    public function getMatchOverHistory(string|int $matchId): array
    {
        return Cache::remember("cricket:match:{$matchId}:over_history", 15, function () use ($matchId) {
            $data = $this->fetchFromApi("match/{$matchId}/overHistory");
            Log::info("RapidApiCricketService: OverHistory response for match {$matchId}", [
                'has_data' => !empty($data['data']),
                'status' => $data['status'] ?? null,
            ]);
            return $data;
        }) ?? [];
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
}

