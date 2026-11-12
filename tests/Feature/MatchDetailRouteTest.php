<?php

namespace Tests\Feature;

use App\Helpers\CricketSlugHelper;
use App\Models\CricketMatch;
use App\Services\Cricket\CricketUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchDetailRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cricket_slug_helper_makes_dynamic_slugs_and_extracts_id(): void
    {
        $match = [
            'series' => 'CSA T20 Challenge',
            'season' => '2026',
            'team_a' => 'Warriors',
            'team_b' => 'Boland',
            'match_title' => 'Super Eights',
            'id' => 15444,
        ];

        $seriesSlug = CricketSlugHelper::makeSeriesSlug($match);
        $this->assertEquals('csa-t20-challenge-2026', $seriesSlug);

        $matchSlug = CricketSlugHelper::makeMatchSlug($match, 15444);
        $this->assertEquals('warriors-vs-boland-super-eights-15444', $matchSlug);

        $extractedId = CricketSlugHelper::extractMatchId($matchSlug);
        $this->assertEquals('15444', $extractedId);

        $numericExtracted = CricketSlugHelper::extractMatchId('15444');
        $this->assertEquals('15444', $numericExtracted);
    }

    public function test_cricket_slug_helper_fallback_values(): void
    {
        $emptyMatch = [];

        $seriesSlug = CricketSlugHelper::makeSeriesSlug($emptyMatch);
        $this->assertEquals('cricket-tournament', $seriesSlug);

        $matchSlug = CricketSlugHelper::makeMatchSlug($emptyMatch, 999);
        $this->assertEquals('team-a-vs-team-b-999', $matchSlug);
    }

    public function test_cricket_slug_helper_generates_full_match_url(): void
    {
        $match = [
            'series_name' => 'IPL',
            'year' => '2026',
            'team_a' => 'Chennai Super Kings',
            'team_b' => 'Mumbai Indians',
            'round' => 'Qualifier 1',
            'match_id' => 7890,
        ];

        $url = CricketSlugHelper::getMatchUrl($match, 'commentary');
        $this->assertStringContainsString('/match-detail/ipl-2026/commentary/chennai-super-kings-vs-mumbai-indians-qualifier-1-7890', $url);

        $defaultUrl = CricketSlugHelper::getMatchUrl($match);
        $this->assertStringContainsString('/match-detail/ipl-2026/live-line/chennai-super-kings-vs-mumbai-indians-qualifier-1-7890', $defaultUrl);
    }

    public function test_match_detail_new_seo_route_renders_successfully(): void
    {
        $response = $this->get('/match-detail/csa-t20-challenge-2026/commentary/warriors-vs-boland-super-eights-15444');
        $response->assertStatus(200);
        $response->assertSee('Commentary');
        $response->assertSee('Scorecard');
        $response->assertSee('Live Line');
        $response->assertSee('Squad');
    }

    public function test_match_detail_squad_tab_renders_successfully(): void
    {
        $response = $this->get('/match-detail/csa-t20-challenge-2026/squad/warriors-vs-boland-super-eights-15444');
        $response->assertStatus(200);
        $response->assertSee('Squads');
    }

    public function test_match_detail_legacy_url_redirects_with_301_to_seo_route(): void
    {
        $response = $this->get('/live-cricket-score/15444/commentary');
        $response->assertStatus(301);
        $response->assertRedirect('/match-detail/cricket-tournament/commentary/match-15444');
    }

    public function test_match_detail_route_rejects_invalid_tabs(): void
    {
        $response = $this->get('/match-detail/csa-t20-challenge-2026/invalid-tab/warriors-vs-boland-super-eights-15444');
        $response->assertStatus(404);
    }
}
