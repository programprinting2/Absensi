import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

/** Host Vite HMR — otomatis dari APP_URL (.env), tanpa hardcode IP di repo. */
function viteDevHost(env) {
    if (env.VITE_DEV_SERVER_HOST) {
        return env.VITE_DEV_SERVER_HOST;
    }
    if (env.APP_URL) {
        try {
            return new URL(env.APP_URL).hostname;
        } catch {
            // APP_URL tidak valid — fallback localhost
        }
    }
    return 'localhost';
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const lanHost = viteDevHost(env);

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ],
        server: {
            host: '0.0.0.0',
            // Port terpisah dari PlayStationBilling (5173) & PrinterCRM (5175)
            port: 5174,
            strictPort: true,
            hmr: {
                host: lanHost,
            },
        },
    };
});
