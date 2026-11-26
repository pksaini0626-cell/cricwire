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
        $this->assertSame('846031', $first['face_image_id']);
        $this->assertSame('https://static.cricbuzz.com/a/img/v1/i1/c846031/i.jpg', $first['image_url']);
    }

    public function test_rankings_page_has_zero_live_api_drain_when_cache_empty_or_populated(): void
    {
        Http::fake();

        // Even when cache is empty, rendering /cricket-rankings makes ZERO upstream HTTP calls
        $emptyResponse = $this->get('/cricket-rankings');
        $emptyResponse->assertStatus(200);
        $emptyResponse->assertSee('ICC Rankings Cache Not Yet Populated');
        Http::assertNothingSent();

        // Populate Redis cache directly
        Cache::put(
            CricketLiveApiService::iccRankingCacheKey('batting', 't20'),
            [
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
                        'rating' => 889,
                        'points' => 889,
                        'trend' => 'Flat',
                        'trend_direction' => 'flat',
                        'face_image_id' => '846031',
                        'image_url' => 'https://static.cricbuzz.com/a/img/v1/i1/c846031/i.jpg',
                    ],
                ],
                'synced_at' => now('Asia/Kolkata')->toIso8601String(),
            ],
            3600
        );

        $populatedResponse = $this->get('/cricket-rankings');
        $populatedResponse->assertStatus(200);
        $populatedResponse->assertSee('Abhishek Sharma');
        $populatedResponse->assertSee('889');
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

        // Ensure Livewire interactions trigger zero additional HTTP requests
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

    public function test_failed_upstream_sync_preserves_existing_redis_cache_and_legacy_route_redirects(): void
    {
        $key = CricketLiveApiService::iccRankingCacheKey('batting', 't20');
        Cache::put($key, [
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
                    'rating' => 889,
                    'points' => 889,
                    'trend' => 'Flat',
                    'trend_direction' => 'flat',
                    'face_image_id' => '846031',
                    'image_url' => 'https://static.cricbuzz.com/a/img/v1/i1/c846031/i.jpg',
                ],
            ],
            'synced_at' => '2026-11-25T05:30:00+05:30',
        ], 3600);

        Http::fake([
            '*' => Http::response(['success' => false], 500),
        ]);

        $service = app(CricketLiveApiService::class);
        $service->syncIccRankingsToRedis('batting', 't20');

        $preserved = $service->getIccRankingsFromRedis('batting', 't20');
        $this->assertCount(1, $preserved['rankings']);
        $this->assertSame('Abhishek Sharma', $preserved['rankings'][0]['name']);

        // Route parameter SEO navigation & legacy gender 301 redirect
        $this->get('/cricket-rankings/bowling/test')->assertStatus(200);
        $this->get('/cricket-rankings/men')->assertRedirect('/cricket-rankings')->assertStatus(301);
        $this->get('/cricket-rankings/women')->assertRedirect('/cricket-rankings')->assertStatus(301);
    }
}
