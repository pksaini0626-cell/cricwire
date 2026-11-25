<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finished_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('finished_matches', 'score_a')) {
                $table->string('score_a')->nullable()->after('result_text');
            }
            if (!Schema::hasColumn('finished_matches', 'score_b')) {
                $table->string('score_b')->nullable()->after('score_a');
            }
            if (!Schema::hasColumn('finished_matches', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('fetched_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finished_matches', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['score_a', 'score_b', 'archived_at'],
                fn ($c) => Schema::hasColumn('finished_matches', $c)
            ));
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
