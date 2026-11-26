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
        if (!Schema::hasTable('battles')) {
            Schema::create('battles', function (Blueprint $table) {
                $table->id();
                $table->string('game_type', 40)->index();
                $table->string('match_id', 64)->index();
                $table->string('match_title', 200);
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('opponent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('entry_gems')->default(1);
                $table->string('creator_pick', 100);
                $table->string('opponent_pick', 100)->nullable();
                $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 25)->default('open')->index();
                $table->timestamp('locks_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();

                $table->index(['creator_id', 'status']);
                $table->index(['opponent_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battles');
    }
};
