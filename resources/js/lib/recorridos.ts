/**
 * El recorrido guiado, declarado una sola vez.
 *
 * Mismo criterio que `navegacion.ts`: el mapa vive en un sitio y lo leen el
 * overlay, el menú que lo relanza y los tests. Repartir los pasos por los
 * componentes que señalan termina con un paso huérfano apuntando a un elemento
 * que ya no existe.
 *
 * **Quien llega no conoce ni la herramienta ni los marcos** (PRODUCT.md), así
 * que cada paso explica una cosa del dominio además de dónde se pulsa. Lo que
 * no hace es dar un curso: seis pasos, y el sexto ya está dentro del producto.
 *
 * El recorrido persigue **una** cosa —que se vea que una evidencia registrada
 * una vez cuenta en los dos marcos—, porque es la razón de existir del producto
 * y lo que ninguna hoja de cálculo hace. Todo lo anterior está para llegar ahí:
 * sin un sistema valorado no hay implantaciones, y sin implantaciones no hay
 * nada a lo que vincular una prueba.
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
};

export const recorridoPanel: PasoRecorrido[] = [
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
        clave: 'documentos',
        titulo: 'Y de ahí sale el entregable',
        cuerpo:
            'La Declaración de Aplicabilidad de la ISO y la del ENS son dos consultas sobre lo que acabas de registrar, no dos documentos que se mantengan a mano. Puedes repetir este recorrido cuando quieras desde tu menú, arriba a la derecha.',
        anclas: ['nav-documentos'],
        lado: 'derecha',
    },
];

/** La clave del navegador donde se recuerda que ya se vio. */
export const CLAVE_RECORRIDO_VISTO = 'statera.recorrido.panel.visto';
