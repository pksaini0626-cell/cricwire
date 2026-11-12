<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_user_exists_and_can_authenticate(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'admin@cricfans.com')->first();

        $this->assertNotNull($user, 'Admin user was not found in the database.');
        $this->assertEquals('admin', $user->role);
        $this->assertTrue(Hash::check('Rudransh@321#', $user->password));

        // Test Filament admin panel access permission
        $this->assertTrue(in_array($user->role, ['admin', 'editor'], true));
    }
}
