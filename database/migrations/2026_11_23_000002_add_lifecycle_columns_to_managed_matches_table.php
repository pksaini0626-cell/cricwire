<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('managed_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('managed_matches', 'series_id')) {
                $table->string('series_id')->nullable()->index()->after('match_id');
            }
            if (!Schema::hasColumn('managed_matches', 'match_number')) {
                $table->string('match_number')->nullable()->after('series_name');
            }
            if (!Schema::hasColumn('managed_matches', 'start_time_ist')) {
                $table->dateTime('start_time_ist')->nullable()->index()->after('start_time');
            }
            if (!Schema::hasColumn('managed_matches', 'pre_match_archived')) {
                $table->boolean('pre_match_archived')->default(false)->after('series_skipped');
            }
            if (!Schema::hasColumn('managed_matches', 'toss_fetched')) {
                $table->boolean('toss_fetched')->default(false)->after('pre_match_archived');
            }
            if (!Schema::hasColumn('managed_matches', 'toss_text')) {
                $table->string('toss_text')->nullable()->after('toss_fetched');
            }
            if (!Schema::hasColumn('managed_matches', 'toss_fetched_at')) {
                $table->dateTime('toss_fetched_at')->nullable()->after('toss_text');
            }
            if (!Schema::hasColumn('managed_matches', 'playing_xi_fetched')) {
                $table->boolean('playing_xi_fetched')->default(false)->after('toss_fetched_at');
            }
            if (!Schema::hasColumn('managed_matches', 'lifecycle_stage')) {
                $table->string('lifecycle_stage')->default('scheduled')->after('playing_xi_fetched');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('managed_matches', function (Blueprint $table) {
            $cols = [
                'series_id',
                'match_number',
                'start_time_ist',
                'pre_match_archived',
                'toss_fetched',
                'toss_text',
                'toss_fetched_at',
                'playing_xi_fetched',
                'lifecycle_stage',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('managed_matches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
