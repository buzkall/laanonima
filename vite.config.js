import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            // The wordmark is listed as an entry so Vite hashes it and
            // `Vite::asset()` can resolve it from the manifest; it is not
            // reachable from the CSS or JS entries.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/images/brand/la-anonima-logo.png',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // The book page is set in the two faces of the design: Gloock
                // for display, Crimson Pro -- italics included -- for reading.
                bunny('Gloock', {
                    weights: [400],
                }),
                bunny('Crimson Pro', {
                    weights: [300, 400, 600, 700],
                    styles: ['normal', 'italic'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
