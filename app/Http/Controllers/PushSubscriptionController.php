<?php

namespace App\Http\Controllers;

use App\Models\PushNotification;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PushSubscriptionController extends Controller
{
    /**
     * Subscribe or refresh FCM token.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token'   => 'required|string|max:512',
            'platform'    => 'nullable|string|in:web,android,ios',
            'device_info' => 'nullable|string|max:255',
        ]);

        $subscription = PushSubscription::updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'user_id'      => auth()->id(),
                'platform'     => $validated['platform'] ?? 'web',
                'device_info'  => $validated['device_info'] ?? $request->userAgent(),
                'is_active'    => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscribed to push notifications successfully.',
            'data'    => [
                'id'       => $subscription->id,
                'platform' => $subscription->platform,
            ],
        ]);
    }

    /**
     * Unsubscribe an FCM token.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:512',
        ]);

        $updated = PushSubscription::where('fcm_token', $validated['fcm_token'])
            ->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => $updated ? 'Unsubscribed successfully.' : 'Token not found.',
        ]);
    }

    /**
     * Return latest 15 sent notifications for In-App Bell drawer.
     */
    public function latest(): JsonResponse
    {
        $notifications = Cache::remember('in_app_notifications:latest', 60, function () {
            return PushNotification::query()
                ->where('status', 'sent')
                ->latest('sent_at')
                ->take(15)
                ->get([
                    'id',
                    'title',
                    'body',
                    'image_url',
                    'type',
                    'target_url',
                    'sent_at',
                    'created_at',
                ]);
        });

        return response()->json([
            'success' => true,
            'data'    => $notifications,
        ]);
    }

    /**
     * Serve Firebase config for service worker or frontend.
     */
    public function firebaseConfig(): JsonResponse
    {
        return response()->json($this->buildFirebaseWebConfig())
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Dynamically serve the Firebase Cloud Messaging Service Worker script
     * with real config('services.firebase') injected synchronously.
     */
    public function serviceWorker(): Response
    {
        $firebaseConfig = $this->buildFirebaseWebConfig();
        unset($firebaseConfig['vapidKey']);

        $encodedConfig = json_encode($firebaseConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $js = <<<JS
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

const cricwireFirebaseConfig = {$encodedConfig};

if (cricwireFirebaseConfig && cricwireFirebaseConfig.apiKey && cricwireFirebaseConfig.projectId) {
    if (!firebase.apps.length) {
        firebase.initializeApp(cricwireFirebaseConfig);
    }

    const messaging = firebase.messaging();

    messaging.onBackgroundMessage((payload) => {
        const title = payload.notification?.title || payload.data?.title || 'Cricwire Alert';
        const body = payload.notification?.body || payload.data?.body || '';
        const icon = payload.notification?.icon || payload.data?.icon || '/icons/icon-192x192.png';
        const image = payload.notification?.image || payload.data?.image_url || undefined;
        const targetUrl = payload.data?.url || payload.fcmOptions?.link || '/';

        const notificationOptions = {
            body: body,
            icon: icon,
            badge: '/icons/icon-192x192.png',
            data: Object.assign({}, payload.data || {}, { url: targetUrl }),
            vibrate: [200, 100, 200],
            requireInteraction: false,
        };

        if (image) {
            notificationOptions.image = image;
        }

        return self.registration.showNotification(title, notificationOptions);
    });
} else {
    console.error('[firebase-messaging-sw.js] Missing Firebase config values from Laravel backend.');
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    if ('navigate' in client) {
                        client.navigate(targetUrl);
                    }
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildFirebaseWebConfig(): array
    {
        return [
            'apiKey'            => (string) config('services.firebase.api_key', ''),
            'authDomain'        => (string) config('services.firebase.auth_domain', ''),
            'projectId'         => (string) config('services.firebase.project_id', ''),
            'storageBucket'     => (string) config('services.firebase.storage_bucket', ''),
            'messagingSenderId' => (string) config('services.firebase.messaging_sender_id', ''),
            'appId'             => (string) config('services.firebase.app_id', ''),
            'vapidKey'          => (string) config('services.firebase.vapid_key', ''),
        ];
    }
}
