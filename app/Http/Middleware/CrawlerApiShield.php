<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CrawlerApiShield
{
    /**
     * Common search engine crawler user agent signatures.
     */
    protected const CRAWLER_SIGNATURES = [
        'googlebot',
        'bingbot',
        'slurp',
        'duckduckbot',
        'baiduspider',
        'yandex',
        'semrushbot',
        'ahrefsbot',
        'mj12bot',
        'dotbot',
        'petalbot',
        'applebot',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = strtolower((string) $request->header('User-Agent', ''));
        $isCrawler = false;

        if ($userAgent !== '') {
            foreach (self::CRAWLER_SIGNATURES as $bot) {
                if (str_contains($userAgent, $bot)) {
                    $isCrawler = true;
                    break;
                }
            }
        }

        $request->attributes->set('is_search_crawler', $isCrawler);

        if ($isCrawler) {
            // Set request-scoped flag in Laravel service container
            app()->instance('crawler_read_only_mode', true);
        }

        return $next($request);
    }
}
