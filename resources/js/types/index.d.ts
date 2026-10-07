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
    /** El tema guardado en la cuenta, que manda sobre el del navegador. */
    tema: App.Domain.Usuario.Enums.Tema;
    /** Administra la plataforma: sin organización y sin rol (punto 41). */
    plataforma: boolean;
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

/** La suscripción, sólo cuando hay algo que avisar (punto 43). */
export interface AvisoSuscripcion {
    estado: App.Domain.Plataforma.Enums.EstadoSuscripcion;
    etiqueta: string;
    plan: string | null;
    venceEn: string | null;
    graciaHasta: string | null;
}

export interface PropsCompartidos {
    auth: {
        usuario: UsuarioAutenticado | null;
        permisos: string[];
    };
    organizacion: OrganizacionActiva | null;
    suscripcion: AvisoSuscripcion | null;
    /** Dónde está como soporte quien administra la plataforma (punto 44). */
    soporte: { organizacion: string; hasta: string | null } | null;
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
