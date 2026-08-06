const key = import.meta.env.VITE_REVERB_APP_KEY;

window.Echo = null;

// Only load Echo/Pusher when Reverb is configured — keeps the main bundle lighter and avoids reconnect spam.
if (key) {
    Promise.all([import('laravel-echo'), import('pusher-js')]).then(
        ([{ default: Echo }, { default: Pusher }]) => {
            window.Pusher = Pusher;
            window.Echo = new Echo({
                broadcaster: 'reverb',
                key,
                wsHost: import.meta.env.VITE_REVERB_HOST,
                wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
                wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
            });
        },
    );
}
