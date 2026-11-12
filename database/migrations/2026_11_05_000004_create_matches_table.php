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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('api_match_id')->unique()->index();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('team_a_id')->constrained('teams');
            $table->foreignId('team_b_id')->constrained('teams');
            $table->string('slug')->unique()->index();
            $table->string('match_title');
            $table->enum('format', ['T20', 'ODI', 'TEST', 'T10', 'SIXES']);
            $table->string('venue');
            $table->timestamp('start_time')->index();
            $table->enum('status', ['upcoming', 'live', 'stumps', 'finished', 'abandoned'])->index();
            $table->boolean('is_pinned')->default(false);
            $table->string('toss_info')->nullable();
            $table->string('result_summary')->nullable();
            $table->json('scorecard_json')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};

