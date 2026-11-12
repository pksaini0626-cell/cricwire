<?php

namespace Tests\Feature;

use App\Livewire\Pages\SchedulePage;
use Livewire\Livewire;
use Tests\TestCase;

class SchedulePageTest extends TestCase
{
    public function test_cricket_schedule_route_renders_successfully(): void
    {
        $response = $this->get('/cricket-schedule');

        $response->assertStatus(200);
        $response->assertSee('Match Fixtures & Schedule', false);
        $response->assertSee('Upcoming Matches');
        $response->assertSee('Recent Results');
    }

    public function test_livewire_schedule_tabs_and_category_switching(): void
    {
        Livewire::test(SchedulePage::class)
            ->assertSet('tab', 'upcoming')
            ->assertSet('category', 'all')
            ->call('setTab', 'recent')
            ->assertSet('tab', 'recent')
            ->call('setCategory', 't20')
            ->assertSet('category', 't20');
    }
}

