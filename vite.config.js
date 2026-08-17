import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/map/app.js', 'resources/js/blog/editor.js'],
            refresh: true,
        }),
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
