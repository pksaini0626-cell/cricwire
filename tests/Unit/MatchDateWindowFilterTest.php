<?php

namespace Tests\Unit;

use App\Services\Cricket\RapidApiCricketService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MatchDateWindowFilterTest extends TestCase
{
    private RapidApiCricketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RapidApiCricketService('dummy_key', 'dummy_host', 'http://dummy.test');
    }

    public function test_upcoming_matches_filters_within_next_3_days_and_sorts_chronologically(): void
    {
        $now = Carbon::now('Asia/Kolkata');

        $matches = [
            ['match_id' => 1, 'match_date' => $now->copy()->addDays(2)->format('Y-m-d'), 'match_time' => '14:00'],
            ['match_id' => 2, 'match_date' => $now->copy()->addDays(1)->format('Y-m-d'), 'match_time' => '10:00'],
            ['match_id' => 3, 'match_date' => $now->copy()->addHours(5)->format('Y-m-d'), 'match_time' => $now->copy()->addHours(5)->format('H:i')],
            ['match_id' => 4, 'match_date' => $now->copy()->addDays(10)->format('Y-m-d'), 'match_time' => '10:00'], // outside window
            ['match_id' => 5, 'match_date' => $now->copy()->subDays(5)->format('Y-m-d'), 'match_time' => '10:00'], // outside window
        ];

        $filtered = $this->service->filterMatchesByDateWindow($matches, 'upcoming');

        $this->assertCount(3, $filtered);
        // Earliest first: id 3, then 2, then 1
        $this->assertEquals(3, $filtered[0]['match_id']);
        $this->assertEquals(2, $filtered[1]['match_id']);
        $this->assertEquals(1, $filtered[2]['match_id']);
    }

    public function test_recent_matches_filters_within_previous_2_days_and_sorts_reverse_chronologically(): void
    {
        $now = Carbon::now('Asia/Kolkata');

        $matches = [
            ['match_id' => 10, 'match_date' => $now->copy()->subDays(1)->format('Y-m-d'), 'match_time' => '10:00'],
            ['match_id' => 20, 'match_date' => $now->copy()->subHours(3)->format('Y-m-d'), 'match_time' => $now->copy()->subHours(3)->format('H:i')],
            ['match_id' => 30, 'match_date' => $now->copy()->subDays(2)->format('Y-m-d'), 'match_time' => '09:00'],
            ['match_id' => 40, 'match_date' => $now->copy()->subDays(10)->format('Y-m-d'), 'match_time' => '10:00'], // outside window
        ];

        $filtered = $this->service->filterMatchesByDateWindow($matches, 'recent');

        $this->assertCount(3, $filtered);
        // Most recent first: id 20, then 10, then 30
        $this->assertEquals(20, $filtered[0]['match_id']);
        $this->assertEquals(10, $filtered[1]['match_id']);
        $this->assertEquals(30, $filtered[2]['match_id']);
    }

    public function test_smart_fallback_returns_fallback_limit_when_fewer_than_3_matches_match_window(): void
    {
        $now = Carbon::now('Asia/Kolkata');

        // Only 1 match in window, 15 far out
        $matches = [
            ['match_id' => 1, 'match_date' => $now->copy()->addDays(1)->format('Y-m-d')],
        ];
        for ($i = 2; $i <= 15; $i++) {
            $matches[] = ['match_id' => $i, 'match_date' => $now->copy()->addDays(20 + $i)->format('Y-m-d')];
        }

        $filtered = $this->service->filterMatchesByDateWindow($matches, 'upcoming', fallbackLimit: 10);

        // Fallback triggered: returns first 10 items
        $this->assertCount(10, $filtered);
        $this->assertEquals(1, $filtered[0]['match_id']);
        $this->assertEquals(10, $filtered[9]['match_id']);
    }

    public function test_maximum_cap_limits_to_20_matches(): void
    {
        $now = Carbon::now('Asia/Kolkata');

        $matches = [];
        for ($i = 1; $i <= 35; $i++) {
            $matches[] = ['match_id' => $i, 'match_date' => $now->copy()->addDays(1)->format('Y-m-d'), 'match_time' => '10:00'];
        }

        $filtered = $this->service->filterMatchesByDateWindow($matches, 'upcoming');

        $this->assertCount(20, $filtered);
    }
}
