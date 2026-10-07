import type { PasoRecorrido } from '@/lib/recorridos';

/**
 * Los recorridos del grupo «Ciclo» del lateral: auditorías, no conformidades,
 * mejoras, incidentes, vulnerabilidades y continuidad.
 *
 * El botón de alta no vive en la cabecera sino en la barra de la tabla
 * (`tabla-acciones`), con la tabla entera (`<modulo>-registro`) de respaldo
 * para quien no lo ve. La tira de indicadores puede no pintarse —sin
 * nada pendiente, o con el registro vacío—, y por eso su paso lleva la tabla de
 * respaldo.
 */
export const recorridosCiclo = {
    auditorias: [
        {
            clave: 'que-es',
            titulo: 'Auditorías internas y autoevaluación',
            cuerpo:
                'Aquí se registran las auditorías internas, las externas y la autoevaluación del ENS, que es lo que pide la cláusula 9.2 de la ISO/IEC 27001:2022. Cada una se hace sobre un sistema, porque el sistema es la unidad que se audita y se certifica.',
            anclas: ['auditorias-indicadores', 'auditorias-registro'],
            lado: 'abajo',
        },
        {
            clave: 'checklist',
            titulo: 'La checklist sale de lo exigible',
            cuerpo:
                'La checklist se precarga con las medidas aplicables al sistema, así que ningún punto se puede marcar «no aplica»: como mucho, fuera de muestra. Un punto pendiente no cuenta como conforme, y eso impide que un muestreo parezca una revisión completa.',
            anclas: ['auditorias-registro'],
            lado: 'arriba',
        },
        {
            clave: 'cierre',
            titulo: 'Cerrarla la vuelve un hecho',
            cuerpo:
                'Al cerrar, cada punto guarda la exigencia y el estado que tenía ese día, y ni la checklist ni los hallazgos admiten cambios. Se puede reabrir, pero nunca volver a planificada, que sería decir que no se hizo.',
            anclas: ['auditorias-registro'],
            lado: 'arriba',
        },
        {
            clave: 'nueva',
            titulo: 'Registrar una auditoría',
            cuerpo:
                'El botón «Nueva auditoría», en la barra de la tabla, la da de alta sobre su sistema. Desde su ficha se precarga la checklist, se revisan los puntos y se apuntan los hallazgos.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'auditorias-registro'],
            lado: 'abajo',
            permiso: 'auditorias.gestionar',
        },
    ],

    'no-conformidades': [
        {
            clave: 'que-es',
            titulo: 'Lo que incumple y cómo se corrige',
            cuerpo:
                'Una no conformidad dice por qué pasó algo, qué se hizo, quién responde y si funcionó: es la cláusula 10.2 de la ISO/IEC 27001:2022. Puede salir de un hallazgo de auditoría, de un incidente, de una prueba de continuidad o apuntarse suelta.',
            anclas: ['no-conformidades-registro'],
            lado: 'arriba',
        },
        {
            clave: 'accion-correctiva',
            titulo: 'La acción correctiva es una tarea',
            cuerpo:
                'No es un texto en una columna: es una tarea con responsable, plazo y coste, que aparece en el tablero, en el calendario y en el plan de adecuación. Si la no conformidad cuelga de una medida, la tarea queda vinculada también a esa medida.',
            anclas: ['no-conformidades-registro'],
            lado: 'arriba',
        },
        {
            clave: 'sin-verificar',
            titulo: 'Cerrada no es verificada',
            cuerpo:
                'Cerrarla es darla por tratada; verificarla es comprobar después que la corrección sirvió, y lo firma alguien con permiso de supervisión. Por eso «Sin verificar» va en rojo: es el paso que el auditor mira porque todo el mundo se lo salta.',
            anclas: ['no-conformidades-indicadores', 'no-conformidades-registro'],
            lado: 'abajo',
        },
        {
            clave: 'nueva',
            titulo: 'Abrir una no conformidad',
            cuerpo:
                'El botón «Nueva no conformidad», en la barra de la tabla, abre una suelta. Las que vienen de un hallazgo o de un incidente se abren desde su ficha, con el origen ya puesto.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'no-conformidades-registro'],
            lado: 'abajo',
            permiso: 'no_conformidades.gestionar',
        },
    ],

    mejoras: [
        {
            clave: 'que-es',
            titulo: 'Lo que se puede hacer mejor',
            cuerpo:
                'La cláusula 10.1 de la ISO/IEC 27001:2022 pide mejorar de forma continua, y aquí se apunta lo que se puede hacer mejor sin que nada incumpla. Lo que sí incumple va a no conformidades, para que una idea apuntada no cuente como un fallo.',
            anclas: ['mejoras-registro'],
            lado: 'arriba',
        },
        {
            clave: 'sin-rojo',
            titulo: 'Un registro sin alertas',
            cuerpo:
                'Una idea sin hacer no incumple nada y descartarla es una decisión legítima, así que aquí nada se pinta en rojo, ni siquiera la fecha prevista. Lo que sí se cuenta es lo que lleva tiempo sin empezar.',
            anclas: ['mejoras-indicadores', 'mejoras-registro'],
            lado: 'abajo',
        },
        {
            clave: 'nueva',
            titulo: 'Apuntar una mejora',
            cuerpo:
                'El botón «Nueva mejora», en la barra de la tabla, la registra. El trabajo que haga falta se lleva como tareas desde su ficha, y sólo descartarla pide un motivo escrito.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'mejoras-registro'],
            lado: 'abajo',
            permiso: 'mejoras.gestionar',
        },
    ],

    incidentes: [
        {
            clave: 'que-es',
            titulo: 'El registro de incidentes',
            cuerpo:
                'Es la medida op.exp.7 del Esquema Nacional de Seguridad, exigible desde la categoría básica. Cada incidente guarda cuándo empezó y cuándo se detectó, qué dimensiones afectó y qué activos tocó.',
            anclas: ['incidentes-registro'],
            lado: 'arriba',
        },
        {
            clave: 'plazos',
            titulo: 'Sólo hay reloj donde la ley lo pone',
            cuerpo:
                'Si hubo datos personales, la notificación a la AEPD tiene 72 horas desde la detección (artículo 33.1 del RGPD), y es el único rojo del módulo. Al CCN-CERT se notifica «sin dilación», sin un número de horas, así que la herramienta no se lo inventa.',
            anclas: ['incidentes-indicadores', 'incidentes-registro'],
            lado: 'abajo',
        },
        {
            clave: 'leccion',
            titulo: 'Resuelto no es cerrado',
            cuerpo:
                'Resuelto es que el servicio ha vuelto; cerrado es que además se ha escrito la lección aprendida. La lección se puede ir escribiendo desde la ficha mientras se resuelve.',
            anclas: ['incidentes-registro'],
            lado: 'arriba',
        },
        {
            clave: 'nuevo',
            titulo: 'Registrar un incidente',
            cuerpo:
                'El botón «Registrar incidente», en la barra de la tabla, lo da de alta. Si aún no sabes cómo clasificarlo, «Otros» existe para eso.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'incidentes-registro'],
            lado: 'abajo',
            permiso: 'incidentes.gestionar',
        },
    ],

    vulnerabilidades: [
        {
            clave: 'que-es',
            titulo: 'Vulnerabilidades técnicas',
            cuerpo:
                'El control A.8.8 de la ISO/IEC 27001:2022 y la medida op.exp.4 del ENS piden gestionarlas. Cada una se registra con su severidad, los activos donde está y el plazo para remediarla.',
            anclas: ['vulnerabilidades-registro'],
            lado: 'arriba',
        },
        {
            clave: 'severidad-y-plazo',
            titulo: 'La severidad sale del CVSS',
            cuerpo:
                'Si hay puntuación CVSS, la severidad se deriva de ella con los tramos de FIRST y no se elige. El plazo de remediación sale de la política de tu organización para esa severidad, y el plazo vencido es el único rojo del registro.',
            anclas: ['vulnerabilidades-indicadores', 'vulnerabilidades-registro'],
            lado: 'abajo',
        },
        {
            clave: 'cierre',
            titulo: 'Aceptar y cerrar llevan firma',
            cuerpo:
                'Mitigada es que se aplicó el arreglo; cerrada es que alguien comprobó por escrito que la vulnerabilidad ya no está. No corregirla a sabiendas es aceptar un riesgo, y eso lo firma alguien con permiso de supervisión.',
            anclas: ['vulnerabilidades-registro'],
            lado: 'arriba',
        },
        {
            clave: 'nueva',
            titulo: 'Registrar una vulnerabilidad',
            cuerpo:
                'El botón «Registrar vulnerabilidad», en la barra de la tabla, abre el formulario. Con un CVE, «Traer de NVD» rellena lo que NVD sabe de ella y avisa si CISA la tiene como explotada.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'vulnerabilidades-registro'],
            lado: 'abajo',
            permiso: 'vulnerabilidades.gestionar',
        },
    ],

    continuidad: [
        {
            clave: 'que-es',
            titulo: 'Continuidad: BIA y pruebas',
            cuerpo:
                'Las medidas op.cont del ENS piden saber cuánto aguanta caído cada servicio y demostrar que el plan funciona. En la pestaña BIA se analiza el impacto de cada servicio, y en Pruebas se registra cómo fueron los simulacros.',
            anclas: ['continuidad-pestanas'],
            lado: 'abajo',
        },
        {
            clave: 'mtpd',
            titulo: 'El umbral tolerable se calcula',
            cuerpo:
                'Valoras el impacto de una caída en cinco tramos, de 4 horas a un mes, y el umbral tolerable es el primero que llega a «muy alto». Un RTO por encima de ese umbral se guarda igual, pero queda marcado para que la contradicción no se esconda.',
            anclas: ['continuidad-registro'],
            lado: 'arriba',
        },
        {
            clave: 'aprobacion',
            titulo: 'Aprobar es aceptar un riesgo',
            cuerpo:
                'Aprobar un BIA lo firma alguien con permiso de supervisión y fija su revisión a doce meses. Si se edita uno aprobado, vuelve a borrador, para que nadie siga confiando en un RTO que acaba de cambiar.',
            anclas: ['continuidad-registro'],
            lado: 'arriba',
        },
        {
            clave: 'nuevo',
            titulo: 'Un BIA por servicio',
            cuerpo:
                'El botón «Registrar BIA», en la barra de la tabla, lo da de alta sobre un activo de tipo servicio del inventario. Cada servicio tiene uno solo.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'continuidad-registro'],
            lado: 'abajo',
            permiso: 'continuidad.gestionar',
        },
    ],

    'continuidad-pruebas': [
        {
            clave: 'que-es',
            titulo: 'Las pruebas del plan',
            cuerpo:
                'La medida op.cont.3 del ENS pide probar el plan de continuidad. Cada prueba cubre uno o varios servicios y anota el tiempo real de recuperación de cada uno.',
            anclas: ['continuidad-pestanas'],
            lado: 'abajo',
        },
        {
            clave: 'fallar-no-es-rojo',
            titulo: 'Fallar no es incumplir',
            cuerpo:
                'Un simulacro fallido es la prueba haciendo su trabajo: descubre el hueco antes de la caída real. Lo que va en rojo es no probar, es decir, una prueba planificada cuya fecha ya pasó.',
            anclas: ['continuidad-pruebas-registro'],
            lado: 'arriba',
        },
        {
            clave: 'despues',
            titulo: 'Lo que deja una prueba',
            cuerpo:
                'Desde la ficha de una prueba realizada que no salió bien puedes abrir tareas, una no conformidad o mejoras, con el origen ya puesto. La evidencia, como el acta del simulacro, se puede adjuntar después del resultado.',
            anclas: ['continuidad-pruebas-registro'],
            lado: 'arriba',
        },
        {
            clave: 'nueva',
            titulo: 'Planificar una prueba',
            cuerpo:
                'El botón «Planificar prueba», en la barra de la tabla, la da de alta con su fecha prevista y los servicios que cubre. Cuando se haga, se registra el resultado desde su ficha.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'continuidad-pruebas-registro'],
            lado: 'abajo',
            permiso: 'continuidad.gestionar',
        },
    ],
} satisfies Record<string, PasoRecorrido[]>;
