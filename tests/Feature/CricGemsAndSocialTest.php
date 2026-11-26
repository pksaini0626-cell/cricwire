<?php

namespace Tests\Feature;

use App\Livewire\Pages\MorePage;
use App\Models\CricGemTransaction;
use App\Models\Friendship;
use App\Models\User;
use App\Services\CricGemsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CricGemsAndSocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_cricgems_double_entry_credit_debit_and_non_negative_guard(): void
    {
        $service = app(CricGemsService::class);
        $user = User::factory()->create();

        $code = $service->ensurePlayerCode($user);
        $this->assertMatchesRegularExpression('/^CW-\d{6}$/', $code);

        $service->grantJoiningBonusIfEligible($user);
        $service->grantJoiningBonusIfEligible($user); // idempotent

        $user->refresh();
        $this->assertSame(500, $user->cric_gems);
        $this->assertSame(1, CricGemTransaction::where('user_id', $user->id)->where('type', 'joining_bonus')->count());

        $service->debit($user, 100, 'battle_entry', 'Test Battle Entry');
        $user->refresh();
        $this->assertSame(400, $user->cric_gems);

        $this->expectException(RuntimeException::class);
        $service->debit($user, 500, 'battle_entry', 'Should Fail');
    }

    public function test_ist_daily_streak_and_anti_fraud_referral_unlock(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Kolkata'));

        try {
            $service = app(CricGemsService::class);
            $referrer = User::factory()->create();
            $referrerCode = $service->ensurePlayerCode($referrer);

            $referee = User::factory()->create();
            $service->ensurePlayerCode($referee);
            $service->grantJoiningBonusIfEligible($referee);

            // Attach referral at signup
            $service->applyReferralCode($referee, $referrerCode);
            $referee->refresh();
            $referrer->refresh();
            $this->assertSame($referrer->id, $referee->referred_by_user_id);
            $this->assertFalse($referee->referral_rewarded);
            $this->assertSame(0, $referrer->cric_gems);

            // Day 1 streak (+5 CricGems) -> referral still locked
            $day1 = $service->processDailyStreak($referee);
            $this->assertSame(['streak' => 1, 'reward' => 5], $day1);
            $this->assertNull($service->processDailyStreak($referee)); // same IST day

            $referee->refresh();
            $referrer->refresh();
            $this->assertFalse($referee->referral_rewarded);
            $this->assertSame(0, $referrer->cric_gems);

            // Advance to next IST day -> Day 2 streak (+10 CricGems) -> referral unlocks +500 for referrer
            Carbon::setTestNow(Carbon::create(2026, 11, 27, 9, 0, 0, 'Asia/Kolkata'));
            $day2 = $service->processDailyStreak($referee);
            $this->assertSame(['streak' => 2, 'reward' => 10], $day2);

            $referee->refresh();
            $referrer->refresh();
            $this->assertTrue($referee->referral_rewarded);
            $this->assertSame(500, $referrer->cric_gems);
            $this->assertSame(515, $referee->cric_gems);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_capture_referral_middleware_stores_code_in_session(): void
    {
        $response = $this->get('/more?ref=cw-123456');
        $response->assertStatus(200);
        $response->assertSessionHas('referral_code', 'CW-123456');
    }

    public function test_more_page_player_dashboard_qr_passbook_and_friends_workflow(): void
    {
        $service = app(CricGemsService::class);

        $alice = User::factory()->create(['name' => 'Alice Striker']);
        $bob = User::factory()->create(['name' => 'Bob Yorker']);
        $service->ensurePlayerCode($alice);
        $service->ensurePlayerCode($bob);

        $this->actingAs($alice);

        $response = $this->get('/more');
        $response->assertStatus(200);
        $response->assertSee($alice->player_code);
        $response->assertSee('CricGems Balance');
        $response->assertSee('Daily Login Streak');
        $response->assertSee('Total Winnings');
        $response->assertSee('All your CricGems balance, daily streak, battle stats, and friends list will be permanently deleted');

        // Search and send friend request to Bob
        Livewire::test(MorePage::class)
            ->set('friendSearch', $bob->player_code)
            ->assertSee($bob->name)
            ->call('sendFriendRequest', $bob->id)
            ->assertSee("Friend request sent to {$bob->name}!");

        $friendship = Friendship::where('sender_id', $alice->id)->where('receiver_id', $bob->id)->first();
        $this->assertNotNull($friendship);
        $this->assertSame('pending', $friendship->status);

        // Bob accepts request
        $this->actingAs($bob);
        Livewire::test(MorePage::class)
            ->assertSee($alice->name)
            ->call('acceptFriendRequest', $friendship->id)
            ->assertSee("You and {$alice->name} are now friends!");

        $friendship->refresh();
        $this->assertSame('accepted', $friendship->status);

        // Toggle Passbook
        Livewire::test(MorePage::class)
            ->call('togglePassbook')
            ->assertSee('CricGems Passbook (Latest 15 Entries)')
            ->assertSee('Welcome Joining Bonus (+500 CricGems)');

        // Remove friend
        Livewire::test(MorePage::class)
            ->call('removeFriend', $alice->id)
            ->assertSee('Friend removed from your network.');

        $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
    }

    public function test_legal_pages_include_cricgems_policy_and_filament_users_loads(): void
    {
        $privacy = $this->get('/privacy-policy');
        $privacy->assertStatus(200);
        $privacy->assertSee('Virtual Loyalty Points (CricGems) & Fan Battles Policy');
        $privacy->assertSee('Non-Transferable Between Users');

        $terms = $this->get('/terms-and-conditions');
        $terms->assertStatus(200);
        $terms->assertSee('Virtual Loyalty Points (CricGems) & Fan Battles Policy');
        $terms->assertSee('No Redemption, Conversion, or Withdrawal');

        $admin = User::factory()->create(['role' => 'admin']);
        app(CricGemsService::class)->ensurePlayerCode($admin);

        $this->actingAs($admin);
        $adminUsers = $this->get('/admin/users');
        $adminUsers->assertStatus(200);
        $adminUsers->assertSee($admin->player_code);
    }
}
