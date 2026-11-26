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
        if (Schema::hasTable('cric_gem_transactions') && Schema::hasColumn('cric_gem_transactions', 'balance_after')) {
            Schema::table('cric_gem_transactions', function (Blueprint $table) {
                $table->bigInteger('balance_after')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cric_gem_transactions') && Schema::hasColumn('cric_gem_transactions', 'balance_after')) {
            Schema::table('cric_gem_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('balance_after')->change();
            });
        }
    }
};
