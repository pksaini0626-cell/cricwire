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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'captain_username')) {
                $table->string('captain_username', 30)->nullable()->unique()->after('name');
            }

            if (Schema::hasColumn('users', 'cric_gems')) {
                $table->bigInteger('cric_gems')->default(0)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'captain_username')) {
                $table->dropUnique(['captain_username']);
                $table->dropColumn('captain_username');
            }

            if (Schema::hasColumn('users', 'cric_gems')) {
                $table->unsignedBigInteger('cric_gems')->default(0)->change();
            }
        });
    }
};
