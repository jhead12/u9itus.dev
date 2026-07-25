import { defineConfig } from 'vite';
import { visualizer } from 'rollup-plugin-visualizer';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/map/app.js'],
            refresh: true,
        }),
        visualizer({
            filename: '/tmp/u9itus-stats.html',
            gzipSize: true,
            brotliSize: false,
            open: false,
        }),
    ],
    build: {
        outDir: '/tmp/u9itus-build',
        emptyOutDir: true,
        rollupOptions: {
            output: {
                manualChunks: { three: ['three'] },
            },
        },
    },
});
