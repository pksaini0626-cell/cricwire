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
        Schema::create('finished_matches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('match_id')->unique()->index();
            $table->string('series_name')->nullable();
            $table->string('match_title')->nullable();
            $table->string('result_text')->nullable();
            $table->longText('match_json');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finished_matches');
    }
};
