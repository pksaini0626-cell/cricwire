<?php

namespace App\Http\Controllers;

use App\Models\PushNotification;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        return response()->json([
            'apiKey'            => config('services.firebase.api_key'),
            'authDomain'        => config('services.firebase.auth_domain'),
            'projectId'         => config('services.firebase.project_id'),
            'storageBucket'     => config('services.firebase.storage_bucket'),
            'messagingSenderId' => config('services.firebase.messaging_sender_id'),
            'appId'             => config('services.firebase.app_id'),
            'vapidKey'          => config('services.firebase.vapid_key'),
        ]);
    }
}
