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
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('api_series_id')->unique()->index();
            $table->string('name');
            $table->string('slug')->unique()->index();
            $table->enum('type', ['international', 'league', 'domestic', 'women']);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_popular')->default(false);
            $table->enum('status', ['upcoming', 'live', 'completed']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};

