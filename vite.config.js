import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny, google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                google('DM Sans', {
                    weights: [400, 500, 700],
                    preload: [{ weight: 400 }],
                    fallbacks: ['system-ui', 'sans-serif'],
                    optimizedFallbacks: false,
                }),
                google('Newsreader', {
                    weights: [600],
                    fallbacks: ['Georgia', 'serif'],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
