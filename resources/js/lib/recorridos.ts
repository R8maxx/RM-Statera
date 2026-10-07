import { recorridosCiclo } from '@/lib/recorridos/ciclo';
import { recorridosMedida } from '@/lib/recorridos/medida';
import { recorridosNucleo } from '@/lib/recorridos/nucleo';
import { recorridosOrganizacion } from '@/lib/recorridos/organizacion';
import { recorridosPlan } from '@/lib/recorridos/plan';

/**
 * Los recorridos guiados, declarados una sola vez.
 *
 * Mismo criterio que `navegacion.ts`: el mapa vive en un sitio y lo leen el
 * overlay, la cabecera que lo relanza, el menú de usuario y los tests. Repartir
 * los pasos por los componentes que señalan termina con un paso huérfano
 * apuntando a un elemento que ya no existe.
 *
 * **Quien llega no conoce ni la herramienta ni los marcos** (PRODUCT.md), así
 * que cada paso explica una cosa del dominio además de dónde se pulsa. Hay dos
 * clases:
 *
 * - **El general**, que arranca en el panel. Persigue una cosa —que una
 *   evidencia registrada una vez cuenta en los dos marcos— y, por el camino,
 *   enseña el mapa: para qué sirve cada grupo del lateral.
 * - **Uno por pantalla**, que arranca la primera vez que se entra en ella y se
 *   relanza con el «?» de su cabecera. Tres o cuatro pasos: qué es y qué parte
 *   de la norma cubre, la idea del dominio que no es obvia y dónde se hace lo
 *   principal. Viven en `recorridos/`, uno por bloque del lateral.
 *
 * Lo que no hacen es dar un curso. `tests/Feature/Diseno/RecorridosTest.php`
 * pone en rojo un ancla que no existe en ninguna parte y un módulo del lateral
 * que llega sin su recorrido.
 */

/** Una posición del panel respecto al elemento señalado. */
export type LadoRecorrido = 'arriba' | 'abajo' | 'izquierda' | 'derecha';

export type PasoRecorrido = {
    clave: string;
    titulo: string;
    /** Dos frases como mucho. La tercera ya no se lee. */
    cuerpo: string;
    /**
     * Anclas candidatas, en orden de preferencia: gana la primera que exista en
     * el DOM. Un paso sin ancla viva se pinta centrado en vez de desaparecer —
     * el mismo recorrido tiene que servir en una organización vacía y en una con
     * seis meses de trabajo dentro, y los elementos de la primera no están en la
     * segunda.
     */
    anclas: string[];
    lado: LadoRecorrido;
    /** Se salta para quien no tenga este permiso. */
    permiso?: string;
};

/**
 * El ancla de un grupo del lateral: `Organización` → `grupo-organizacion`.
 * Derivada, como la de cada entrada, para que un grupo nuevo no tenga que
 * acordarse de declararla.
 */
export function anclaGrupo(titulo: string): string {
    return `grupo-${titulo
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')}`;
}

const recorridoPanel: PasoRecorrido[] = [
    {
        clave: 'que-es',
        titulo: 'Dos marcos, un solo registro',
        cuerpo:
            'Statera lleva a la vez la ISO/IEC 27001:2022 y el Esquema Nacional de Seguridad. Cada prueba, cada tarea y cada documento se apunta una vez y cuenta en todos los marcos donde valga, que es justo lo que obliga a duplicar trabajo cuando esto se lleva en hojas de cálculo.',
        anclas: ['logotipo'],
        lado: 'derecha',
    },
    {
        clave: 'sistema',
        titulo: 'Primero, qué entra',
        cuerpo:
            'Un sistema es el trozo de la organización que se somete a los marcos: una sede, una plataforma, un servicio. Delimitarlo es el primer paso porque todo lo que viene después se mide contra él.',
        anclas: ['paso-sistema', 'tarjeta-sistemas', 'nav-sistemas'],
        lado: 'arriba',
    },
    {
        clave: 'derivacion',
        titulo: 'La herramienta decide qué se te exige',
        cuerpo:
            'Se valora el perjuicio en cinco dimensiones —confidencialidad, integridad, trazabilidad, autenticidad y disponibilidad— y de ahí sale la categoría del sistema y el conjunto exacto de medidas exigibles. Nadie marca controles a mano: es un cálculo con una respuesta correcta en el BOE.',
        anclas: ['paso-valoracion', 'anillo-progreso', 'nav-sistemas'],
        lado: 'arriba',
    },
    {
        clave: 'implantaciones',
        titulo: 'Y eso es la lista de trabajo',
        cuerpo:
            'Cada medida exigible se convierte en una implantación con su estado y su histórico. El auditor no pregunta si algo está implantado, pregunta desde cuándo, así que toda transición queda registrada con fecha y autor.',
        anclas: ['nav-implantaciones'],
        lado: 'derecha',
    },
    {
        clave: 'mapeo',
        titulo: 'Aquí es donde se nota',
        cuerpo:
            'Una captura, un acta o una política se registra una vez y se vincula a todo lo que prueba: el mismo documento puede sostener un control de la ISO y tres medidas del ENS a la vez. Eso es lo que deja de mantenerse por duplicado.',
        anclas: ['paso-evidencia', 'tarjeta-pruebas', 'nav-evidencias'],
        lado: 'arriba',
    },
    {
        clave: 'grupo-estado',
        titulo: 'Estado: dónde estás hoy',
        cuerpo:
            'El panel, las implantaciones, las evidencias y los documentos que salen de ellas. Aquí vive también la conformidad con el ENS: la autoevaluación, la Declaración firmada y el distintivo.',
        anclas: [anclaGrupo('Estado')],
        lado: 'derecha',
    },
    {
        clave: 'grupo-plan',
        titulo: 'Plan: qué toca hacer y cuándo',
        cuerpo:
            'El análisis de riesgos decide qué se trata, y el tratamiento se convierte en tareas con responsable y plazo. El calendario junta todo lo que vence, también lo que la norma obliga a repetir cada año.',
        anclas: [anclaGrupo('Plan')],
        lado: 'derecha',
    },
    {
        clave: 'grupo-ciclo',
        titulo: 'Ciclo: lo que pasa y cómo se responde',
        cuerpo:
            'Auditorías, no conformidades con su acción correctiva, mejoras, incidentes, vulnerabilidades y continuidad. Es el SGSI funcionando, no sólo documentado.',
        anclas: [anclaGrupo('Ciclo')],
        lado: 'derecha',
    },
    {
        clave: 'grupo-medida',
        titulo: 'Medida: si está funcionando',
        cuerpo:
            'Indicadores que se sellan solos cada periodo, objetivos con su meta, los cambios planificados del SGSI y la revisión por la dirección, que recoge todo lo anterior en un acta.',
        anclas: [anclaGrupo('Medida')],
        lado: 'derecha',
    },
    {
        clave: 'grupo-alcance',
        titulo: 'Alcance: sobre qué se aplica',
        cuerpo:
            'El contexto de la organización, sus partes interesadas y qué se les comunica; los sistemas y el inventario de activos que los sostienen. Es la base contra la que se mide todo lo demás.',
        anclas: [anclaGrupo('Alcance')],
        lado: 'derecha',
    },
    {
        clave: 'grupo-organizacion',
        titulo: 'Organización: quién lo hace',
        cuerpo:
            'Personas, puestos con sus responsabilidades, formación y proveedores. Asignar bien los roles es un requisito de los dos marcos, no un organigrama decorativo.',
        anclas: [anclaGrupo('Organización')],
        lado: 'derecha',
    },
    {
        clave: 'cada-pantalla',
        titulo: 'Cada pantalla tiene el suyo',
        cuerpo:
            'Al entrar por primera vez en una pantalla verás un recorrido corto sobre ella, y el botón «?» de su cabecera lo repite. Este general lo tienes siempre en tu menú, arriba a la derecha.',
        anclas: ['menu-cuenta'],
        lado: 'abajo',
    },
];

const mapa = {
    panel: recorridoPanel,
    ...recorridosNucleo,
    ...recorridosPlan,
    ...recorridosCiclo,
    ...recorridosMedida,
    ...recorridosOrganizacion,
} satisfies Record<string, PasoRecorrido[]>;

export type ClaveRecorrido = keyof typeof mapa;

/*
 * Reanotado después del `satisfies`: un recorrido en el que ningún paso lleva
 * `permiso` se infiere sin esa propiedad, y leerla daría un error de tipos.
 */
export const recorridos: Record<ClaveRecorrido, PasoRecorrido[]> = mapa;

/** La clave del navegador donde se recuerda qué recorridos ya se vieron. */
export const CLAVE_RECORRIDOS_VISTOS = 'statera.recorridos.vistos';

/** Donde se guardaba cuando sólo existía el del panel. Se lee para migrar. */
export const CLAVE_RECORRIDO_PANEL_ANTIGUA = 'statera.recorrido.panel.visto';
