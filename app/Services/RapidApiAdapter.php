<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RapidApiAdapter
{
    protected string $apiKey;
    protected string $apiHost;
    protected string $baseUrl;
    protected int $dailyLimit;

    public function __construct()
    {
        $this->apiKey = (string) config('services.rapidapi.key');
        $this->apiHost = (string) config('services.rapidapi.host', 'cricbuzz-cricket.p.rapidapi.com');
        $this->baseUrl = rtrim((string) config('services.rapidapi.base_url', 'https://cricbuzz-cricket.p.rapidapi.com'), '/');
        $this->dailyLimit = (int) config('services.rapidapi.rate_limit_per_day', 500);
    }

    /**
     * Check rate limit and increment call counter for the day.
     */
    public function checkAndIncrementRateLimit(): bool
    {
        $key = 'api:calls:' . now()->format('Y-m-d');
        
        $currentCalls = (int) Redis::get($key);
        if ($currentCalls >= $this->dailyLimit) {
            Log::warning("RapidAPI daily rate limit hit: {$currentCalls}/{$this->dailyLimit}");
            return false;
        }

        $count = Redis::incr($key);
        if ($count === 1) {
            Redis::expire($key, 86400 * 2); // 48-hour retention
        }

        return true;
    }

    /**
     * Perform GET request with connection resilience and rate-limit guard.
     */
    public function get(string $endpoint, array $queryParams = []): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('RapidAPI Key is not configured.');
            return null;
        }

        if (!$this->checkAndIncrementRateLimit()) {
            return null;
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        try {
            $response = Http::withHeaders([
                'X-RapidAPI-Key' => $this->apiKey,
                'X-RapidAPI-Host' => $this->apiHost,
                'Accept' => 'application/json',
            ])
            ->timeout(5)
            ->retry(2, 100)
            ->get($url, $queryParams);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("RapidAPI request failed [{$response->status()}]: {$url}", [
                'body' => $response->body(),
            ]);
            return null;
        } catch (Exception $e) {
            Log::error("RapidAPI HTTP exception for {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch live matches list.
     */
    public function getLiveMatches(): ?array
    {
        return $this->get('matches/v1/live');
    }

    /**
     * Fetch match commentary / mini-scorecard live-line.
     */
    public function getMatchLiveLine(string $apiMatchId): ?array
    {
        return $this->get("mcenter/v1/{$apiMatchId}/comm");
    }

    /**
     * Fetch upcoming schedules / series list.
     */
    public function getSchedules(): ?array
    {
        return $this->get('schedule/v1/international');
    }

    /**
     * Fetch ICC rankings.
     */
    public function getRankings(string $category = 'teams', string $format = 'test'): ?array
    {
        return $this->get("stats/v1/rankings/{$category}", [
            'formatType' => strtolower($format),
        ]);
    }
}

