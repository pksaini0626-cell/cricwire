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
        Schema::table('skipped_series', function (Blueprint $table) {
            if (!Schema::hasColumn('skipped_series', 'format')) {
                $table->string('format')->nullable()->after('series_name');
            }
            if (!Schema::hasColumn('skipped_series', 'live_matches_count')) {
                $table->unsignedInteger('live_matches_count')->default(0)->after('format');
            }
            if (!Schema::hasColumn('skipped_series', 'upcoming_matches_count')) {
                $table->unsignedInteger('upcoming_matches_count')->default(0)->after('live_matches_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skipped_series', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('skipped_series', 'format')) {
                $cols[] = 'format';
            }
            if (Schema::hasColumn('skipped_series', 'live_matches_count')) {
                $cols[] = 'live_matches_count';
            }
            if (Schema::hasColumn('skipped_series', 'upcoming_matches_count')) {
                $cols[] = 'upcoming_matches_count';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
