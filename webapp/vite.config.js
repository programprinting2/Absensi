import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const lanHost = process.env.VITE_DEV_SERVER_HOST || '192.168.100.249';

export default defineConfig({
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
});
