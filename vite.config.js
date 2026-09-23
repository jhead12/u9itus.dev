import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/map/app.js', 'resources/js/blog/editor.js', 'resources/js/earn-cta/app.js', 'resources/js/portal-builder/app.jsx'],
            refresh: true,
        }),
        // Scoped to resources/js/portal-builder — the only entry with JSX.
        // Everything else in this app is Blade + Alpine + plain JS.
        react({ include: 'resources/js/portal-builder/**' }),
    ],
    build: {
        rollupOptions: {
            output: {
                // Split Three.js into its own vendor chunk so the map bundle
                // stays cacheable and under the single-chunk size warning.
                manualChunks: { three: ['three'] },
            },
        },
    },
});
