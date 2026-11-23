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
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('fcm_token', 512)->unique();
            $table->enum('platform', ['web', 'android', 'ios'])->default('web');
            $table->string('device_info')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('image_url')->nullable();
            $table->enum('type', ['general', 'match', 'poll', 'news'])->default('general');
            $table->string('target_url')->nullable();
            $table->enum('audience', ['all', 'logged_in'])->default('all');
            $table->enum('status', ['draft', 'queued', 'sent', 'failed'])->default('draft');
            $table->unsignedInteger('total_targeted')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
        Schema::dropIfExists('push_subscriptions');
    }
};
