<?php

use App\Helpers\CricketSlugHelper;
use App\Http\Controllers\AdRedirectController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\SitemapController;
use App\Services\Cricket\CricketUrlService;
use App\Services\Cricket\RapidApiCricketService;
use Illuminate\Support\Facades\Route;

// Home & Live Scores
Route::get('/', App\Livewire\Pages\HomeLiveScores::class)->name('home');
Route::get('/live-scores', App\Livewire\Pages\HomeLiveScores::class);

// Match Detail Views - Primary SEO Industry-Standard URL Structure
Route::get('/match-detail/{seriesSlug}/{tab}/{matchSlug}', App\Livewire\Pages\MatchDetail::class)
    ->where('tab', 'live-line|commentary|scorecard|squad|odds|info')
    ->name('match.detail');

// Match Detail Views - Backward Compatibility & Redirect Route
Route::get('/live-cricket-score/{matchId}/{tab?}', function (string $matchId, ?string $tab = 'live-line', RapidApiCricketService $cricketService = null) {
    $cleanTab = CricketUrlService::normalizeTab($tab);
    $resolvedId = CricketSlugHelper::extractMatchId($matchId) ?? CricketUrlService::resolveMatchId($matchId) ?? $matchId;

    // Check home matches cache or detail to build rich dynamic slug
    $matchedItem = null;
    if ($cricketService) {
        $homeData = $cricketService->getHomeMatches();
        $matchesList = $homeData['data'] ?? $homeData['matches'] ?? [];
        foreach ($matchesList as $m) {
            $id = $m['match_id'] ?? $m['id'] ?? null;
            if ((string) $id === (string) $resolvedId) {
                $matchedItem = $m;
                break;
            }
        }
    }

    if ($matchedItem) {
        $seriesSlug = CricketSlugHelper::makeSeriesSlug($matchedItem);
        $matchSlug = CricketSlugHelper::makeMatchSlug($matchedItem, $resolvedId);
    } else {
        $seriesSlug = 'cricket-tournament';
        $matchSlug = "match-{$resolvedId}";
    }

    // Redirect to clean dynamic SEO URL format with 301 Permanent Redirect
    return redirect()->to(
        route('match.detail', [
            'seriesSlug' => $seriesSlug,
            'tab' => $cleanTab,
            'matchSlug' => $matchSlug,
        ]),
        301
    );
})->name('match.detail.legacy');

// Tournament Schedules & Tables
Route::get('/cricket-schedule/{status?}/{category?}', App\Livewire\Pages\SchedulePage::class)->name('schedule');
Route::get('/cricket-series/{series:slug}/overview', App\Livewire\Pages\SeriesDetail::class)->name('series.detail');
Route::get('/cricket-rankings/{gender?}', App\Livewire\Pages\RankingsPage::class)->name('rankings');

// Editorial & Fan Engagement
Route::get('/cricket-news', App\Livewire\Pages\NewsIndex::class)->name('news.index');
Route::get('/cricket-news/{article:slug}', App\Livewire\Pages\NewsShow::class)->name('news.show');
Route::get('/polls', App\Livewire\Pages\PollsPage::class)->name('polls');

// User Settings & More Hub (Formerly Profile)
Route::get('/more', App\Livewire\Pages\MorePage::class)->name('more');
Route::get('/profile', fn () => redirect()->route('more', [], 301))->name('profile');

// Legal & Informational Pages
Route::view('/privacy-policy', 'legal.privacy')->name('privacy.policy');
Route::view('/terms-and-conditions', 'legal.terms')->name('terms');
Route::view('/about-us', 'legal.about')->name('about');
// Backward compatibility route alias
Route::get('/privacy', fn () => redirect()->route('privacy.policy', [], 301))->name('privacy');

// OAuth Authentication
Route::get('/auth/google/redirect', [SocialiteController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [SocialiteController::class, 'callback'])->name('google.callback');

// Monetization Gateway
Route::get('/go/{banner}', [AdRedirectController::class, 'trackAndRedirect'])->name('ad.redirect');

// Cricket Session & Fancy API
Route::get('/api/matches/{matchId}/sessions', [\App\Http\Controllers\Cricket\SessionController::class, 'show'])->name('match.sessions');

// Sitemaps for Google News & Search Indexing
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/sitemap-news.xml', [SitemapController::class, 'news']);
