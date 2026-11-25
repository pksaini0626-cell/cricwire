<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackSiteVisitors
{
    /**
     * Seconds before an online visitor entry expires.
     */
    protected const ONLINE_TTL_SECONDS = 180; // 3 minutes

    /**
     * Minimum seconds between session heartbeat writes to cache.
     */
    protected const HEARTBEAT_THROTTLE_SECONDS = 30;

    /**
     * Session key storing the last online heartbeat timestamp.
     */
    protected const SESSION_HEARTBEAT_KEY = 'visitor_online_ping_at';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldTrack($request)) {
            $this->recordVisit($request);
        }

        return $next($request);
    }

    /**
     * Determine whether the request represents a real public page visit.
     */
    protected function shouldTrack(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }

        if ($request->is(
            'admin',
            'admin/*',
            'livewire',
            'livewire/*',
            'api',
            'api/*',
            'live-data',
            'live-data/*',
            'filament',
            'filament/*',
            'up'
        )) {
            return false;
        }

        if ($this->isBotOrCrawler($request)) {
            return false;
        }

        return true;
    }

    /**
     * Detect search crawlers, bots, and automated HTTP clients.
     */
    protected function isBotOrCrawler(Request $request): bool
    {
        if ($request->attributes->get('is_search_crawler') || app()->bound('crawler_read_only_mode')) {
            return true;
        }

        $userAgent = trim((string) $request->userAgent());
        if ($userAgent === '') {
            return true;
        }

        return (bool) preg_match(
            '/(\bbot\b|bot[\/;:\-\)]|crawl|spider|slurp|mediapartners|adsbot|bingpreview|facebookexternalhit|facebot|twitterbot|whatsapp|telegrambot|linkedinbot|pinterest|slackbot|discordbot|applebot|duckduck|baidu|yandex|semrush|ahrefs|mj12bot|dotbot|petalbot|headlesschrome|lighthouse|uptimerobot|pingdom|curl\/|wget\/|python-requests|go-http-client|postman|bytespider|gptbot|claudebot|oai-searchbot|anthropic)/i',
            $userAgent
        );
    }

    /**
     * Record online heartbeat, daily unique visitor, and daily page view in cache.
     */
    protected function recordVisit(Request $request): void
    {
        try {
            $now = now()->timestamp;
            $today = now()->format('Y-m-d');
            $visitorHash = sha1($request->ip() . '|' . substr((string) $request->userAgent(), 0, 80));

            $this->updateOnlineHeartbeat($request, $visitorHash, $now);
            $this->recordDailyUniqueVisitor($visitorHash, $today);
            $this->incrementDailyPageviews($today);
        } catch (\Throwable) {
            // Never disrupt page rendering if cache storage is temporarily unavailable.
        }
    }

    /**
     * Refresh visitor presence in site:online_visitors, throttled per session every 30s.
     */
    protected function updateOnlineHeartbeat(Request $request, string $visitorHash, int $now): void
    {
        $hasSession = $request->hasSession();
        if ($hasSession) {
            $lastPingAt = (int) $request->session()->get(self::SESSION_HEARTBEAT_KEY, 0);
            if (($now - $lastPingAt) < self::HEARTBEAT_THROTTLE_SECONDS) {
                return;
            }
        }

        $cutoff = $now - self::ONLINE_TTL_SECONDS;
        $onlineVisitors = Cache::get('site:online_visitors', []);
        if (!is_array($onlineVisitors)) {
            $onlineVisitors = [];
        }

        $prunedVisitors = [];
        foreach ($onlineVisitors as $hash => $timestamp) {
            if (is_int($timestamp) && $timestamp >= $cutoff) {
                $prunedVisitors[$hash] = $timestamp;
            }
        }

        $prunedVisitors[$visitorHash] = $now;

        Cache::put('site:online_visitors', $prunedVisitors, now()->addMinutes(5));

        if ($hasSession) {
            $request->session()->put(self::SESSION_HEARTBEAT_KEY, $now);
        }
    }

    /**
     * Track unique visitor hashes for today with a 48-hour retention window.
     */
    protected function recordDailyUniqueVisitor(string $visitorHash, string $today): void
    {
        $dailyKey = 'site:daily_visitors:' . $today;
        $dailyVisitors = Cache::get($dailyKey, []);
        if (!is_array($dailyVisitors)) {
            $dailyVisitors = [];
        }

        if (!isset($dailyVisitors[$visitorHash])) {
            $dailyVisitors[$visitorHash] = true;
            Cache::put($dailyKey, $dailyVisitors, now()->addHours(48));
        }
    }

    /**
     * Increment today's total pageview counter with a 48-hour retention window.
     */
    protected function incrementDailyPageviews(string $today): void
    {
        $pageviewsKey = 'site:daily_pageviews:' . $today;
        Cache::add($pageviewsKey, 0, now()->addHours(48));
        Cache::increment($pageviewsKey);
    }
}
