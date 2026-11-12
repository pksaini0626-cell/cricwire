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
        Schema::table('ad_banners', function (Blueprint $table) {
            // Change placement to string to support any placement key (e.g., news_top, news_in_feed)
            $table->string('placement')->change();

            if (!Schema::hasColumn('ad_banners', 'image_path')) {
                $table->string('image_path')->nullable()->after('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_banners', function (Blueprint $table) {
            if (Schema::hasColumn('ad_banners', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};

