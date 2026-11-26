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
        if (!Schema::hasTable('squad_battles')) {
            Schema::create('squad_battles', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 180)->unique();
                $table->string('match_id', 64)->index();
                $table->string('match_title', 200);
                $table->string('match_format', 20);
                $table->string('series_slug', 160)->nullable();
                $table->string('match_slug', 180)->nullable();
                $table->string('team_a', 100);
                $table->string('team_b', 100);
                $table->unsignedTinyInteger('team_size')->default(5);
                $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('opponent_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('toss_winner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('current_turn_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('challenger_last_seen_at')->nullable();
                $table->timestamp('opponent_last_seen_at')->nullable();
                $table->string('status', 30)->default('pending_accept')->index();
                $table->timestamp('match_starts_at')->nullable();
                $table->timestamp('locks_at')->nullable();
                $table->unsignedInteger('challenger_runs')->default(0);
                $table->unsignedInteger('opponent_runs')->default(0);
                $table->unsignedInteger('run_margin')->default(0);
                $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();

                $table->index(['challenger_id', 'status']);
                $table->index(['opponent_id', 'status']);
                $table->index(['match_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('squad_battles');
    }
};
