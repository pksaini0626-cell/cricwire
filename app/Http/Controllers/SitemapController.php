<?php

namespace App\Http\Controllers;

use App\Helpers\CricketSlugHelper;
use App\Models\NewsArticle;
use App\Services\Cricket\RapidApiCricketService;
use App\Support\SeoConfig;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    protected const CACHE_TTL = 900; // 15 minutes (900 seconds)

    /**
     * Master XML Sitemap Index.
     * GET /sitemap.xml
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap:index', self::CACHE_TTL, function () {
            return view('sitemaps.sitemap-index')->render();
        });

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Static Pages XML Sitemap.
     * GET /sitemap-static.xml
     */
    public function staticPages(): Response
    {
        $xml = Cache::remember('sitemap:static', self::CACHE_TTL, function () {
            return view('sitemaps.sitemap-static')->render();
        });

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Live & Upcoming Dynamic Cricket Matches XML Sitemap.
     * GET /sitemap-matches.xml
     */
    public function matches(RapidApiCricketService $cricketService): Response
    {
        $xml = Cache::remember('sitemap:matches', self::CACHE_TTL, function () use ($cricketService) {
            $matchesList = [];

            // 1. Fetch cached home matches
            try {
                $homeData = $cricketService->getHomeMatches(false);
                $matchesList = is_array($homeData) ? $homeData : ($homeData['data'] ?? []);
            } catch (\Throwable $e) {
                $matchesList = [];
            }

            // 2. Fetch cached schedule data (upcoming & recent)
            try {
                $schedule = $cricketService->getSchedule(false);
                if (!empty($schedule['upcoming']) && is_array($schedule['upcoming'])) {
                    $matchesList = array_merge($matchesList, $schedule['upcoming']);
                }
            } catch (\Throwable $e) {
                // Ignore fallback
            }

            $uniqueMatches = [];
            $seenIds = [];

            foreach ($matchesList as $m) {
                if (!is_array($m)) continue;
                $matchId = $m['match_id'] ?? $m['id'] ?? null;
                if (!$matchId || isset($seenIds[$matchId])) continue;
                $seenIds[$matchId] = true;

                $seriesSlug = CricketSlugHelper::makeSeriesSlug($m);
                $matchSlug = CricketSlugHelper::makeMatchSlug($m, $matchId);

                $liveLineUrl = route('match.detail', [
                    'seriesSlug' => $seriesSlug,
                    'tab' => 'live-line',
                    'matchSlug' => $matchSlug,
                ]);

                $infoUrl = route('match.detail', [
                    'seriesSlug' => $seriesSlug,
                    'tab' => 'info',
                    'matchSlug' => $matchSlug,
                ]);

                $uniqueMatches[] = [
                    'id' => $matchId,
                    'status' => $m['match_status'] ?? $m['status'] ?? 'upcoming',
                    'primary_url' => $liveLineUrl,
                    'info_url' => $infoUrl,
                ];
            }

            return view('sitemaps.sitemap-matches', [
                'matches' => $uniqueMatches,
            ])->render();
        });

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Google News XML Sitemap for fresh news articles (last 48 hours) + older articles.
     * GET /sitemap-news.xml
     */
    public function news(): Response
    {
        $xml = Cache::remember('sitemap:news', self::CACHE_TTL, function () {
            $freshArticles = NewsArticle::query()
                ->where('is_published', true)
                ->where('created_at', '>=', now()->subHours(48))
                ->latest()
                ->take(250)
                ->get();

            $olderArticles = NewsArticle::query()
                ->where('is_published', true)
                ->where('created_at', '<', now()->subHours(48))
                ->latest()
                ->take(750)
                ->get();

            return view('sitemaps.sitemap-news', [
                'freshArticles' => $freshArticles,
                'olderArticles' => $olderArticles,
            ])->render();
        });

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Dynamic robots.txt generation.
     * GET /robots.txt
     */
    public function robots(): Response
    {
        $content = SeoConfig::get('seo_robots_txt_content', SeoConfig::DEFAULTS['seo_robots_txt_content']);

        return response($content, 200)->header('Content-Type', 'text/plain');
    }
}
