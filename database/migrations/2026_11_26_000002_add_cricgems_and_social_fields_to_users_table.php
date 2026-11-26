<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'player_code')) {
                $table->string('player_code', 16)->nullable()->unique()->after('role');
            }
            if (!Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->foreignId('referred_by_user_id')
                    ->nullable()
                    ->after('player_code')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'referral_rewarded')) {
                $table->boolean('referral_rewarded')->default(false)->after('referred_by_user_id');
            }
            if (!Schema::hasColumn('users', 'cric_gems')) {
                $table->unsignedBigInteger('cric_gems')->default(0)->after('referral_rewarded');
            }
            if (!Schema::hasColumn('users', 'login_streak')) {
                $table->unsignedInteger('login_streak')->default(0)->after('cric_gems');
            }
            if (!Schema::hasColumn('users', 'last_streak_date')) {
                $table->date('last_streak_date')->nullable()->after('login_streak');
            }
            if (!Schema::hasColumn('users', 'battles_played')) {
                $table->unsignedInteger('battles_played')->default(0)->after('last_streak_date');
            }
            if (!Schema::hasColumn('users', 'battles_won')) {
                $table->unsignedInteger('battles_won')->default(0)->after('battles_played');
            }
        });

        // Backfill unique player_code for any existing users missing one
        $existingUsers = DB::table('users')
            ->whereNull('player_code')
            ->orWhere('player_code', '')
            ->select('id')
            ->get();

        foreach ($existingUsers as $row) {
            do {
                $code = 'CW-' . random_int(100000, 999999);
                $exists = DB::table('users')->where('player_code', $code)->exists();
            } while ($exists);

            DB::table('users')
                ->where('id', $row->id)
                ->update(['player_code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->dropForeign(['referred_by_user_id']);
            }

            $columnsToDrop = array_values(array_filter([
                'player_code',
                'referred_by_user_id',
                'referral_rewarded',
                'cric_gems',
                'login_streak',
                'last_streak_date',
                'battles_played',
                'battles_won',
            ], fn (string $col) => Schema::hasColumn('users', $col)));

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
