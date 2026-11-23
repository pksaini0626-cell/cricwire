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
        Schema::create('completed_match_scorecards', function (Blueprint $table) {
            $table->id();
            $table->string('match_id')->unique();
            $table->string('series_name')->nullable();
            $table->string('match_title')->nullable();
            $table->string('result_text')->nullable();
            $table->string('json_path')->nullable();
            $table->longText('scorecard_payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('completed_match_scorecards');
    }
};
