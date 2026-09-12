import {
    BoxesIcon,
    ClipboardCheckIcon,
    ClipboardListIcon,
    FileTextIcon,
    LayoutDashboardIcon,
    LayoutTemplateIcon,
    ListTodoIcon,
    PaperclipIcon,
    ServerIcon,
    type LucideIcon,
} from '@lucide/vue';

/**
 * El mapa de la aplicación, declarado una sola vez.
 *
 * Lo leen el sidebar, el panel lateral de móvil, las migas de pan y la paleta
 * de comandos. Un módulo nuevo se añade aquí y aparece en los cuatro sitios:
 * mantener cuatro listas sincronizadas a mano es lo que termina dejando un
 * módulo fuera del buscador sin que nadie lo note.
 *
 * Los grupos existen desde ya, con tres entradas, porque la especificación
 * define diecinueve módulos y una lista plana de diecinueve no se recorre.
 */
export interface EntradaNavegacion {
    titulo: string;
    href: string;
    icono: LucideIcon;
    /** Sinónimos para la paleta: lo que alguien teclea sin saber cómo se llama. */
    alias?: string[];
}

export interface GrupoNavegacion {
    titulo: string;
    entradas: EntradaNavegacion[];
}

export const navegacion: GrupoNavegacion[] = [
    {
        titulo: 'Cumplimiento',
        entradas: [
            {
                titulo: 'Panel',
                href: '/panel',
                icono: LayoutDashboardIcon,
                alias: ['inicio', 'resumen', 'dashboard'],
            },
            {
                titulo: 'Implantaciones',
                href: '/implantaciones',
                icono: ClipboardCheckIcon,
                alias: ['controles', 'requisitos', 'medidas', 'anexo'],
            },
            {
                titulo: 'Evidencias',
                href: '/evidencias',
                icono: PaperclipIcon,
                alias: ['pruebas', 'adjuntos', 'capturas', 'soporte'],
            },
            {
                titulo: 'Tareas',
                href: '/tareas',
                icono: ListTodoIcon,
                alias: ['plan de acción', 'plan', 'pendientes', 'acciones', 'kanban', 'to-do', 'deberes'],
            },
            {
                titulo: 'Documentos',
                href: '/documentos',
                icono: FileTextIcon,
                // `soa` y `dda` no son opcionales: es lo que la gente teclea en
                // la paleta cuando busca la Declaración de Aplicabilidad.
                alias: ['soa', 'dda', 'declaración de aplicabilidad', 'pdf', 'informes', 'documentación', 'entregables'],
            },
        ],
    },
    {
        titulo: 'Alcance',
        entradas: [
            {
                titulo: 'Sistemas',
                href: '/sistemas',
                icono: ServerIcon,
                alias: ['alcance', 'ens', 'categoria'],
            },
            {
                titulo: 'Activos',
                href: '/activos',
                icono: BoxesIcon,
                alias: ['inventario', 'magerit', 'servidores', 'datos', 'servicios', 'dependencias', 'qr', 'etiquetas'],
            },
            {
                titulo: 'Revisiones',
                href: '/revisiones',
                icono: ClipboardListIcon,
                alias: ['revisión', 'inventario', 'mantenido', 'desviaciones'],
            },
        ],
    },
    {
        /*
         * Grupo nuevo, con una sola entrada de momento. Se irá llenando con
         * personas (§ 4.8) y proveedores (§ 4.9), que son de la organización y
         * no del cumplimiento ni del alcance.
         */
        titulo: 'Organización',
        entradas: [
            {
                titulo: 'Plantillas de documento',
                href: '/plantillas-documento',
                icono: LayoutTemplateIcon,
                alias: ['textos', 'plantilla', 'narrativa', 'introducción', 'metodología', 'modelo', 'base'],
            },
        ],
    },
];

/** Todas las entradas en plano, para buscar y para resolver la ruta activa. */
export const entradas: EntradaNavegacion[] = navegacion.flatMap((grupo) => grupo.entradas);

/** Una sección está activa si la ruta es la suya o cuelga de ella. */
export function esSeccionActiva(href: string, ruta: string): boolean {
    return ruta === href || ruta.startsWith(`${href}/`);
}

/** La entrada a la que pertenece una ruta, para las migas de pan. */
export function entradaDe(ruta: string): EntradaNavegacion | undefined {
    return entradas.find((entrada) => esSeccionActiva(entrada.href, ruta));
}
