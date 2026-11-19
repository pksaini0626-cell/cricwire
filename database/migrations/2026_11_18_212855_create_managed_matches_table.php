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
        Schema::create('managed_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_id')->unique()->index();
            $table->string('series_name')->nullable();
            $table->string('match_desc')->nullable();
            $table->string('format')->nullable(); // T20, ODI, Test
            $table->string('team_a')->nullable();
            $table->string('team_b')->nullable();
            $table->string('state')->nullable(); // Live, Scheduled, Complete
            $table->string('start_time')->nullable();
            $table->boolean('is_covered')->default(true); // when false, skipped from live polling AND hidden from frontend
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('managed_matches');
    }
};
