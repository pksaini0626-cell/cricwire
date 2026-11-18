<?php

namespace App\Services\Cricket;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReverbPresenceService
{
    /**
     * Get active watched match IDs by combining Reverb WebSocket channel subscribers
     * and HTTP heartbeat fallback keys in Cache/Redis.
     *
     * @param array $candidateMatchIds Optional candidate match IDs to check
     * @return array Array of match IDs (as strings) that currently have active viewers
     */
    public function getActiveWatchedMatchIds(array $candidateMatchIds = []): array
    {
        $watchedIds = [];

        // 1. Primary Check: Query Laravel Reverb internal Pusher HTTP API
        $reverbWatched = $this->getWatchedMatchesFromReverb();
        foreach ($reverbWatched as $id) {
            $watchedIds[strval($id)] = true;
        }

        // 2. Fallback / Complementary Check: Lightweight heartbeat cache keys
        $heartbeatWatched = $this->getWatchedMatchesFromHeartbeat($candidateMatchIds);
        foreach ($heartbeatWatched as $id) {
            $watchedIds[strval($id)] = true;
        }

        return array_values(array_keys($watchedIds));
    }

    /**
     * Query Laravel Reverb HTTP API (/apps/{appId}/channels) with Pusher signature.
     *
     * @return array
     */
    public function getWatchedMatchesFromReverb(): array
    {
        $appId = config('reverb.apps.apps.0.app_id') ?: env('REVERB_APP_ID');
        $appKey = config('reverb.apps.apps.0.key') ?: env('REVERB_APP_KEY');
        $appSecret = config('reverb.apps.apps.0.secret') ?: env('REVERB_APP_SECRET');
        $host = config('reverb.servers.reverb.host', '127.0.0.1');
        if ($host === '0.0.0.0' || empty($host)) {
            $host = '127.0.0.1';
        }
        $port = config('reverb.servers.reverb.port', 8080);

        if (!$appId || !$appKey || !$appSecret) {
            return [];
        }

        $path = "/apps/{$appId}/channels";
        $method = 'GET';
        $params = [
            'auth_key' => $appKey,
            'auth_timestamp' => time(),
            'auth_version' => '1.0',
            'filter_by_prefix' => 'match.',
            'info' => 'subscription_count',
        ];

        ksort($params);
        $queryString = http_build_query($params);
        $signaturePayload = "{$method}\n{$path}\n{$queryString}";
        $signature = hash_hmac('sha256', $signaturePayload, $appSecret);
        $params['auth_signature'] = $signature;

        $url = "http://{$host}:{$port}{$path}?" . http_build_query($params);

        try {
            $response = Http::timeout(1.5)->get($url);
            if ($response->successful()) {
                $data = $response->json();
                $channels = $data['channels'] ?? [];
                $activeMatchIds = [];

                foreach ($channels as $channelName => $info) {
                    $occupied = $info['occupied'] ?? false;
                    $subCount = $info['subscription_count'] ?? 0;

                    if ($occupied || $subCount > 0) {
                        if (Str::startsWith($channelName, 'match.')) {
                            $matchId = Str::after($channelName, 'match.');
                            if ($matchId !== '') {
                                $activeMatchIds[] = $matchId;
                            }
                        }
                    }
                }

                return $activeMatchIds;
            }
        } catch (\Throwable $e) {
            // Reverb not reachable or timed out; fallback heartbeat handles it smoothly
        }

        return [];
    }

    /**
     * Check heartbeat cache keys (cricket:watchers:match:{matchId}).
     *
     * @param array $candidateMatchIds
     * @return array
     */
    public function getWatchedMatchesFromHeartbeat(array $candidateMatchIds = []): array
    {
        $active = [];

        // If specific candidate match IDs were passed, check them directly
        if (!empty($candidateMatchIds)) {
            foreach ($candidateMatchIds as $matchId) {
                if (Cache::has("cricket:watchers:match:{$matchId}")) {
                    $active[] = strval($matchId);
                }
            }
        }

        // Also check if any active matches list was registered or discovered
        $allActiveWatchers = Cache::get('cricket:active_heartbeat_match_ids', []);
        if (is_array($allActiveWatchers)) {
            $freshActive = [];
            foreach ($allActiveWatchers as $mId) {
                if (Cache::has("cricket:watchers:match:{$mId}")) {
                    $active[] = strval($mId);
                    $freshActive[] = strval($mId);
                }
            }
            // Update active list
            if (count($freshActive) !== count($allActiveWatchers)) {
                Cache::put('cricket:active_heartbeat_match_ids', array_unique($freshActive), 60);
            }
        }

        return array_values(array_unique($active));
    }

    /**
     * Record a heartbeat ping for a match.
     *
     * @param string|int $matchId
     * @return void
     */
    public static function recordHeartbeat(string|int $matchId): void
    {
        $matchId = strval($matchId);
        Cache::put("cricket:watchers:match:{$matchId}", true, now()->addSeconds(30));

        // Keep registry of active heartbeat match IDs
        $current = Cache::get('cricket:active_heartbeat_match_ids', []);
        if (!is_array($current)) {
            $current = [];
        }
        if (!in_array($matchId, $current, true)) {
            $current[] = $matchId;
            Cache::put('cricket:active_heartbeat_match_ids', $current, 60);
        }
    }
}
