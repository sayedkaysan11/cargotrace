import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/dashboard-map.js',
                'resources/js/dashboard-globe.js',
                'resources/js/dashboard-currency.js',
                'resources/js/shipment-estimator.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('/node_modules/three/')) return 'three';
                    if (id.includes('/node_modules/topojson-client/') || id.includes('/node_modules/world-atlas/')) return 'world-data';
                },
            },
        },
    },
});
