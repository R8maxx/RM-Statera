import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            /*
             * Sin `fonts: [bunny(...)]`. El plugin descargaba las dos familias y
             * emitía un `fonts-*.css` que NO enlazaba nadie: la página sólo pide
             * `app.css`, así que toda la interfaz se pintaba con la fuente del
             * sistema mientras el build cargaba doce `@font-face` muertos.
             *
             * Ahora los `.woff2` viven en `resources/fonts/` y el `@font-face`
             * está en `app.css`, que sí se enlaza. Y son **los mismos ficheros**
             * que incrusta el PDF (`AssetsDocumento`): un solo origen, sin dos
             * juegos que puedan divergir.
             */
        }),
        vue(),
        // Inertia v3: resuelve las páginas por sí solo. No hay callback `resolve`
        // en el punto de entrada. SSR desactivado — Statera vive tras un login,
        // no hay SEO ni primer pintado crítico que lo justifique.
        inertia({ ssr: false }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': resolve(import.meta.dirname, 'resources/js'),
        },
    },
    server: {
        // Vite corre en su propio contenedor: sin escuchar en todas las
        // interfaces, el puerto publicado no llegaría a ninguna parte.
        host: '0.0.0.0',
        port: 5173,
        // Lo que `laravel-vite-plugin` escribe en `public/hot` y, por tanto, lo
        // que acaba pidiendo el navegador. Sin esto apuntaría a la dirección
        // interna del contenedor, que desde el host no resuelve.
        origin: 'http://localhost:5173',
        watch: {
            // Sin `usePolling`: en Linux los eventos de inotify atraviesan el
            // bind mount, y sondear decenas de miles de ficheros cuesta una CPU
            // a cambio de nada.
            ignored: [
                '**/storage/framework/views/**',
                '**/vendor/**',
                '**/node_modules/**',
            ],
        },
    },
});
