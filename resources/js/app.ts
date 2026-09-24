import { createInertiaApp } from '@inertiajs/vue3';

/*
 * Inertia v3. Sin callbacks `resolve` ni `setup`: la resolución de páginas la
 * inyecta el plugin `@inertiajs/vite` en tiempo de compilación, y el montaje de
 * la aplicación Vue lo hace el propio adaptador. Axios no es dependencia; para
 * peticiones que no son navegación se usa `useHttp`.
 */
void createInertiaApp({
    title: (titulo) => (titulo ? `${titulo} · Statera` : 'Statera'),
    /*
     * La barra lee el token y no lo copia: con el valor escrito a mano se
     * quedaba en el teal del tema claro también en oscuro, donde `--primary`
     * sube de luminosidad.
     */
    progress: {
        color: 'var(--primary)',
    },
});
