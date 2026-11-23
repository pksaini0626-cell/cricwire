<?php

namespace App\Services\Notifications;

use App\Models\PushNotification;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\WebPushConfig;

class FcmPushService
{
    protected mixed $messaging = null;

    public function __construct()
    {
        $this->initializeFirebase();
    }

    /**
     * Initialize Firebase Factory using credentials from services config.
     */
    protected function initializeFirebase(): void
    {
        $credentialsPath = config('services.firebase.credentials');

        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            Log::warning('FCM Service: Firebase credentials file missing or not found at ' . ($credentialsPath ?: 'null') . '. In-App notifications will still work, but push delivery is skipped.');
            return;
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
        } catch (\Throwable $e) {
            Log::error('FCM Service: Failed to initialize Firebase messaging: ' . $e->getMessage());
            $this->messaging = null;
        }
    }

    /**
     * Send notification to targeted push subscriptions.
     *
     * @param PushNotification $notification
     * @return array
     */
    public function sendToNotification(PushNotification $notification): array
    {
        // Query active push subscriptions
        $query = PushSubscription::query()->active();

        if ($notification->audience === 'logged_in') {
            $query->whereNotNull('user_id');
        }

        $allTokens = $query->pluck('fcm_token')->filter()->unique()->values()->all();
        $totalTargeted = count($allTokens);

        $sentCount = 0;
        $failedCount = 0;
        $invalidTokensList = [];

        // If messaging is not initialized (e.g. missing credentials file) or no tokens, update and return gracefully
        if (! $this->messaging) {
            $notification->update([
                'total_targeted' => $totalTargeted,
                'sent_count'     => 0,
                'failed_count'   => 0,
                'status'         => 'sent',
                'sent_at'        => now(),
            ]);

            Cache::forget('in_app_notifications:latest');

            return [
                'success'        => true,
                'total_targeted' => $totalTargeted,
                'sent_count'     => 0,
                'failed_count'   => 0,
                'warning'        => 'Firebase credentials missing. Marked sent for In-App Bell.',
            ];
        }

        if ($totalTargeted > 0) {
            $targetUrl = $notification->target_url ?: url('/');
            $iconUrl = asset('icons/icon-192x192.png');

            // Construct WebPushConfig
            $webPushConfig = WebPushConfig::fromArray([
                'notification' => [
                    'icon'  => $iconUrl,
                    'badge' => $iconUrl,
                ],
                'fcm_options' => [
                    'link' => $targetUrl,
                ],
            ]);

            // Construct Notification
            $fcmNotification = Notification::create(
                $notification->title,
                $notification->body,
                $notification->image_url ?: null
            );

            // Construct Base CloudMessage
            $cloudMessage = CloudMessage::new()
                ->withNotification($fcmNotification)
                ->withWebPushConfig($webPushConfig)
                ->withData([
                    'url'             => (string) $targetUrl,
                    'type'            => (string) $notification->type,
                    'notification_id' => (string) $notification->id,
                ]);

            // Process tokens in chunks of 500
            $chunks = array_chunk($allTokens, 500);

            foreach ($chunks as $chunk) {
                try {
                    $report = $this->messaging->sendMulticast($cloudMessage, $chunk);

                    $sentCount += $report->successes()->count();
                    $failedCount += $report->failures()->count();

                    // Collect invalid / unregistered tokens to deactivate
                    $unknown = $report->unknownTokens();
                    $invalid = $report->invalidTokens();

                    $deactivateTokens = array_unique(array_merge($unknown, $invalid));
                    if (! empty($deactivateTokens)) {
                        $invalidTokensList = array_merge($invalidTokensList, $deactivateTokens);
                        PushSubscription::whereIn('fcm_token', $deactivateTokens)
                            ->update(['is_active' => false]);
                    }
                } catch (\Throwable $e) {
                    Log::error('FCM Service: Error sending multicast batch: ' . $e->getMessage());
                    $failedCount += count($chunk);
                }
            }
        }

        $notification->update([
            'total_targeted' => $totalTargeted,
            'sent_count'     => $sentCount,
            'failed_count'   => $failedCount,
            'status'         => 'sent',
            'sent_at'        => now(),
        ]);

        // Flush In-App Bell notifications cache
        Cache::forget('in_app_notifications:latest');

        return [
            'success'        => true,
            'total_targeted' => $totalTargeted,
            'sent_count'     => $sentCount,
            'failed_count'   => $failedCount,
            'deactivated'    => count($invalidTokensList),
        ];
    }
}
