importScripts('https://www.gstatic.com/firebasejs/10.13.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.0/firebase-messaging-compat.js');

// Fetch credentials configuration dynamically from the backend endpoint
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

let messagingInitialized = false;

async function initFirebaseMessaging() {
    if (messagingInitialized) return;
    try {
        const response = await fetch('/api/firebase/config');
        const config = await response.json();

        if (config && config.apiKey && config.projectId) {
            firebase.initializeApp(config);
            const messaging = firebase.messaging();

            // Background notification handler
            messaging.onBackgroundMessage((payload) => {
                console.log('[firebase-messaging-sw.js] Received background message: ', payload);

                const title = payload.notification?.title || payload.data?.title || 'Cricwire Alert';
                const body = payload.notification?.body || payload.data?.body || '';
                const icon = payload.notification?.icon || payload.data?.icon || '/icons/icon-192x192.png';
                const image = payload.notification?.image || payload.data?.image_url || null;
                const targetUrl = payload.data?.url || payload.fcmOptions?.link || '/';

                const notificationOptions = {
                    body: body,
                    icon: icon,
                    badge: '/icons/icon-192x192.png',
                    image: image,
                    data: {
                        url: targetUrl,
                        notification_id: payload.data?.notification_id || null,
                    },
                    vibrate: [200, 100, 200],
                    requireInteraction: false,
                };

                return self.registration.showNotification(title, notificationOptions);
            });

            messagingInitialized = true;
        }
    } catch (err) {
        console.warn('[firebase-messaging-sw.js] Failed to fetch Firebase config or init messaging:', err);
    }
}

// Immediately attempt initialization
initFirebaseMessaging();

// Notification Click Handler: Focus existing tab or open new one
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
