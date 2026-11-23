import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = (import.meta.env.VITE_REVERB_APP_KEY || '').trim();
const reverbHost = (import.meta.env.VITE_REVERB_HOST || '').trim();

// Only initialize Laravel Echo when both Reverb key and host are explicitly provided
if (reverbKey !== '' && reverbHost !== '') {
    const reverbPort = import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT, 10) : 443;
    const reverbScheme = (import.meta.env.VITE_REVERB_SCHEME || 'https').toLowerCase();

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else {
    window.Echo = null;
}
