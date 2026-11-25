<?php

namespace Tests\Unit;

use App\Services\Cricket\CricketLiveApiService;
use Carbon\Carbon;
use Tests\TestCase;

class TodayUpcomingMatchesFilterTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_filters_strictly_to_today_ist_and_sorts_chronologically(): void
    {
        // Freeze time to 2026-11-26 02:00:00 IST (2026-11-25 20:30:00 UTC)
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 2, 0, 0, 'Asia/Kolkata'));

        $service = new CricketLiveApiService();

        $matches = [
            // 1. Live match today -> EXCLUDE
            [
                'match_id' => 101,
                'series_name' => 'Live Series',
                'state' => 'Live',
                'status' => 'In Progress',
                'score_a' => '120/2 (14.1 ov)',
                'start_time' => '2026-11-26T03:00:00Z',
            ],
            // 2. Completed match today -> EXCLUDE
            [
                'match_id' => 102,
                'series_name' => 'Done Series',
                'state' => 'Complete',
                'result' => 'India won by 6 wickets',
                'start_time' => '2026-11-26T03:00:00Z',
            ],
            // 3. Not covered / skipped -> EXCLUDE
            [
                'match_id' => 103,
                'series_name' => 'Hidden Series',
                'state' => 'Scheduled',
                'is_covered' => false,
                'start_time' => '2026-11-26T06:00:00Z',
            ],
            // 4. Yesterday in IST (Nov 25 20:00 IST = Nov 25 14:30 UTC) -> EXCLUDE
            [
                'match_id' => 104,
                'series_name' => 'Yesterday Cup',
                'state' => 'Scheduled',
                'status_note' => 'Match starts at Nov 25, 14:30 GMT',
            ],
            // 5. Tomorrow in IST due to +05:30 rollover (Nov 26 19:00 UTC = Nov 27 00:30 IST) -> EXCLUDE
            [
                'match_id' => 105,
                'series_name' => 'Tomorrow Midnight Cup',
                'state' => 'Scheduled',
                'start_time' => '2026-11-26T19:00:00Z',
            ],
            // 6. Test match at Stumps with no start timestamp -> EXCLUDE
            [
                'match_id' => 106,
                'series_name' => 'Test Series',
                'state' => 'Scheduled',
                'status_note' => 'Day 2: Stumps - South Africa lead by 403 runs',
            ],
            // 7. Today IST via Unix ms (2026-11-26 19:30 IST = 2026-11-26 14:00 UTC = 1795701600000 ms) -> KEEP (3rd)
            [
                'match_id' => 203,
                'series_name' => 'Evening T20',
                'state' => 'Scheduled',
                'timestamp' => Carbon::create(2026, 11, 26, 14, 0, 0, 'UTC')->timestamp * 1000,
            ],
            // 8. Today IST via status_note GMT (Nov 26 03:30 GMT = 09:00 AM IST) -> KEEP (2nd)
            [
                'match_id' => 202,
                'series_name' => 'Morning Cup',
                'state' => 'Scheduled',
                'status_note' => 'Match starts at Nov 26, 03:30 GMT',
            ],
            // 9. Today IST via UTC rollover (Nov 25 21:00 UTC = Nov 26 02:30 AM IST) -> KEEP (1st)
            [
                'match_id' => 201,
                'series_name' => 'Early Dawn Cup',
                'state' => 'Scheduled',
                'match_date' => '2026-11-25',
                'match_time' => '21:00:00',
            ],
            // 10. Today IST explicit IST label (Nov 26 08:00 PM IST) -> KEEP (4th)
            [
                'match_id' => 204,
                'series_name' => 'Prime Time League',
                'state' => 'Scheduled',
                'date_time' => 'Nov 26, 08:00 PM IST',
            ],
        ];

        $result = $service->filterTodayUpcomingMatches($matches);

        $this->assertCount(4, $result);
        $this->assertSame([201, 202, 203, 204], array_column($result, 'match_id'));
        $this->assertSame('02:30 AM IST', $result[0]['ist_time_label']);
        $this->assertSame('09:00 AM IST', $result[1]['ist_time_label']);
        $this->assertSame('07:30 PM IST', $result[2]['ist_time_label']);
        $this->assertSame('08:00 PM IST', $result[3]['ist_time_label']);
    }
}
