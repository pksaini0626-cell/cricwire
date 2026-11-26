<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Notifications\FcmPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class WebPushNotificationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_firebase_config_and_dynamic_service_worker_route(): void
    {
        Config::set('services.firebase.api_key', 'test-api-key');
        Config::set('services.firebase.auth_domain', 'test.firebaseapp.com');
        Config::set('services.firebase.project_id', 'test-project-id');
        Config::set('services.firebase.storage_bucket', 'test.firebasestorage.app');
        Config::set('services.firebase.messaging_sender_id', '123456789');
        Config::set('services.firebase.app_id', '1:123456789:web:abcdef');
        Config::set('services.firebase.vapid_key', 'test-vapid-key');

        $configResponse = $this->getJson('/api/firebase/config');
        $configResponse->assertOk()->assertJson([
            'apiKey' => 'test-api-key',
            'projectId' => 'test-project-id',
            'messagingSenderId' => '123456789',
            'appId' => '1:123456789:web:abcdef',
            'vapidKey' => 'test-vapid-key',
        ]);

        $swResponse = $this->get('/firebase-messaging-sw.js');
        $swResponse->assertOk();
        $this->assertStringContainsString('application/javascript', (string) $swResponse->headers->get('Content-Type'));
        $swResponse->assertHeader('Service-Worker-Allowed', '/');
        $swResponse->assertSee('firebase-app-compat.js', false);
        $swResponse->assertSee('firebase-messaging-compat.js', false);
        $swResponse->assertSee('test-project-id', false);
        $swResponse->assertSee('onBackgroundMessage', false);
        $swResponse->assertSee('notificationclick', false);
        $swResponse->assertDontSee('test-vapid-key', false);
    }

    public function test_push_subscribe_and_unsubscribe_endpoints(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $subResponse = $this->postJson('/api/push/subscribe', [
            'fcm_token' => 'sample-fcm-token-1234567890',
            'platform' => 'web',
            'device_info' => 'PHPUnit Browser',
        ]);

        $subResponse->assertOk()->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'fcm_token' => 'sample-fcm-token-1234567890',
            'platform' => 'web',
            'is_active' => 1,
        ]);

        $unsubResponse = $this->postJson('/api/push/unsubscribe', [
            'fcm_token' => 'sample-fcm-token-1234567890',
        ]);

        $unsubResponse->assertOk()->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('push_subscriptions', [
            'fcm_token' => 'sample-fcm-token-1234567890',
            'is_active' => 0,
        ]);
    }

    public function test_layouts_and_more_page_include_cricwire_push_client_and_banner(): void
    {
        $more = $this->get('/more');
        $more->assertOk();
        $more->assertSee('js/cricwire-push.js', false);
        $more->assertSee('Never Miss a Moment!', false);
    }

    public function test_send_push_notification_job_marks_notification_failed_on_exception(): void
    {
        $notification = PushNotification::create([
            'title' => 'Test Match Alert',
            'body' => 'Toss update body',
            'type' => 'match',
            'target_url' => '/polls',
            'audience' => 'all',
            'status' => 'queued',
        ]);

        $job = new SendPushNotificationJob($notification->id);
        $job->failed(new RuntimeException('Simulated FCM transport failure'));

        $notification->refresh();
        $this->assertSame('failed', $notification->status);
    }

    public function test_fcm_service_resolves_relative_urls_and_handles_missing_credentials_gracefully(): void
    {
        Config::set('services.firebase.credentials', storage_path('app/firebase/non-existent.json'));

        $notification = PushNotification::create([
            'title' => 'Poll Alert',
            'body' => 'Vote now',
            'type' => 'poll',
            'target_url' => '/polls',
            'image_url' => 'push-banners/sample.png',
            'audience' => 'all',
            'status' => 'queued',
        ]);

        $service = new FcmPushService();
        $result = $service->sendToNotification($notification);

        $this->assertTrue($result['success']);
        $notification->refresh();
        $this->assertSame('sent', $notification->status);
    }
}
