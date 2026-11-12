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
            // Drop foreign key constraints if needing modifications, or adjust columns
            if (Schema::hasColumn('news_articles', 'author_id')) {
                $table->dropForeign(['author_id']);
                $table->foreignId('author_id')->nullable()->change()->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('news_articles', 'summary')) {
                $table->text('summary')->nullable()->after('featured_image');
            }

            if (!Schema::hasColumn('news_articles', 'source_name')) {
                $table->string('source_name')->nullable()->after('content');
            }

            if (!Schema::hasColumn('news_articles', 'source_url')) {
                $table->string('source_url')->nullable()->after('source_name');
            }

            if (!Schema::hasColumn('news_articles', 'status')) {
                $table->enum('status', ['draft', 'published'])->default('draft')->after('source_url');
            }
        });

        // Ensure category enum or values match requested options
        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('featured_image')->nullable()->change();
            $table->longText('content')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            if (Schema::hasColumn('news_articles', 'summary')) {
                $table->dropColumn('summary');
            }
            if (Schema::hasColumn('news_articles', 'source_name')) {
                $table->dropColumn('source_name');
            }
            if (Schema::hasColumn('news_articles', 'source_url')) {
                $table->dropColumn('source_url');
            }
            if (Schema::hasColumn('news_articles', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

