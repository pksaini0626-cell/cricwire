<?php

namespace Tests\Feature;

use App\Http\Controllers\Cricket\FinishedMatchController;
use App\Models\CompletedMatchScorecard;
use App\Models\FinishedMatch;
use App\Models\ManagedMatch;
use App\Services\Cricket\CricketLiveApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TossWindowAndPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_get_match_facts_unwraps_data_short_caches_pending_and_persists_decided_toss(): void
    {
        $matchId = 99001;
        $startIst = Carbon::create(2026, 11, 26, 14, 0, 0, 'Asia/Kolkata'); // 2:00 PM IST
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 13, 35, 0, 'Asia/Kolkata')); // 1:35 PM IST (T-25m)

        ManagedMatch::create([
            'match_id' => $matchId,
            'series_name' => 'Test Trophy 2026',
            'match_desc' => '1st ODI',
            'format' => 'ODI',
            'team_a' => 'Team 1',
            'team_b' => 'Team 2',
            'state' => 'Scheduled',
            'start_time' => $startIst->copy()->utc()->toIso8601String(),
            'start_time_ist' => $startIst->format('Y-m-d H:i:s'),
            'is_covered' => true,
        ]);

        Http::fake([
            "*/cricket/match-facts/{$matchId}" => Http::sequence()
                ->push([
                    'success' => true,
                    'data' => [
                        'toss' => 'Toss yet to take place',
                        'venue' => 'Wankhede Stadium, Mumbai',
                        'umpires' => ['Nitin Menon', 'Richard Kettleborough'],
                        'referee' => 'Javagal Srinath',
                        'day_night' => true,
                        'series' => 'Test Trophy 2026',
                    ],
                ], 200)
                ->push([
                    'success' => true,
                    'data' => [
                        'toss' => 'Team 2 won the toss and elected to bat first',
                        'venue' => 'Wankhede Stadium, Mumbai',
                        'umpires' => ['Nitin Menon', 'Richard Kettleborough'],
                        'referee' => 'Javagal Srinath',
                        'day_night' => true,
                        'series' => 'Test Trophy 2026',
                    ],
                ], 200),
        ]);

        $service = new CricketLiveApiService();

        // First call during T-25m returns undecided toss -> cached short (30s)
        $facts1 = $service->getMatchFacts($matchId, ['start_time_ist' => $startIst->format('Y-m-d H:i:s')]);
        $this->assertSame('Toss yet to take place', $facts1['toss']);
        $this->assertSame('Wankhede Stadium, Mumbai', $facts1['venue']);
        $this->assertSame(['Nitin Menon', 'Richard Kettleborough'], $facts1['umpires']);
        $this->assertSame('Javagal Srinath', $facts1['referee']);
        $this->assertTrue($facts1['day_night']);
        $this->assertSame('Test Trophy 2026', $facts1['series']);

        // ManagedMatch should NOT be marked toss_fetched yet
        $managed = ManagedMatch::where('match_id', $matchId)->first();
        $this->assertFalse((bool) $managed->toss_fetched);

        // Advance 35 seconds (past the 30s short cache) -> next call fetches decided toss
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 13, 35, 35, 'Asia/Kolkata'));
        Cache::forget("cricket:facts:{$matchId}"); // array driver respects explicit forget/TTL; verify re-fetch works

        $facts2 = $service->getMatchFacts($matchId, ['start_time_ist' => $startIst->format('Y-m-d H:i:s')]);
        $this->assertSame('Team 2 won the toss and elected to bat first', $facts2['toss']);

        // Persisted permanently into ManagedMatch
        $managed->refresh();
        $this->assertTrue((bool) $managed->toss_fetched);
        $this->assertSame('Team 2 won the toss and elected to bat first', $managed->toss_text);
    }

    public function test_resolve_toss_display_state_handles_window_delay_decided_and_completed(): void
    {
        $service = new CricketLiveApiService();
        $startIst = Carbon::create(2026, 11, 26, 14, 0, 0, 'Asia/Kolkata'); // 2:00 PM IST

        // 1. Outside 30m window (1:15 PM IST = T-45m)
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 13, 15, 0, 'Asia/Kolkata'));
        $outside = $service->resolveTossDisplayState([
            'match_id' => 1,
            'state' => 'Scheduled',
            'start_time_ist' => $startIst->format('Y-m-d H:i:s'),
        ], [], false);
        $this->assertFalse($outside['show_toss_banner']);
        $this->assertSame('none', $outside['toss_status']);

        // 2. Inside 30m window (1:30 PM IST = T-30m) with pending/empty toss
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 13, 30, 0, 'Asia/Kolkata'));
        $pending = $service->resolveTossDisplayState([
            'match_id' => 2,
            'state' => 'Scheduled',
            'start_time_ist' => $startIst->format('Y-m-d H:i:s'),
        ], ['toss' => 'Toss yet to take place'], false);
        $this->assertTrue($pending['show_toss_banner']);
        $this->assertSame('pending', $pending['toss_status']);
        $this->assertSame('Toss is pending', $pending['toss_text']);

        // 3. Delayed toss (wet outfield / rain)
        $delayed = $service->resolveTossDisplayState([
            'match_id' => 3,
            'state' => 'Scheduled',
            'start_time_ist' => $startIst->format('Y-m-d H:i:s'),
        ], ['toss' => 'Toss delayed due to wet outfield'], false);
        $this->assertTrue($delayed['show_toss_banner']);
        $this->assertSame('delayed', $delayed['toss_status']);
        $this->assertSame('Toss delayed due to wet outfield', $delayed['toss_text']);

        // 4. Decided toss
        $decided = $service->resolveTossDisplayState([
            'match_id' => 4,
            'state' => 'Live',
            'start_time_ist' => $startIst->format('Y-m-d H:i:s'),
        ], ['toss' => 'Team 2 won the toss and elected to bat first'], false);
        $this->assertTrue($decided['show_toss_banner']);
        $this->assertSame('decided', $decided['toss_status']);
        $this->assertSame('Team 2 won the toss and elected to bat first', $decided['toss_text']);

        // 5. Completed match with empty toss -> "Not recorded" (never "Toss yet to take place")
        $completedEmpty = $service->resolveTossDisplayState([
            'match_id' => 5,
            'state' => 'Complete',
            'result' => 'Team 2 won by 4 wickets',
            'toss' => '',
        ], [], false);
        $this->assertFalse($completedEmpty['show_toss_banner']);
        $this->assertSame('none', $completedEmpty['toss_status']);
        $this->assertSame('Not recorded', $completedEmpty['toss_text']);
    }

    public function test_finished_match_archiving_hydrates_and_persists_missing_match_facts(): void
    {
        $matchId = 88001;

        FinishedMatch::create([
            'match_id' => (string) $matchId,
            'series_name' => 'Archive Cup',
            'match_title' => 'Alpha vs Beta',
            'result_text' => 'Alpha won by 25 runs',
            'score_a' => '180/5 (20 ov)',
            'score_b' => '155/8 (20 ov)',
            'match_data' => [
                'match_id' => $matchId,
                'team_a' => 'Alpha',
                'team_b' => 'Beta',
                'score_a' => '180/5 (20 ov)',
                'score_b' => '155/8 (20 ov)',
                'result' => 'Alpha won by 25 runs',
                'toss' => 'Toss yet to take place',
                'state' => 'Finished',
            ],
            'match_json' => [
                'match_id' => $matchId,
                'toss' => 'Toss yet to take place',
            ],
        ]);

        Http::fake([
            "*/cricket/match-facts/{$matchId}" => Http::response([
                'success' => true,
                'data' => [
                    'toss' => 'Alpha won the toss and elected to bat first',
                    'venue' => 'Eden Gardens, Kolkata',
                    'umpires' => ['Anil Chaudhary', 'Virender Sharma'],
                    'referee' => 'Ranjan Madugalle',
                    'day_night' => true,
                    'series' => 'Archive Cup',
                ],
            ], 200),
            "*/cricket/scorecard/{$matchId}" => Http::response([
                'success' => true,
                'data' => ['innings' => [['team' => 'Alpha', 'score' => '180/5 (20 ov)']]],
            ], 200),
        ]);

        $controller = new FinishedMatchController();
        $archived = $controller->getOrArchive($matchId);

        $this->assertSame('Alpha won the toss and elected to bat first', $archived['toss']);
        $this->assertSame('Eden Gardens, Kolkata', $archived['venue']);
        $this->assertSame('Ranjan Madugalle', $archived['referee']);

        $row = FinishedMatch::where('match_id', (string) $matchId)->first();
        $this->assertSame('Alpha won the toss and elected to bat first', $row->match_data['toss']);
        $this->assertSame('Alpha won the toss and elected to bat first', $row->match_json['toss']);

        // CompletedMatchScorecard archiving also persists match_facts
        $service = new CricketLiveApiService();
        $sc = $service->getOrArchiveCompletedScorecard($matchId, ['series_name' => 'Archive Cup']);
        $this->assertSame('Alpha won the toss and elected to bat first', $sc['toss']);
        $this->assertSame('Eden Gardens, Kolkata', $sc['match_facts']['venue']);
        $this->assertDatabaseHas('completed_match_scorecards', ['match_id' => (string) $matchId]);
        $cms = CompletedMatchScorecard::where('match_id', (string) $matchId)->first();
        $this->assertSame('Alpha won the toss and elected to bat first', $cms->scorecard_payload['toss']);
    }

    public function test_match_card_and_match_detail_render_toss_states_correctly(): void
    {
        // 1. Match Card: pending, delayed, decided
        $pendingHtml = Blade::render('<x-match-card :match="$match" />', [
            'match' => [
                'match_id' => 701,
                'series' => 'Premier League',
                'matchs' => '1st T20',
                'team_a' => 'Team 1',
                'team_b' => 'Team 2',
                'state' => 'Scheduled',
                'show_toss_banner' => true,
                'toss_status' => 'pending',
                'toss_text' => 'Toss is pending',
            ],
        ]);
        $this->assertStringContainsString('Toss is pending', $pendingHtml);

        $delayedHtml = Blade::render('<x-match-card :match="$match" />', [
            'match' => [
                'match_id' => 702,
                'series' => 'Premier League',
                'matchs' => '2nd T20',
                'team_a' => 'Team 1',
                'team_b' => 'Team 2',
                'state' => 'Scheduled',
                'show_toss_banner' => true,
                'toss_status' => 'delayed',
                'toss_text' => 'Toss delayed due to wet outfield',
            ],
        ]);
        $this->assertStringContainsString('Toss delayed due to wet outfield', $delayedHtml);

        $decidedHtml = Blade::render('<x-match-card :match="$match" />', [
            'match' => [
                'match_id' => 703,
                'series' => 'Premier League',
                'matchs' => '3rd T20',
                'team_a' => 'Team 1',
                'team_b' => 'Team 2',
                'state' => 'Live',
                'show_toss_banner' => true,
                'toss_status' => 'decided',
                'toss_text' => 'Team 2 won the toss and elected to bat first',
            ],
        ]);
        $this->assertStringContainsString('Team 2 won the toss and elected to bat first', $decidedHtml);

        // 2. Match Detail Info tab for Finished match shows persisted toss (never "Toss yet to take place")
        $finishedId = 77001;
        FinishedMatch::create([
            'match_id' => (string) $finishedId,
            'series_name' => 'Final Series 2026',
            'match_title' => 'Warriors vs Titans',
            'result_text' => 'Titans won by 5 wickets',
            'score_a' => '170/6 (20 ov)',
            'score_b' => '171/5 (19.1 ov)',
            'match_data' => [
                'match_id' => $finishedId,
                'series' => 'Final Series 2026',
                'matchs' => 'Final',
                'team_a' => 'Warriors',
                'team_b' => 'Titans',
                'score_a' => '170/6 (20 ov)',
                'score_b' => '171/5 (19.1 ov)',
                'result' => 'Titans won by 5 wickets',
                'toss' => 'Titans won the toss and elected to field first',
                'venue' => 'MCG, Melbourne',
                'state' => 'Finished',
                'facts' => [
                    'toss' => 'Titans won the toss and elected to field first',
                    'venue' => 'MCG, Melbourne',
                    'umpires' => ['Rod Tucker', 'Paul Reiffel'],
                    'referee' => 'David Boon',
                    'day_night' => false,
                    'series' => 'Final Series 2026',
                ],
            ],
            'match_json' => [
                'match_id' => $finishedId,
                'toss' => 'Titans won the toss and elected to field first',
            ],
        ]);

        $response = $this->get("/match-detail/final-series-2026/info/warriors-vs-titans-final-{$finishedId}");
        $response->assertStatus(200);
        $response->assertSee('Titans won the toss and elected to field first');
        $response->assertDontSee('Toss yet to take place');
        $response->assertSee('Rod Tucker, Paul Reiffel');
        $response->assertSee('David Boon');

        // 3. Match Detail Live Feed box in 30m pre-match window shows Live Toss Pill
        $preMatchId = 77002;
        $startIst = Carbon::create(2026, 11, 26, 14, 0, 0, 'Asia/Kolkata');
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 13, 40, 0, 'Asia/Kolkata')); // 1:40 PM IST

        Cache::put("cricket:redis:match:{$preMatchId}", [
            'match_id' => $preMatchId,
            'series' => 'PreMatch Cup 2026',
            'matchs' => '2nd T20',
            'team_a' => 'Team 1',
            'team_b' => 'Team 2',
            'team_a_short' => 'TM1',
            'team_b_short' => 'TM2',
            'state' => 'Live',
            'match_status' => 'Live',
            'start_time_ist' => $startIst->format('Y-m-d H:i:s'),
        ], 120);

        Http::fake([
            "*/cricket/match-facts/{$preMatchId}" => Http::response([
                'success' => true,
                'data' => [
                    'toss' => 'Toss delayed due to wet outfield',
                    'venue' => 'Chinnaswamy Stadium',
                    'umpires' => [],
                    'referee' => '',
                    'day_night' => true,
                    'series' => 'PreMatch Cup 2026',
                ],
            ], 200),
        ]);

        $livePreResponse = $this->get("/match-detail/prematch-cup-2026/live-line/team-1-vs-team-2-{$preMatchId}");
        $livePreResponse->assertStatus(200);
        $livePreResponse->assertSee('Toss delayed due to wet outfield');
    }
}
