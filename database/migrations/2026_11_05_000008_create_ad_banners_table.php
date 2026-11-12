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
        Schema::create('ad_banners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('placement', ['header', 'pinned_match_below', 'match_detail_odds', 'sidebar', 'sticky_footer']);
            $table->enum('type', ['image_link', 'html_script', 'telegram_cta']);
            $table->string('image_url')->nullable();
            $table->string('target_url')->nullable();
            $table->text('script_code')->nullable();
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_banners');
    }
};

