<?php

namespace Tests\Feature;

use App\Jobs\SyncRankingsJob;
use App\Livewire\Pages\RankingsPage;
use App\Services\Cricket\CricketLiveApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RankingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function sampleRankingResponse(string $category, string $format): array
    {
        $categoryType = match ($category) {
            'bowling' => 'bowlers',
            'allrounder' => 'allrounders',
            'teams' => 'teams',
            default => 'batsmen',
        };

        $entries = match ($category) {
            'teams' => [
                [
                    'id' => '2',
                    'rank' => '1',
                    'name' => 'India',
                    'country' => 'India',
                    'rating' => '271',
                    'points' => '18150',
                    'trend' => 'Flat',
                    'faceImageId' => '172115',
                ],
                [
                    'id' => '4',
                    'rank' => '2',
                    'name' => 'Australia',
                    'country' => 'Australia',
                    'rating' => '259',
                    'points' => '14504',
                    'trend' => 'Up',
                    'faceImageId' => '172117',
                ],
            ],
            'bowling' => [
                [
                    'id' => '14172',
                    'rank' => '1',
                    'name' => 'Varun Chakaravarthy',
                    'country' => 'India',
                    'rating' => '790',
                    'points' => '790',
                    'trend' => 'Up',
                    'faceImageId' => '181446',
                ],
            ],
            'allrounder' => [
                [
                    'id' => '9647',
                    'rank' => '1',
                    'name' => 'Hardik Pandya',
                    'country' => 'India',
                    'rating' => '252',
                    'points' => '252',
                    'trend' => 'Down',
                    'faceImageId' => '170666',
                ],
            ],
            default => [
                [
                    'id' => '12086',
                    'rank' => '1',
                    'name' => 'Abhishek Sharma',
                    'country' => 'India',
                    'rating' => '889',
                    'points' => '889',
                    'trend' => 'Flat',
                    'faceImageId' => '846031',
                ],
                [
                    'id' => '10808',
                    'rank' => '2',
                    'name' => 'Phil Salt',
                    'country' => 'England',
                    'rating' => '829',
                    'points' => '829',
                    'trend' => 'Up',
                    'faceImageId' => '591982',
                ],
            ],
        };

        return [
            'success' => true,
            'category' => $category,
            'format' => $format,
            'count' => count($entries),
            'data' => [
                'category' => $category,
                'format' => $format,
                'categoryType' => $categoryType,
                'availableFormats' => ['odi', 'test', 't20'],
                'rankings' => $entries,
            ],
        ];
    }

    public function test_daily_sync_maps_verified_schema_into_redis_for_all_12_combinations(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
            if (preg_match('#/cricket/icc-rankings/(batting|bowling|allrounder|teams)/(t20|odi|test)$#', $path, $matches)) {
                return Http::response($this->sampleRankingResponse($matches[1], $matches[2]), 200);
            }

            return Http::response(['success' => false], 404);
        });

        $this->artisan('cricket:sync-rankings')->assertExitCode(0);

        Http::assertSentCount(12);

        $service = app(CricketLiveApiService::class);

        foreach (CricketLiveApiService::ICC_RANKING_CATEGORIES as $cat) {
            foreach (CricketLiveApiService::ICC_RANKING_FORMATS as $fmt) {
                $cached = $service->getIccRankingsFromRedis($cat, $fmt);
                $this->assertSame($cat, $cached['category']);
                $this->assertSame($fmt, $cached['format']);
                $this->assertNotEmpty($cached['rankings']);
                $this->assertNotEmpty($cached['synced_at']);
                $this->assertTrue(Cache::has(CricketLiveApiService::iccRankingBackupCacheKey($cat, $fmt)));
            }
        }

        $battingT20 = $service->getIccRankingsFromRedis('batting', 't20');
        $first = $battingT20['rankings'][0];
        $this->assertSame('12086', $first['id']);
        $this->assertSame(1, $first['rank']);
        $this->assertSame('Abhishek Sharma', $first['name']);
        $this->assertSame('India', $first['country']);
        $this->assertSame(889, $first['rating']);
        $this->assertSame(889, $first['points']);
        $this->assertSame('Flat', $first['trend']);
        $this->assertSame('flat', $first['trend_direction']);
        $this->assertSame('846031', $first['image_id']);
        $this->assertSame('846031', $first['face_image_id']);
        $this->assertSame('https://static.cricbuzz.com/a/img/v1/i1/c846031/i.jpg', $first['image_url']);
    }

    public function test_human_cache_miss_hydrates_redis_once_and_never_exposes_internal_text(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
            if (preg_match('#/cricket/icc-rankings/(batting|bowling|allrounder|teams)/(t20|odi|test)$#', $path, $matches)) {
                return Http::response($this->sampleRankingResponse($matches[1], $matches[2]), 200);
            }

            return Http::response(['success' => false], 404);
        });

        // Cold cache hit by human visitor: self-heals with exactly 1 API call and populates primary + backup cache
        $firstResponse = $this->get('/cricket-rankings');
        $firstResponse->assertStatus(200);
        $firstResponse->assertSee('Abhishek Sharma');
        $firstResponse->assertSee('889');
        $firstResponse->assertDontSee('Redis');
        $firstResponse->assertDontSee('Cache Not Yet Populated');
        $firstResponse->assertDontSee('zero live API drain');
        $firstResponse->assertDontSee('daily sync schedule');
        Http::assertSentCount(1);

        // Second request hits Redis fast path with ZERO API calls
        $secondResponse = $this->get('/cricket-rankings');
        $secondResponse->assertStatus(200);
        $secondResponse->assertSee('Abhishek Sharma');
        Http::assertSentCount(1);

        $this->assertTrue(Cache::has(CricketLiveApiService::iccRankingCacheKey('batting', 't20')));
        $this->assertTrue(Cache::has(CricketLiveApiService::iccRankingBackupCacheKey('batting', 't20')));
    }

    public function test_search_crawler_guard_uses_backup_cache_and_never_spends_api_quota(): void
    {
        Http::fake();
        app()->instance('crawler_read_only_mode', true);

        $response = $this->get('/cricket-rankings');
        $response->assertStatus(200);
        $response->assertSee('No rankings available for this category and format right now. Please check back shortly.');
        $response->assertDontSee('Redis');
        $response->assertDontSee('Cache Not Yet Populated');
        Http::assertNothingSent();
    }

    public function test_livewire_rankings_switching_and_search_filtering(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
            if (preg_match('#/cricket/icc-rankings/(batting|bowling|allrounder|teams)/(t20|odi|test)$#', $path, $matches)) {
                return Http::response($this->sampleRankingResponse($matches[1], $matches[2]), 200);
            }

            return Http::response(['success' => false], 404);
        });

        app(SyncRankingsJob::class)->handle(app(CricketLiveApiService::class));

        // Ensure Livewire interactions trigger zero additional HTTP requests once synced
        Http::fake();

        Livewire::test(RankingsPage::class)
            ->assertSet('category', 'batting')
            ->assertSet('format', 't20')
            ->assertSee('Abhishek Sharma')
            ->assertSee('Phil Salt')
            ->set('search', 'Phil')
            ->assertSee('Phil Salt')
            ->assertDontSee('Abhishek Sharma')
            ->set('search', '')
            ->call('setCategory', 'bowling')
            ->assertSet('category', 'bowling')
            ->assertSee('Varun Chakaravarthy')
            ->call('setCategory', 'allrounder')
            ->assertSet('category', 'allrounder')
            ->assertSee('Hardik Pandya')
            ->call('setCategory', 'teams')
            ->call('setFormat', 'odi')
            ->assertSet('category', 'teams')
            ->assertSet('format', 'odi')
            ->assertSee('Australia');

        Http::assertNothingSent();
    }

    public function test_failed_upstream_sync_preserves_existing_redis_and_backup_cache_and_legacy_redirects(): void
    {
        $key = CricketLiveApiService::iccRankingCacheKey('batting', 't20');
        $backupKey = CricketLiveApiService::iccRankingBackupCacheKey('batting', 't20');
        $cachedPayload = [
            'category' => 'batting',
            'format' => 't20',
            'category_type' => 'batsmen',
            'available_formats' => ['odi', 'test', 't20'],
            'count' => 1,
            'rankings' => [
                [
                    'id' => '12086',
                    'rank' => 1,
                    'name' => 'Abhishek Sharma',
                    'country' => 'India',
                    'country_short' => 'IND',
                    'matches' => null,
                    'rating' => 889,
                    'points' => 889,
                    'trend' => 'Flat',
                    'trend_direction' => 'flat',
                    'image_id' => '846031',
                    'face_image_id' => '846031',
                    'image_url' => 'https://static.cricbuzz.com/a/img/v1/i1/c846031/i.jpg',
                ],
            ],
            'synced_at' => '2026-11-25T05:30:00+05:30',
        ];
        Cache::put($backupKey, $cachedPayload, 604800);

        Http::fake([
            '*' => Http::response(['success' => false], 500),
        ]);

        $service = app(CricketLiveApiService::class);

        // Primary missing + upstream 500 => cleanly falls back to 7-day backup
        $fallback = $service->getIccRankings('batting', 't20');
        $this->assertCount(1, $fallback['rankings']);
        $this->assertSame('Abhishek Sharma', $fallback['rankings'][0]['name']);

        // Also test primary preservation during syncAllIccRankings
        Cache::put($key, $cachedPayload, 43200);
        $service->syncAllIccRankings();
        $preserved = $service->getIccRankingsFromRedis('batting', 't20');
        $this->assertCount(1, $preserved['rankings']);
        $this->assertSame('Abhishek Sharma', $preserved['rankings'][0]['name']);

        // Route parameter SEO navigation & legacy gender 301 redirect
        $this->get('/cricket-rankings/bowling/test')->assertStatus(200);
        $this->get('/cricket-rankings/men')->assertRedirect('/cricket-rankings')->assertStatus(301);
        $this->get('/cricket-rankings/women')->assertRedirect('/cricket-rankings')->assertStatus(301);
    }
}
