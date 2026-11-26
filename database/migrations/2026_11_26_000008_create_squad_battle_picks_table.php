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
        if (!Schema::hasTable('squad_battle_picks')) {
            Schema::create('squad_battle_picks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('squad_battle_id')->constrained('squad_battles')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('player_id', 64)->nullable()->index();
                $table->string('player_name', 120);
                $table->string('player_team', 100);
                $table->string('player_role', 50)->nullable();
                $table->unsignedTinyInteger('pick_order');
                $table->boolean('is_playing_xi')->default(false);
                $table->unsignedInteger('runs_scored')->default(0);
                $table->boolean('did_not_bat')->default(true);
                $table->timestamps();

                $table->index(['squad_battle_id', 'user_id']);
                $table->unique(['squad_battle_id', 'player_name', 'player_team'], 'sbp_battle_player_team_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('squad_battle_picks');
    }
};
