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
        Schema::table('news_articles', function (Blueprint $table) {
            if (!Schema::hasColumn('news_articles', 'series_slug')) {
                $table->string('series_slug')->nullable()->index()->after('match_id');
            }
            if (!Schema::hasColumn('news_articles', 'series_name')) {
                $table->string('series_name')->nullable()->after('series_slug');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            if (Schema::hasColumn('news_articles', 'series_slug')) {
                $table->dropIndex(['series_slug']);
                $table->dropColumn('series_slug');
            }
            if (Schema::hasColumn('news_articles', 'series_name')) {
                $table->dropColumn('series_name');
            }
        });
    }
};
