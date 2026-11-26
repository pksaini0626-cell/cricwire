/**
 * Cricwire FCM Web & Mobile Push Notification Client
 */
window.CricwirePush = {
    messaging: null,
    vapidKey: null,
    swRegistration: null,
    isSubscribed: false,
    initializingPromise: null,

    async init() {
        if (this.initializingPromise) {
            return this.initializingPromise;
        }

        this.initializingPromise = this._doInit();
        try {
            return await this.initializingPromise;
        } finally {
            this.initializingPromise = null;
        }
    },

    async _doInit() {
        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            console.warn('[CricwirePush] Push notifications are not supported in this browser.');
            return false;
        }

        try {
            // Register unified root-scope Service Worker (/sw.js imports /firebase-messaging-sw.js)
            await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            this.swRegistration = await navigator.serviceWorker.ready;
            console.log('[CricwirePush] Unified Service Worker ready:', this.swRegistration.scope);

            // Fetch Firebase web config
            const response = await fetch('/api/firebase/config', { cache: 'no-store' });
            if (!response.ok) {
                throw new Error(`Failed to load /api/firebase/config (HTTP ${response.status})`);
            }
            const config = await response.json();

            if (!config || !config.apiKey || !config.projectId || !config.messagingSenderId || !config.appId) {
                console.error('[CricwirePush] Firebase web config incomplete:', config);
                return false;
            }

            if (!config.vapidKey) {
                console.error('[CricwirePush] Missing Firebase VAPID key (FIREBASE_WEB_VAPID_KEY).');
            }

            this.vapidKey = config.vapidKey || null;

            // Load Firebase v10.12.2 Compat scripts dynamically if not present
            await this.loadScript('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
            await this.loadScript('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

            const firebaseAppConfig = {
                apiKey: config.apiKey,
                authDomain: config.authDomain,
                projectId: config.projectId,
                storageBucket: config.storageBucket,
                messagingSenderId: config.messagingSenderId,
                appId: config.appId,
            };

            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseAppConfig);
            }

            this.messaging = firebase.messaging();

            // Handle in-app foreground message
            if (!this._foregroundListenerBound) {
                this._foregroundListenerBound = true;
                this.messaging.onMessage((payload) => {
                    console.log('[CricwirePush] Foreground message received:', payload);
                    const title = payload.notification?.title || payload.data?.title || 'Cricwire Live Alert';
                    const body = payload.notification?.body || payload.data?.body || '';
                    const icon = payload.notification?.icon || payload.data?.icon || '/icons/icon-192x192.png';

                    if (Notification.permission === 'granted') {
                        if (this.swRegistration && this.swRegistration.showNotification) {
                            this.swRegistration.showNotification(title, {
                                body: body,
                                icon: icon,
                                badge: '/icons/icon-192x192.png',
                                data: payload.data || {},
                            });
                        } else {
                            new Notification(title, {
                                body: body,
                                icon: icon,
                                data: payload.data || {},
                            });
                        }
                    }
                });
            }

            // Check current subscription status
            const currentToken = localStorage.getItem('cricwire_fcm_token');
            this.isSubscribed = Notification.permission === 'granted' && !!currentToken;

            window.dispatchEvent(new CustomEvent('cricwire:push-status-changed', {
                detail: { isSubscribed: this.isSubscribed }
            }));

            return true;
        } catch (e) {
            console.error('[CricwirePush] Initialization error:', e);
            return false;
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
            script.onerror = (e) => reject(new Error(`Failed to load script: ${src}`));
            document.head.appendChild(script);
        });
    },

    async subscribe() {
        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            const err = new Error('Push notifications are not supported on this browser or device.');
            console.error('[CricwirePush]', err.message);
            throw err;
        }

        if (Notification.permission === 'denied') {
            const err = new Error('Notifications are blocked in your browser settings. Please enable site notifications and try again.');
            console.error('[CricwirePush]', err.message);
            throw err;
        }

        if (Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                const err = new Error('Notification permission was not granted.');
                console.error('[CricwirePush]', err.message);
                throw err;
            }
        }

        if (!this.messaging || !this.swRegistration) {
            await this.init();
        }

        if (!this.messaging) {
            const err = new Error('Firebase Messaging failed to initialize. Check console and Firebase web config.');
            console.error('[CricwirePush]', err.message);
            throw err;
        }

        if (!this.vapidKey) {
            const err = new Error('Firebase VAPID key is missing. Please configure FIREBASE_WEB_VAPID_KEY.');
            console.error('[CricwirePush]', err.message);
            throw err;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            this.swRegistration = registration;

            const token = await this.messaging.getToken({
                vapidKey: this.vapidKey,
                serviceWorkerRegistration: registration,
            });

            if (!token) {
                throw new Error('FCM getToken() returned an empty token.');
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/api/push/subscribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    fcm_token: token,
                    platform: 'web',
                    device_info: navigator.userAgent
                })
            });

            let data = null;
            try {
                data = await res.json();
            } catch (_) {
                data = null;
            }

            if (!res.ok || !data || !data.success) {
                throw new Error(data?.message || `Push subscription API failed with HTTP ${res.status}`);
            }

            localStorage.setItem('cricwire_fcm_token', token);
            this.isSubscribed = true;
            window.dispatchEvent(new CustomEvent('cricwire:push-status-changed', {
                detail: { isSubscribed: true }
            }));

            return true;
        } catch (err) {
            console.error('[CricwirePush] Subscribe failed:', err);
            throw err;
        }
    },

    async unsubscribe() {
        const token = localStorage.getItem('cricwire_fcm_token');
        if (!token) {
            this.isSubscribed = false;
            window.dispatchEvent(new CustomEvent('cricwire:push-status-changed', {
                detail: { isSubscribed: false }
            }));
            return true;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/api/push/unsubscribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ fcm_token: token })
            });

            if (!res.ok) {
                console.warn('[CricwirePush] Unsubscribe API returned HTTP', res.status);
            }

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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.CricwirePush.init());
} else {
    window.CricwirePush.init();
}

document.addEventListener('livewire:navigated', () => {
    const isSub = ('Notification' in window) && Notification.permission === 'granted' && !!localStorage.getItem('cricwire_fcm_token');
    window.CricwirePush.isSubscribed = isSub;
    window.dispatchEvent(new CustomEvent('cricwire:push-status-changed', {
        detail: { isSubscribed: isSub }
    }));
});
