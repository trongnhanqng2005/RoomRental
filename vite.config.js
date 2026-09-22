import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700],
                    optimizedFallbacks: false,
                }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
