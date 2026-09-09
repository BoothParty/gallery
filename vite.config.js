import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        /*
         * Les galeries sont servies depuis leur propre sous-domaine
         * (ma-soiree.localhost) : sans ces origines autorisées, le navigateur
         * refuse les modules que le serveur de dev livre sur localhost:5173.
         */
        cors: {
            origin: /^https?:\/\/(([a-z0-9-]+\.)*localhost|127\.0\.0\.1|([a-z0-9-]+\.)*[a-z0-9-]+\.test)(:\d+)?$/,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
