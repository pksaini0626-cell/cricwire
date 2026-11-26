<?php

namespace App\Services\Notifications;

use App\Models\PushNotification;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $query = PushSubscription::query()->active();

        if ($notification->audience === 'logged_in') {
            $query->whereNotNull('user_id');
        }

        $allTokens = $query->pluck('fcm_token')->filter()->unique()->values()->all();
        $totalTargeted = count($allTokens);

        $sentCount = 0;
        $failedCount = 0;
        $invalidTokensList = [];
        $hadBatchException = false;

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
            $targetUrl = $this->resolveAbsoluteUrl($notification->target_url) ?: url('/');
            $iconUrl = asset('icons/icon-192x192.png');
            $imageUrl = $this->resolveImageUrl($notification->image_url);

            $webPushNotification = [
                'title' => (string) $notification->title,
                'body'  => (string) $notification->body,
                'icon'  => $iconUrl,
                'badge' => $iconUrl,
            ];

            if ($imageUrl !== null) {
                $webPushNotification['image'] = $imageUrl;
            }

            $webPushConfig = WebPushConfig::fromArray([
                'notification' => $webPushNotification,
                'fcm_options' => [
                    'link' => $targetUrl,
                ],
            ]);

            $fcmNotification = Notification::create(
                (string) $notification->title,
                (string) $notification->body,
                $imageUrl
            );

            $cloudMessage = CloudMessage::new()
                ->withNotification($fcmNotification)
                ->withWebPushConfig($webPushConfig)
                ->withData([
                    'title'           => (string) $notification->title,
                    'body'            => (string) $notification->body,
                    'icon'            => (string) $iconUrl,
                    'image_url'       => (string) ($imageUrl ?? ''),
                    'url'             => (string) $targetUrl,
                    'type'            => (string) $notification->type,
                    'notification_id' => (string) $notification->id,
                ]);

            $chunks = array_chunk($allTokens, 500);

            foreach ($chunks as $chunk) {
                try {
                    $report = $this->messaging->sendMulticast($cloudMessage, $chunk);

                    $sentCount += $report->successes()->count();
                    $failedCount += $report->failures()->count();

                    foreach ($report->failures()->getItems() as $failure) {
                        $tokenValue = $failure->target()->value();
                        $tokenPreview = substr($tokenValue, 0, 14) . '...';
                        $errorMessage = $failure->error()?->getMessage() ?? 'Unknown FCM error';

                        Log::error("FCM Service: Token delivery failure [{$tokenPreview}]: {$errorMessage}", [
                            'notification_id'    => $notification->id,
                            'is_unknown_token'   => $failure->messageWasSentToUnknownToken(),
                            'is_invalid_token'   => $failure->messageTargetWasInvalid(),
                            'is_invalid_message' => $failure->messageWasInvalid(),
                        ]);
                    }

                    $unknown = $report->unknownTokens();
                    $invalid = $report->invalidTokens();

                    $deactivateTokens = array_unique(array_merge($unknown, $invalid));
                    if (! empty($deactivateTokens)) {
                        $invalidTokensList = array_merge($invalidTokensList, $deactivateTokens);
                        PushSubscription::whereIn('fcm_token', $deactivateTokens)
                            ->update(['is_active' => false]);
                    }
                } catch (\Throwable $e) {
                    $hadBatchException = true;
                    Log::error('FCM Service: Error sending multicast batch: ' . $e->getMessage(), [
                        'notification_id' => $notification->id,
                        'exception'       => get_class($e),
                    ]);
                    $failedCount += count($chunk);
                }
            }
        }

        $finalStatus = ($hadBatchException && $sentCount === 0 && $totalTargeted > 0) ? 'failed' : 'sent';

        $notification->update([
            'total_targeted' => $totalTargeted,
            'sent_count'     => $sentCount,
            'failed_count'   => $failedCount,
            'status'         => $finalStatus,
            'sent_at'        => $finalStatus === 'sent' ? now() : null,
        ]);

        Cache::forget('in_app_notifications:latest');

        return [
            'success'        => $finalStatus === 'sent',
            'total_targeted' => $totalTargeted,
            'sent_count'     => $sentCount,
            'failed_count'   => $failedCount,
            'deactivated'    => count($invalidTokensList),
        ];
    }

    protected function resolveAbsoluteUrl(?string $rawUrl): ?string
    {
        $trimmed = trim((string) $rawUrl);
        if ($trimmed === '') {
            return null;
        }

        if (Str::startsWith($trimmed, ['http://', 'https://'])) {
            return $trimmed;
        }

        return url($trimmed);
    }

    protected function resolveImageUrl(?string $rawImage): ?string
    {
        $trimmed = trim((string) $rawImage);
        if ($trimmed === '') {
            return null;
        }

        if (Str::startsWith($trimmed, ['http://', 'https://'])) {
            return $trimmed;
        }

        return asset('storage/' . ltrim($trimmed, '/'));
    }
}
