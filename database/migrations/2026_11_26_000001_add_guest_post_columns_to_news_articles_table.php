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
            if (Schema::hasColumn('news_articles', 'status')) {
                $table->string('status', 30)->default('draft')->change();
            } else {
                $table->string('status', 30)->default('draft')->after('source_url');
            }

            if (!Schema::hasColumn('news_articles', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('author_id')
                    ->index()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('news_articles', 'is_guest_post')) {
                $table->boolean('is_guest_post')
                    ->default(false)
                    ->index()
                    ->after('status');
            }

            if (!Schema::hasColumn('news_articles', 'author_name')) {
                $table->string('author_name', 100)
                    ->nullable()
                    ->after('is_guest_post');
            }

            if (!Schema::hasColumn('news_articles', 'author_bio')) {
                $table->string('author_bio', 250)
                    ->nullable()
                    ->after('author_name');
            }

            if (!Schema::hasColumn('news_articles', 'author_social_url')) {
                $table->string('author_social_url', 255)
                    ->nullable()
                    ->after('author_bio');
            }

            if (!Schema::hasColumn('news_articles', 'rejection_reason')) {
                $table->string('rejection_reason', 255)
                    ->nullable()
                    ->after('author_social_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            if (Schema::hasColumn('news_articles', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            }

            foreach (['rejection_reason', 'author_social_url', 'author_bio', 'author_name'] as $column) {
                if (Schema::hasColumn('news_articles', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('news_articles', 'is_guest_post')) {
                $table->dropIndex(['is_guest_post']);
                $table->dropColumn('is_guest_post');
            }
        });
    }
};
