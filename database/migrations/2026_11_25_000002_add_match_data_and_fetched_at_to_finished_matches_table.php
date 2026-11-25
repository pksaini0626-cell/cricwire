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
        Schema::table('finished_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('finished_matches', 'match_data')) {
                $table->longText('match_data')->nullable()->after('result_text');
            }
            if (!Schema::hasColumn('finished_matches', 'fetched_at')) {
                $table->timestamp('fetched_at')->nullable()->after('match_data');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finished_matches', function (Blueprint $table) {
            $cols = array_values(array_filter(['match_data', 'fetched_at'], fn ($c) => Schema::hasColumn('finished_matches', $c)));
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
