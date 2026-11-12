<?php

namespace Tests\Feature;

use App\Http\Controllers\Cricket\SessionController;
use App\Services\Cricket\RapidApiCricketService;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    public function test_show_endpoint_returns_json()
    {
        $response = $this->getJson('/api/matches/15329/sessions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'match_id',
                'data' => [
                    'innings_1' => ['inning', 'team_name', 'rows'],
                    'innings_2' => ['inning', 'team_name', 'rows'],
                    'active_inning',
                ],
            ]);
    }

    public function test_session_table_builder_with_mock_fancy_and_over_history()
    {
        $mockService = \Mockery::mock(RapidApiCricketService::class);
        $mockService->shouldReceive('getMatchFancy')->with('test_1')->andReturn([
            'status' => true,
            'data' => [
                [
                    'over' => 6,
                    'title' => '6 Over Runs',
                    'min' => 45,
                    'max' => 47,
                    'inning' => 1,
                ],
                [
                    'over' => 10,
                    'title' => '10 Over Runs',
                    'min' => 78,
                    'max' => 80,
                    'inning' => 1,
                ],
            ],
        ]);

        $mockService->shouldReceive('getMatchOverHistory')->with('test_1')->andReturn([
            'status' => true,
            'data' => [
                '1' => [
                    [
                        'team' => [
                            'over' => 6,
                            'score' => '51-0',
                        ],
                    ],
                    [
                        'team' => [
                            'over' => 10,
                            'score' => '75-1',
                        ],
                    ],
                ],
            ],
        ]);

        $matchDetail = [
            'match_type' => 'T20',
            'team_a_short' => 'StarsW',
            'team_b_short' => 'HeatW',
            'fav_team' => 'StarsW',
            'min_rate' => '47',
            'max_rate' => '48',
            'current_inning' => 1,
        ];

        $result = SessionController::getMatchSessionTable('test_1', $mockService, $matchDetail);

        $this->assertEquals(1, $result['active_inning']);
        $this->assertNotEmpty($result['innings_1']['rows']);

        // Check Over 6: Score 51/0 vs yes_target 47 -> Passed true ✔
        $row6 = collect($result['innings_1']['rows'])->firstWhere('over', 6);
        $this->assertNotNull($row6);
        $this->assertEquals('51/0', $row6['score']);
        $this->assertTrue($row6['passed']);
        $this->assertEquals('45-47', $row6['rate_display']);

        // Check Over 10: Score 75/1 vs yes_target 80 -> Passed false ✘
        $row10 = collect($result['innings_1']['rows'])->firstWhere('over', 10);
        $this->assertNotNull($row10);
        $this->assertEquals('75/1', $row10['score']);
        $this->assertFalse($row10['passed']);
    }
}

