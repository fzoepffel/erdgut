import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // Match CSP dev allowances (127.0.0.1) — default [::1] URLs get blocked.
        host: '127.0.0.1',
        hmr: {
            host: '127.0.0.1',
        },
    },
});
