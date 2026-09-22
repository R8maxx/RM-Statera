/**
 * Props que toda página recibe sin pedirlos, declarados en
 * `App\Http\Middleware\HandleInertiaRequests`.
 *
 * Los tipos del dominio (enums de estado, columnas, filtros, acciones) NO se
 * escriben aquí: se generan desde PHP en `generated.d.ts`.
 */

export interface UsuarioAutenticado {
    id: number;
    nombre: string;
    email: string;
    /**
     * La ruta por la que pedir la foto de perfil, con su sufijo de versión, o
     * nulo si no hay. La construye `User::urlFoto()`; el cliente no la compone.
     */
    foto: string | null;
    dosFactores: boolean;
}

export interface OrganizacionActiva {
    id: number;
    nombre: string;
    /**
     * El logo del cliente, con su sufijo de versión, o nulo si no lo ha subido.
     * Lo compone `Organizacion::urlMarca()`; el cliente no arma la ruta.
     */
    logo: string | null;
}

export interface PropsCompartidos {
    auth: {
        usuario: UsuarioAutenticado | null;
        permisos: string[];
    };
    organizacion: OrganizacionActiva | null;
}

/** Los avisos de una acción, por el canal de flash de Inertia v3. */
export interface DatosFlash {
    exito?: string;
    error?: string;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: PropsCompartidos;
        flashDataType: DatosFlash;
    }
}

export {};
