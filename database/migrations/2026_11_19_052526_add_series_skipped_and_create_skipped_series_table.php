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
        if (Schema::hasTable('managed_matches')) {
            Schema::table('managed_matches', function (Blueprint $table) {
                if (!Schema::hasColumn('managed_matches', 'series_skipped')) {
                    $table->boolean('series_skipped')->default(false)->after('is_covered');
                }
            });
        }

        if (!Schema::hasTable('skipped_series')) {
            Schema::create('skipped_series', function (Blueprint $table) {
                $table->id();
                $table->string('series_name')->unique()->index();
                $table->boolean('is_skipped')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skipped_series');

        if (Schema::hasTable('managed_matches')) {
            Schema::table('managed_matches', function (Blueprint $table) {
                if (Schema::hasColumn('managed_matches', 'series_skipped')) {
                    $table->dropColumn('series_skipped');
                }
            });
        }
    }
};
