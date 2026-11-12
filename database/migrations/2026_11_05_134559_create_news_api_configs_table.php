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
        Schema::create('news_api_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('endpoint_url');
            $table->enum('auth_type', ['header', 'bearer', 'query_param', 'none'])->default('header');
            $table->string('api_key_header')->nullable();
            $table->text('api_key_value')->nullable();
            $table->json('additional_headers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_api_configs');
    }
};

