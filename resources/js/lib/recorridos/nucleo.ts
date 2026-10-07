import type { PasoRecorrido } from '@/lib/recorridos';

/**
 * El bloque Estado del lateral y su base en Alcance: sistemas, la valoración que
 * los categoriza, implantaciones, evidencias, documentos y conformidad.
 *
 * El botón «Nuevo…» de las pantallas con `DataTable` vive dentro de la barra de
 * la tabla y no lleva ancla propia, así que esos pasos señalan la tabla entera y
 * nombran el botón en el texto.
 */
export const recorridosNucleo = {
    sistemas: [
        {
            clave: 'que-es',
            titulo: 'La unidad de alcance',
            cuerpo: 'Un sistema es lo que se somete a los marcos: un servicio, una plataforma, una sede. El Esquema Nacional de Seguridad se aplica sistema a sistema, y cada uno tiene su propia categoría.',
            anclas: ['nav-sistemas'],
            lado: 'derecha',
        },
        {
            clave: 'categoria',
            titulo: 'La categoría no se elige',
            cuerpo: 'Valoras el perjuicio en las cinco dimensiones del Anexo I y la categoría es la más alta de las cinco; de ella sale lo que se le exige al sistema. La valoración se abre con «Valorar dimensiones», en el menú de cada fila.',
            anclas: ['sistemas-tabla', 'tabla-filtros'],
            lado: 'arriba',
            permiso: 'sistemas.valorar',
        },
        {
            clave: 'nuevo',
            titulo: 'Da de alta un sistema',
            cuerpo: 'Con «Nuevo sistema», arriba a la derecha de la tabla. Nace sin nada exigible hasta que lo valores.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'sistemas-tabla'],
            lado: 'abajo',
            permiso: 'sistemas.gestionar',
        },
        {
            clave: 'implantaciones',
            titulo: 'De cada sistema, su lista de trabajo',
            cuerpo: '«Ver implantaciones», en el menú de la fila, abre los requisitos que se le exigen a ese sistema. Borrar un sistema se lleva sus implantaciones y la traza de sus estados.',
            anclas: ['sistemas-tabla', 'tabla-filtros'],
            lado: 'arriba',
        },
    ],

    /*
     * La valoración no es un módulo del lateral, pero es la única entrada del
     * motor de categorización y no se parece a la lista de sistemas: merece su
     * propio recorrido.
     */
    valoracion: [
        {
            clave: 'dimensiones',
            titulo: 'Cinco preguntas, una por dimensión',
            cuerpo: 'Para confidencialidad, integridad, trazabilidad, autenticidad y disponibilidad eliges el nivel de perjuicio y escribes por qué. La justificación es lo que el auditor contrasta cuando discute la categoría.',
            anclas: [],
            lado: 'abajo',
        },
        {
            clave: 'categoria',
            titulo: 'La categoría sale sola',
            cuerpo: 'Es la más alta de las cinco y cambia mientras eliges. Al guardar la vuelve a calcular el servidor desde lo escrito, así que lo que ves aquí no se puede forzar.',
            anclas: ['valoracion-categoria'],
            lado: 'abajo',
        },
        {
            clave: 'perfil',
            titulo: 'El perfil, sólo si lo hay',
            cuerpo: 'Un perfil de la serie CCN-STIC 890 acota lo exigible a las medidas que recoge. Es opcional: sin él se exige lo que dicta la categoría.',
            anclas: [],
            lado: 'abajo',
        },
        {
            clave: 'revisar',
            titulo: 'Nada cambia sin verlo antes',
            cuerpo: '«Revisar cambios» enseña, código a código, qué medidas entran, cuáles vuelven, cuáles cambian de refuerzo y cuáles dejan de exigirse. Sólo al confirmar se recalculan las implantaciones.',
            anclas: ['valoracion-revisar'],
            lado: 'arriba',
        },
    ],

    implantaciones: [
        {
            clave: 'que-es',
            titulo: 'Lo que se te exige, requisito a requisito',
            cuerpo: 'Cada fila es un control de la ISO/IEC 27001:2022 o una medida del ENS aplicada a un sistema, con su estado, su responsable y su madurez. La Declaración de Aplicabilidad sale de aquí.',
            anclas: ['nav-implantaciones'],
            lado: 'derecha',
        },
        {
            clave: 'derivada',
            titulo: 'Se calcula y se recuerda',
            cuerpo: 'Qué aplica a cada sistema sale de su valoración; nadie lo marca a mano. Cada cambio de estado queda con fecha y autor, porque el auditor no pregunta si está implantado, pregunta desde cuándo.',
            anclas: ['implantaciones-tabla', 'tabla-filtros'],
            lado: 'arriba',
        },
        {
            clave: 'filtros',
            titulo: 'Lo que pide atención',
            cuerpo: '«Pendiente», «Fecha objetivo pasada», «Sin trabajo planificado» e «Implantado sin prueba» separan lo que está a medias. Seleccionando filas cambias el estado de varias a la vez.',
            anclas: ['tabla-filtros', 'implantaciones-tabla'],
            lado: 'abajo',
        },
        {
            clave: 'por-atributo',
            titulo: 'Agrupadas por atributo',
            cuerpo: 'La vista «Por atributo ISO 27002» reparte lo exigible por una de las cinco dimensiones de atributos de la ISO/IEC 27002, y cada fila te trae aquí con el filtro puesto. Un control con varios valores cuenta en cada uno.',
            anclas: ['cabecera-acciones'],
            lado: 'abajo',
        },
    ],

    evidencias: [
        {
            clave: 'que-es',
            titulo: 'Las pruebas, una sola vez',
            cuerpo: 'Una captura, un acta o un enlace que demuestra que algo se cumple. Se vincula a todos los requisitos que prueba, de la ISO y del ENS a la vez, y deja de mantenerse por duplicado.',
            anclas: ['nav-evidencias'],
            lado: 'derecha',
        },
        {
            clave: 'vigencia',
            titulo: 'Las pruebas caducan',
            cuerpo: 'Con una periodicidad de renovación, la caducidad se calcula sola y avisa antes de vencer. Renovar da de alta otra: la anterior conserva lo que probó durante su periodo.',
            anclas: ['evidencias-tabla', 'tabla-filtros'],
            lado: 'arriba',
        },
        {
            clave: 'nueva',
            titulo: 'Registra una',
            cuerpo: 'Con «Nueva evidencia», arriba a la derecha de la tabla. Desde su ficha la vinculas a los requisitos que prueba.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'evidencias-tabla'],
            lado: 'abajo',
            permiso: 'evidencias.gestionar',
        },
        {
            clave: 'filtros',
            titulo: 'Lo que hay que renovar',
            cuerpo: '«Caducadas» y «Por caducar» dejan a la vista las que ya no prueban nada o están a punto. Las que ya se renovaron no salen.',
            anclas: ['tabla-filtros', 'evidencias-tabla'],
            lado: 'abajo',
        },
    ],

    documentos: [
        {
            clave: 'que-es',
            titulo: 'Lo que se entrega y lo que se firma',
            cuerpo: 'Dos familias: lo que se genera desde el registro —la SoA, la Declaración de Aplicabilidad del ENS, el plan de adecuación, las actas y los informes— y lo que se redacta: políticas, normas y procedimientos.',
            anclas: ['nav-documentos'],
            lado: 'derecha',
        },
        {
            clave: 'emitida',
            titulo: 'Una versión firmada no se toca',
            cuerpo: 'Aprobar es lo que emite: la firma sale impresa en el PDF, la versión recibe número y queda congelada tal cual se entregó. Cambiar algo después es otra versión, con la anterior a la vista.',
            anclas: ['documentos-tabla', 'tabla-filtros'],
            lado: 'arriba',
        },
        {
            clave: 'pendiente',
            titulo: 'Lo que espera de ti',
            cuerpo: 'Revisiones vencidas y firmas pendientes salen arriba. Un documento puede pedir acuse de lectura, y el filtro «Pendientes de mi acuse» te deja los tuyos.',
            anclas: ['documentos-alertas', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'nuevo',
            titulo: 'Crea uno',
            cuerpo: 'Con «Nuevo documento», arriba a la derecha de la tabla. Eliges el tipo y el sistema; el contenido de las declaraciones sale del registro, no se escribe a mano.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'documentos-tabla'],
            lado: 'abajo',
            permiso: 'documentos.generar',
        },
    ],

    conformidad: [
        {
            clave: 'que-es',
            titulo: 'La conformidad con el ENS',
            cuerpo: 'En categoría básica la organización se autoevalúa, firma la Declaración de Conformidad y publica el distintivo. Media y alta se certifican con una entidad acreditada por ENAC, y esa vía aquí sólo está modelada.',
            anclas: ['nav-conformidad'],
            lado: 'derecha',
        },
        {
            clave: 'por-sistema',
            titulo: 'Una fila por sistema',
            cuerpo: 'Se declara sistema a sistema, y la categoría queda congelada el día en que se inicia: revalorar después no cambia lo declarado. Las declaraciones anteriores viven en la ficha.',
            anclas: ['conformidad-sistemas'],
            lado: 'abajo',
        },
        {
            clave: 'bloqueos',
            titulo: 'La ficha te dice qué falta',
            cuerpo: 'Declarar exige la última autoevaluación cerrada, sin puntos pendientes, y ninguna no conformidad mayor abierta. La ficha enseña cada bloqueo antes de que pulses nada.',
            anclas: ['conformidad-sistemas'],
            lado: 'abajo',
        },
        {
            clave: 'firma',
            titulo: 'Se firma en Documentos y dura dos años',
            cuerpo: 'La Declaración se aprueba en Documentos, con la misma firma que cualquier otro documento. Vale dos años y la renovación se prepara con la anterior todavía en vigor.',
            anclas: ['nav-documentos'],
            lado: 'derecha',
        },
    ],
} satisfies Record<string, PasoRecorrido[]>;
