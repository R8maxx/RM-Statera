import type { PasoRecorrido } from '@/lib/recorridos';

/**
 * Los recorridos del inventario y de la organización: activos y sus revisiones,
 * personas, puestos, formación y proveedores.
 *
 * En las pantallas de tabla el botón «Nuevo…» vive en la barra de `DataTable` y
 * no en la cabecera, así que el paso de la acción principal cae en el selector de
 * columnas, que está a su lado.
 */
export const recorridosOrganizacion = {
    activos: [
        {
            clave: 'que-es',
            titulo: 'Lo que hay que proteger',
            cuerpo:
                'El inventario de activos: información, servicios, aplicaciones, equipos y lo que los sostiene, valorado en las cinco dimensiones. Un mismo activo puede estar en el alcance de varios sistemas sin darlo de alta dos veces.',
            anclas: ['tabla-filtros', 'nav-activos'],
            lado: 'abajo',
        },
        {
            clave: 'valoracion-efectiva',
            titulo: 'La valoración sube por las dependencias',
            cuerpo:
                'Una base de datos valorada «bajo» que sostiene un servicio esencial vale «alto», y la tabla enseña esa valoración efectiva marcada como «heredado». La tuya no se sobrescribe: en la ficha se ven las dos, con el grafo que explica por dónde le sube.',
            anclas: ['tabla-columnas', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'indicadores',
            titulo: 'Sólo lo que pide acción hoy',
            cuerpo:
                'Encima de la tabla quedan los activos sin cifrar, sin copia o sin soporte, siempre con su denominador. Cada cifra abre la tabla filtrada exactamente por ella.',
            anclas: ['activos-indicadores', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'alta',
            titulo: 'Alta, revisión y etiquetas',
            cuerpo:
                'Desde la barra de la tabla das de alta un activo o imprimes etiquetas QR que abren su ficha en el móvil. Si seleccionas filas, puedes marcarlas como revisadas hoy.',
            anclas: ['tabla-acciones', 'cabecera-acciones'],
            lado: 'abajo',
            permiso: 'activos.gestionar',
        },
    ],
    revisiones: [
        {
            clave: 'que-es',
            titulo: 'Un inventario mantenido',
            cuerpo:
                'El control A.5.9 de la ISO/IEC 27001:2022 y la medida op.exp.1 del ENS no piden un inventario, piden uno mantenido. Cada revisión registrada es la prueba de que alguien lo repasó y cuándo.',
            anclas: ['tabla-filtros', 'nav-revisiones'],
            lado: 'abajo',
        },
        {
            clave: 'cifras-firmadas',
            titulo: 'Las cifras de ese día',
            cuerpo:
                'Las altas y bajas se guardan tal como las contó quien revisó, no se recalculan después. La columna de hallazgos separa una revisión limpia de una que dejó desviaciones anotadas.',
            anclas: ['tabla-columnas', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'registrar',
            titulo: 'Registra la revisión',
            cuerpo:
                'Anota la fecha, el alcance revisado y lo que encontraste. La fecha de cada activo se pone aparte, seleccionándolos en el inventario y marcándolos como revisados hoy.',
            anclas: ['tabla-acciones', 'cabecera-acciones'],
            lado: 'abajo',
            permiso: 'activos.gestionar',
        },
    ],
    personas: [
        {
            clave: 'que-es',
            titulo: 'La plantilla, no las cuentas',
            cuerpo:
                'Aquí se registra quién firma los deberes de confidencialidad, asiste a la formación y puede recibir un rol: la cláusula 5.3 de la ISO y las medidas mp.per del ENS. La mayoría de estas personas nunca entra en Statera, y no necesita cuenta para estar.',
            anclas: ['tabla-filtros', 'nav-personas'],
            lado: 'abajo',
        },
        {
            clave: 'roles-ens',
            titulo: 'Cinco roles por sistema',
            cuerpo:
                'El ENS pide designar por escrito los roles de cada sistema, y esta tarjeta cuenta los que faltan. La herramienta impide que el responsable de seguridad y el responsable del sistema sean la misma persona en el mismo sistema.',
            anclas: ['personas-roles', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'alta',
            titulo: 'Da de alta a cada persona',
            cuerpo:
                'Los nombramientos, el acuerdo de confidencialidad y las checklists de entrada y salida se gestionan desde su ficha. Los nombramientos llevan fecha de inicio y de fin, y no se borran.',
            anclas: ['tabla-acciones', 'cabecera-acciones'],
            lado: 'abajo',
            permiso: 'personas.gestionar',
        },
        {
            clave: 'organigrama',
            titulo: 'El organigrama está en Puestos',
            cuerpo:
                'La jerarquía se declara entre puestos y no entre personas, así que alguien que entra o se va no lo descoloca. Lo encuentras en Puestos, con la lista y el diagrama.',
            anclas: ['nav-puestos'],
            lado: 'derecha',
        },
    ],
    puestos: [
        {
            clave: 'que-es',
            titulo: 'Qué puestos hay',
            cuerpo:
                'El catálogo de puestos, de quién depende cada uno y qué competencia pide, que es la caracterización que nombra la medida mp.per.1 del ENS. Un puesto puede tener varias personas a la vez.',
            anclas: ['puestos-resumen', 'tabla-filtros', 'nav-puestos'],
            lado: 'abajo',
        },
        {
            clave: 'jerarquia',
            titulo: 'La jerarquía vive en el puesto',
            cuerpo:
                'El organigrama sale de cruzar los puestos con quién los ocupa hoy, así que es un solo árbol con dos lecturas. Cada asignación guarda desde cuándo y hasta cuándo, y cambiar de puesto cierra la anterior.',
            anclas: ['tabla-columnas', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'organigrama',
            titulo: 'Organigrama y alta',
            cuerpo:
                'En la barra de la tabla tienes el organigrama, como lista sangrada o como diagrama, y el botón para crear un puesto. El superior se cambia en la ficha del puesto, que rechaza los ciclos.',
            anclas: ['tabla-acciones', 'cabecera-acciones'],
            lado: 'abajo',
        },
    ],
    formacion: [
        {
            clave: 'que-es',
            titulo: 'Concienciar y formar',
            cuerpo:
                'Las sesiones impartidas y quién asistió. El ENS lo separa en dos medidas: concienciación (mp.per.3) es recordar lo que todos tienen que saber, y formación (mp.per.4) es enseñar a hacer algo a quien lo hace.',
            anclas: ['tabla-filtros', 'nav-formacion'],
            lado: 'abajo',
        },
        {
            clave: 'convocar-asistir',
            titulo: 'Convocado no es asistente',
            cuerpo:
                'Quien está en la convocatoria y no fue queda marcado como ausente, con su motivo si lo hubo, que es lo que pregunta el auditor. Una sesión con la lista vacía no prueba nada, y por eso se señala.',
            anclas: ['tabla-columnas', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'registrar',
            titulo: 'Registra la sesión',
            cuerpo:
                'Una sesión con todos sus convocados, y la asistencia se marca en bloque. Lo que una persona tiene pendiente de formación se ve en Personas, no aquí.',
            anclas: ['tabla-acciones', 'cabecera-acciones'],
            lado: 'abajo',
            permiso: 'personas.gestionar',
        },
    ],
    proveedores: [
        {
            clave: 'que-es',
            titulo: 'Con quién trabajas',
            cuerpo:
                'Los terceros, qué le prestan a la organización y cuándo se comprobó su contrato: los controles A.5.19 a A.5.23 de la ISO y las medidas op.ext y op.nub del ENS.',
            anclas: ['proveedores-indicadores', 'tabla-filtros', 'nav-proveedores'],
            lado: 'abajo',
        },
        {
            clave: 'criticidad',
            titulo: 'La criticidad tiene un mínimo',
            cuerpo:
                'Sale de la valoración más alta de los activos que presta, y se recalcula sola cuando esos activos cambian. Puedes declararla por encima; por debajo tienes que justificarlo, porque es la que decide cada cuánto se reevalúa.',
            anclas: ['tabla-columnas', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'evaluacion',
            titulo: 'El estado lo decide la evaluación',
            cuerpo:
                'Se contrasta el contrato cláusula a cláusula y el resultado homologa, condiciona o rechaza al proveedor. La siguiente reevaluación cae en el calendario según los plazos que fija tu organización.',
            anclas: ['proveedores-indicadores', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'alta',
            titulo: 'Da de alta un proveedor',
            cuerpo:
                'Desde aquí lo registras; la evaluación y los certificados se hacen en su ficha. Sin evaluar todavía no está pendiente de reevaluar, sino de evaluar por primera vez.',
            anclas: ['tabla-acciones', 'cabecera-acciones'],
            lado: 'abajo',
            permiso: 'proveedores.gestionar',
        },
    ],
} satisfies Record<string, PasoRecorrido[]>;
