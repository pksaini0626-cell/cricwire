<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LiveEngineConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MorePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_more_page_renders_successfully(): void
    {
        $response = $this->get('/more');
        $response->assertStatus(200);
        $response->assertSee('More & Settings');
        $response->assertSee('Cricwire Premium');
        $response->assertSee('₹149');
        $response->assertSee('₹399');
        $response->assertSee('₹999');
    }

    public function test_profile_route_redirects_permanently_to_more(): void
    {
        $response = $this->get('/profile');
        $response->assertStatus(301);
        $response->assertRedirect('/more');
    }

    public function test_legal_pages_render_successfully(): void
    {
        $responseTerms = $this->get('/terms-and-conditions');
        $responseTerms->assertStatus(200);
        $responseTerms->assertSee('Terms & Conditions');

        $responsePrivacy = $this->get('/privacy-policy');
        $responsePrivacy->assertStatus(200);
        $responsePrivacy->assertSee('Privacy Policy');
    }

    public function test_authenticated_user_can_delete_account(): void
    {
        $user = User::factory()->create([
            'email' => 'delete_me@example.com',
        ]);

        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Livewire\Pages\MorePage::class)
            ->call('deleteAccount')
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
        $this->assertGuest();
    }
}

