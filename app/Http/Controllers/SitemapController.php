<?php

namespace App\Http\Controllers;

use App\Models\CricketMatch;
use App\Models\NewsArticle;
use App\Models\Series;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Standard XML Sitemap for pages, matches, and series.
     */
    public function index(): Response
    {
        $matches = CricketMatch::latest()->take(100)->get();
        $seriesList = Series::latest()->take(50)->get();
        $articles = NewsArticle::where('is_published', true)->latest()->take(100)->get();

        $content = view('sitemaps.index', compact('matches', 'seriesList', 'articles'))->render();

        return response($content, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Google News XML Sitemap for fresh news articles.
     */
    public function news(): Response
    {
        $articles = NewsArticle::where('is_published', true)
            ->where('created_at', '>=', now()->subDays(2))
            ->latest()
            ->take(200)
            ->get();

        $content = view('sitemaps.news', compact('articles'))->render();

        return response($content, 200)->header('Content-Type', 'text/xml');
    }
}

