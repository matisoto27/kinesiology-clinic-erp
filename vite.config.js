import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import legacy from '@vitejs/plugin-legacy';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pages/actividades-pacientes/general/crear.js',
                'resources/js/pages/actividades-pacientes/kinesiologia/con-orden/crear.js',
                'resources/js/pages/actividades-pacientes/kinesiologia/sin-orden/crear.js',
                'resources/js/pages/obras-sociales-pacientes/crear.js',
                'resources/js/pages/precios/crear.js'
            ],
            refresh: true,
        }),
        tailwindcss(),
        legacy({
            targets: ['defaults', 'not IE 11', 'chrome 109'],
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            '@compartido': path.resolve(__dirname, './resources/js/compartido'),
        }
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true,
        },
    }
});
