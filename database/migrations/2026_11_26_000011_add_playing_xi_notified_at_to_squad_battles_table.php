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
        if (Schema::hasTable('squad_battles') && !Schema::hasColumn('squad_battles', 'playing_xi_notified_at')) {
            Schema::table('squad_battles', function (Blueprint $table) {
                $table->timestamp('playing_xi_notified_at')->nullable()->after('locks_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('squad_battles') && Schema::hasColumn('squad_battles', 'playing_xi_notified_at')) {
            Schema::table('squad_battles', function (Blueprint $table) {
                $table->dropColumn('playing_xi_notified_at');
            });
        }
    }
};
