<?php

use App\Helpers\CricketSlugHelper;
use App\Http\Controllers\AdRedirectController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\SitemapController;
use App\Services\Cricket\CricketUrlService;
use App\Services\Cricket\RapidApiCricketService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Home & Live Scores
Route::get('/', App\Livewire\Pages\HomeLiveScores::class)->name('home');
Route::get('/live-scores', App\Livewire\Pages\HomeLiveScores::class);

// Match Detail Views - Primary SEO Industry-Standard URL Structure
Route::get('/match-detail/{seriesSlug}/{tab}/{matchSlug}', App\Livewire\Pages\MatchDetail::class)
    ->where('tab', 'live-line|commentary|scorecard|squad|highlights|odds|info')
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

// Tournament Schedules, Series Hub & Tables
Route::get('/cricket-schedule/{status?}/{category?}', App\Livewire\Pages\SchedulePage::class)->name('schedule');

Route::get('/cricket-series', \App\Livewire\Pages\SeriesListPage::class)
    ->name('series.index');

Route::get('/cricket-series/{seriesSlug}/{tab?}', \App\Livewire\Pages\SeriesDetailPage::class)
    ->where('tab', 'matches|points-table|news|squads')
    ->name('series.detail');

Route::get('/cricket-rankings/{category?}/{format?}', App\Livewire\Pages\RankingsPage::class)
    ->whereIn('category', ['batting', 'bowling', 'allrounder', 'teams'])
    ->whereIn('format', ['t20', 'odi', 'test'])
    ->name('rankings');

Route::get('/cricket-rankings/{gender}', fn () => redirect()->route('rankings', [], 301))
    ->whereIn('gender', ['men', 'women'])
    ->name('rankings.legacy');

// Editorial & Fan Engagement
Route::get('/cricket-news', App\Livewire\Pages\NewsIndex::class)->name('news.index');
Route::get('/cricket-news/{article:slug}', App\Livewire\Pages\NewsShow::class)->name('news.show');
Route::get('/write-for-us', \App\Livewire\Pages\WriteForUsPage::class)->name('guest-post.create');
Route::get('/polls', App\Livewire\Pages\PollsPage::class)->name('polls');

// Games Hub & Captain vs Captain Squad Battles
Route::get('/games', App\Livewire\Pages\GamesHub::class)->name('games.index');
Route::get('/games/battle/{slug}', App\Livewire\Pages\BattleRoom::class)->name('games.battle.show');

// User Settings & More Hub (Formerly Profile)
Route::get('/more', App\Livewire\Pages\MorePage::class)->name('more');
Route::get('/profile', fn () => redirect()->route('more', [], 301))->name('profile');

// Legal & Informational Pages
Route::view('/privacy-policy', 'legal.privacy')->name('privacy.policy');
Route::view('/terms-and-conditions', 'legal.terms')->name('terms');
Route::view('/about-us', 'legal.about')->name('about');
// Backward compatibility route alias
Route::get('/privacy', fn () => redirect()->route('privacy.policy', [], 301))->name('privacy');

// OAuth Authentication & Session Logout
Route::get('/auth/google', [SocialiteController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/redirect', [SocialiteController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [SocialiteController::class, 'callback'])->name('google.callback');
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/')->with('status', 'Logged out successfully.');
})->name('logout');

// Monetization Gateway
Route::get('/go/{banner}', [AdRedirectController::class, 'trackAndRedirect'])->name('ad.redirect');

// Cricket Session & Fancy API
Route::get('/api/matches/{matchId}/sessions', [\App\Http\Controllers\Cricket\SessionController::class, 'show'])->name('match.sessions');

// Match Viewer Presence Heartbeat Ping
Route::post('/api/matches/{matchId}/ping', function (string|int $matchId, \Illuminate\Http\Request $request) {
    if (!$request->attributes->get('is_search_crawler') && !app()->bound('crawler_read_only_mode')) {
        $ts = now()->timestamp;
        \Illuminate\Support\Facades\Cache::put('active_site_visitor', $ts, 120);
        \Illuminate\Support\Facades\Cache::put("watching:match:{$matchId}", $ts, 45);
        \Illuminate\Support\Facades\Cache::put("cricket:watchers:match:{$matchId}", $ts, 45);
    }
    return response()
        ->json(['status' => true, 'match_id' => $matchId])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
})->name('match.ping');

// Segregated Cricket Match API Endpoints
Route::get('/api/matches/live', [\App\Http\Controllers\Cricket\LiveMatchController::class, 'index'])->name('api.matches.live');
Route::get('/api/matches/live/{matchId}', [\App\Http\Controllers\Cricket\LiveMatchController::class, 'show'])->name('api.matches.live.show');
Route::get('/api/matches/upcoming', [\App\Http\Controllers\Cricket\UpcomingMatchController::class, 'index'])->name('api.matches.upcoming');
Route::get('/api/matches/finished', [\App\Http\Controllers\Cricket\FinishedMatchController::class, 'index'])->name('api.matches.finished');
Route::get('/api/matches/finished/{matchId}', [\App\Http\Controllers\Cricket\FinishedMatchController::class, 'show'])->name('api.matches.finished.show');

// Direct Fast Real-Time Live Cricket JSON Endpoints (Delegated to LiveMatchController)
Route::get('/api/live/home', [\App\Http\Controllers\Cricket\LiveMatchController::class, 'index'])->name('api.live.home');
Route::get('/api/live/match/{matchId}', [\App\Http\Controllers\Cricket\LiveMatchController::class, 'show'])->name('api.live.match');

// High-Speed Dynamic XML Sitemaps & Robots.txt
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-static.xml', [SitemapController::class, 'staticPages'])->name('sitemap.static');
Route::get('/sitemap-matches.xml', [SitemapController::class, 'matches'])->name('sitemap.matches');
Route::get('/sitemap-news.xml', [SitemapController::class, 'news'])->name('sitemap.news');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots.txt');

// Push Notifications & FCM Subscription API
Route::get('/firebase-messaging-sw.js', [\App\Http\Controllers\PushSubscriptionController::class, 'serviceWorker'])->name('firebase.sw');
Route::post('/api/push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'subscribe'])->name('api.push.subscribe');
Route::post('/api/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'unsubscribe'])->name('api.push.unsubscribe');
Route::get('/api/notifications/latest', [\App\Http\Controllers\PushSubscriptionController::class, 'latest'])->name('api.notifications.latest');
Route::post('/api/notifications/mark-read', [\App\Http\Controllers\PushSubscriptionController::class, 'markRead'])->name('api.notifications.mark-read');
Route::get('/api/firebase/config', [\App\Http\Controllers\PushSubscriptionController::class, 'firebaseConfig'])->name('api.firebase.config');


Route::get('/api/finished-match/{matchId}', [\App\Http\Controllers\Cricket\CompletedMatchController::class, 'showJson'])->name('api.finished-match.show');
