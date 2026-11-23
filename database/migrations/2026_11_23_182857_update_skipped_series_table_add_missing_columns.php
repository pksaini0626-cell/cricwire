<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('skipped_series')) {
            Schema::create('skipped_series', function (Blueprint $table) {
                $table->id();
                $table->string('series_id')->nullable()->index();
                $table->string('series_name')->unique()->index();
                $table->string('series_slug')->unique()->index();
                $table->string('format')->nullable();
                $table->unsignedInteger('live_matches_count')->default(0);
                $table->unsignedInteger('upcoming_matches_count')->default(0);
                $table->unsignedInteger('recent_matches_count')->default(0);
                $table->boolean('is_skipped')->default(false);
                $table->timestamps();
            });
            return;
        }

        // Table exists, add missing columns safely
        Schema::table('skipped_series', function (Blueprint $table) {
            if (!Schema::hasColumn('skipped_series', 'series_id')) {
                $table->string('series_id')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('skipped_series', 'series_slug')) {
                $table->string('series_slug')->nullable()->after('series_name');
            }
            if (!Schema::hasColumn('skipped_series', 'format')) {
                $table->string('format')->nullable()->after('series_slug');
            }
            if (!Schema::hasColumn('skipped_series', 'live_matches_count')) {
                $table->unsignedInteger('live_matches_count')->default(0)->after('format');
            }
            if (!Schema::hasColumn('skipped_series', 'upcoming_matches_count')) {
                $table->unsignedInteger('upcoming_matches_count')->default(0)->after('live_matches_count');
            }
            if (!Schema::hasColumn('skipped_series', 'recent_matches_count')) {
                $table->unsignedInteger('recent_matches_count')->default(0)->after('upcoming_matches_count');
            }
            if (!Schema::hasColumn('skipped_series', 'is_skipped')) {
                $table->boolean('is_skipped')->default(false)->after('recent_matches_count');
            }
        });

        // Backfill series_slug for existing rows before making it unique
        $rows = DB::table('skipped_series')->get();
        foreach ($rows as $row) {
            $slug = !empty($row->series_slug) ? $row->series_slug : Str::slug($row->series_name);
            DB::table('skipped_series')->where('id', $row->id)->update(['series_slug' => $slug]);
        }

        // Now ensure unique index on series_slug
        Schema::table('skipped_series', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('skipped_series'))->pluck('name')->all();
            if (!in_array('skipped_series_series_slug_unique', $indexes, true)) {
                $table->unique('series_slug');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('skipped_series')) {
            Schema::table('skipped_series', function (Blueprint $table) {
                if (Schema::hasColumn('skipped_series', 'series_slug')) {
                    $table->dropUnique(['series_slug']);
                    $table->dropColumn('series_slug');
                }
                if (Schema::hasColumn('skipped_series', 'series_id')) {
                    $table->dropColumn('series_id');
                }
                if (Schema::hasColumn('skipped_series', 'recent_matches_count')) {
                    $table->dropColumn('recent_matches_count');
                }
            });
        }
    }
};
