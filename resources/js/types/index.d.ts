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
    dosFactores: boolean;
}

export interface OrganizacionActiva {
    id: number;
    nombre: string;
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
