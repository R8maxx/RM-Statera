import {
    BoxesIcon,
    ClipboardCheckIcon,
    ClipboardListIcon,
    ClipboardXIcon,
    CompassIcon,
    FileTextIcon,
    GaugeIcon,
    LayoutDashboardIcon,
    LayoutTemplateIcon,
    ListTodoIcon,
    PaperclipIcon,
    SearchCheckIcon,
    ServerIcon,
    ShieldAlertIcon,
    UsersIcon,
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
                titulo: 'Riesgos',
                href: '/riesgos',
                icono: ShieldAlertIcon,
                // `magerit` y `amenazas` son lo que se teclea sin saber que el
                // módulo se llama «Riesgos»; `matriz` y `residual` son lo que se
                // busca cuando ya se está dentro del análisis.
                alias: ['análisis de riesgos', 'amenazas', 'magerit', 'matriz', 'residual', 'salvaguardas', 'tratamiento', 'metodología'],
            },
            {
                titulo: 'Tareas',
                href: '/tareas',
                icono: ListTodoIcon,
                alias: ['plan de acción', 'plan', 'pendientes', 'acciones', 'kanban', 'to-do', 'deberes'],
            },
            {
                titulo: 'Auditorías',
                href: '/auditorias',
                icono: SearchCheckIcon,
                /*
                 * Sin el alias «revisión», que ya es de `/revisiones` —las del
                 * inventario— y volverá a hacer falta para la revisión por la
                 * dirección (§ 4.15). Dos entradas que responden a la misma
                 * palabra dejan la paleta de comandos sin poder desempatar.
                 */
                /*
                 * Y sin «no conformidades», que era alias suyo mientras el
                 * § 4.13 no existía y ahora **es el título de otra entrada**.
                 * Dejarlo aquí sería el mismo empate que se evitó con
                 * «revisión»: quien teclea eso quiere el registro, no la
                 * auditoría de la que salió.
                 */
                alias: ['auditoría interna', 'hallazgos', 'autoevaluación', '9.2', 'checklist'],
            },
            {
                titulo: 'No conformidades',
                href: '/no-conformidades',
                icono: ClipboardXIcon,
                // `nc` y `10.2` son lo que se teclea sabiendo de qué va; «causa
                // raíz» y «eficacia» son lo que se busca estando ya dentro.
                alias: ['nc', 'acciones correctivas', 'causa raíz', 'eficacia', '10.2', 'mejora continua'],
            },
            {
                titulo: 'Indicadores',
                href: '/indicadores',
                icono: GaugeIcon,
                /*
                 * `9.1` y `kpi` son lo que se teclea sabiendo de qué va;
                 * «métricas» es el título del § 4.14 en la especificación y
                 * «cuadro de mando» es como lo llama la dirección.
                 *
                 * Sin el alias «objetivos»: la cláusula 6.2 tendrá su propio
                 * registro y un indicador no es un objetivo —uno mide y el otro
                 * compromete—. Dejarlo aquí sería el empate que ya se evitó con
                 * «revisión» y con «no conformidades».
                 */
                alias: ['métricas', 'kpi', 'mediciones', 'cuadro de mando', 'seguimiento', '9.1'],
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
            /*
             * Delante de Sistemas porque es lo que va delante en la norma: el
             * contexto (4.1) y las partes interesadas (4.2) son lo que determina
             * el alcance (4.3), que es lo que declara un sistema.
             */
            {
                titulo: 'Contexto',
                href: '/contexto',
                icono: CompassIcon,
                // `dafo` y `swot` son lo que se teclea sabiendo qué se busca; los
                // cuatro cuadrantes, lo que se teclea sin acordarse del nombre.
                alias: ['dafo', 'swot', '4.1', 'cuestiones', 'debilidades', 'amenazas', 'fortalezas', 'oportunidades', 'cambio climático'],
            },
            {
                titulo: 'Partes interesadas',
                href: '/partes-interesadas',
                icono: UsersIcon,
                alias: ['4.2', 'requisitos legales', 'reguladores', 'expectativas', 'clientes', 'stakeholders'],
            },
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
