/**
 * Cricwire FCM Web & Mobile Push Notification Client
 */
window.CricwirePush = {
    messaging: null,
    vapidKey: null,
    isSubscribed: false,

    async init() {
        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            console.log('[CricwirePush] Push notifications not supported in this environment.');
            return;
        }

        try {
            // Register Firebase Messaging Service Worker
            const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            console.log('[CricwirePush] Service Worker registered:', registration.scope);

            // Fetch Firebase web config
            const response = await fetch('/api/firebase/config');
            const config = await response.json();

            if (!config || !config.apiKey || !config.projectId) {
                console.warn('[CricwirePush] Firebase web config incomplete.');
                return;
            }

            this.vapidKey = config.vapidKey;

            // Load Firebase Compat scripts dynamically if not present
            await this.loadScript('https://www.gstatic.com/firebasejs/10.13.0/firebase-app-compat.js');
            await this.loadScript('https://www.gstatic.com/firebasejs/10.13.0/firebase-messaging-compat.js');

            if (!firebase.apps.length) {
                firebase.initializeApp(config);
            }

            this.messaging = firebase.messaging();

            // Handle in-app foreground message
            this.messaging.onMessage((payload) => {
                console.log('[CricwirePush] Foreground message received:', payload);
                const title = payload.notification?.title || payload.data?.title || 'Cricwire Live Alert';
                const body = payload.notification?.body || payload.data?.body || '';

                if (Notification.permission === 'granted') {
                    new Notification(title, {
                        body: body,
                        icon: payload.notification?.icon || '/icons/icon-192x192.png',
                        data: payload.data || {},
                    });
                }
            });

            // Check current subscription status
            const currentToken = localStorage.getItem('cricwire_fcm_token');
            this.isSubscribed = Notification.permission === 'granted' && !!currentToken;

            window.dispatchEvent(new CustomEvent('cricwire:push-status-changed', {
                detail: { isSubscribed: this.isSubscribed }
            }));
        } catch (e) {
            console.error('[CricwirePush] Initialization error:', e);
        }
    },

    loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${src}"]`)) {
                return resolve();
            }
            const script = document.createElement('script');
            script.src = src;
            script.onload = () => resolve();
            script.onerror = (e) => reject(e);
            document.head.appendChild(script);
        });
    },

    async subscribe() {
        if (!this.messaging) {
            await this.init();
        }

        if (!this.messaging) {
            throw new Error('Push messaging could not be initialized.');
        }

        try {
            const token = await this.messaging.getToken({
                vapidKey: this.vapidKey || undefined
            });

            if (!token) {
                throw new Error('No FCM registration token received.');
            }

            // Save to backend
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    fcm_token: token,
                    platform: 'web',
                    device_info: navigator.userAgent
                })
            });

            const data = await res.json();
            if (data.success) {
                localStorage.setItem('cricwire_fcm_token', token);
                this.isSubscribed = true;
                window.dispatchEvent(new CustomEvent('cricwire:push-status-changed', {
                    detail: { isSubscribed: true }
                }));
                return true;
            }
        } catch (err) {
            console.error('[CricwirePush] Subscribe failed:', err);
            throw err;
        }
    },

    async unsubscribe() {
        const token = localStorage.getItem('cricwire_fcm_token');
        if (!token) {
            this.isSubscribed = false;
            return true;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            await fetch('/api/push/unsubscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ fcm_token: token })
            });

            if (this.messaging) {
                await this.messaging.deleteToken();
            }

            localStorage.removeItem('cricwire_fcm_token');
            this.isSubscribed = false;
            window.dispatchEvent(new CustomEvent('cricwire:push-status-changed', {
                detail: { isSubscribed: false }
            }));
            return true;
        } catch (err) {
            console.error('[CricwirePush] Unsubscribe error:', err);
            throw err;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.CricwirePush.init();
});
