import { createInertiaApp } from '@inertiajs/vue3';

/*
 * Inertia v3. Sin callbacks `resolve` ni `setup`: la resolución de páginas la
 * inyecta el plugin `@inertiajs/vite` en tiempo de compilación, y el montaje de
 * la aplicación Vue lo hace el propio adaptador. Axios no es dependencia; para
 * peticiones que no son navegación se usa `useHttp`.
 */
void createInertiaApp({
    title: (titulo) => (titulo ? `${titulo} · Statera` : 'Statera'),
    progress: {
        color: 'oklch(0.45 0.09 233)',
    },
});
