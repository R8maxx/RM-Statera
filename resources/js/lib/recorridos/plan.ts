import type { PasoRecorrido } from '@/lib/recorridos';

/**
 * Los recorridos del grupo «Plan» del lateral: riesgos, tareas, calendario y
 * obligaciones.
 */
export const recorridosPlan = {
    riesgos: [
        {
            clave: 'que-es',
            titulo: 'Qué puede pasar y qué se decide',
            cuerpo:
                'Es el análisis de riesgos que piden la ISO 27001 en sus cláusulas 6.1.2 y 6.1.3 y el ENS en op.pl.1. Cada riesgo junta una amenaza, los activos sobre los que pesa y lo que se ha decidido hacer con él.',
            anclas: ['nav-riesgos'],
            lado: 'derecha',
        },
        {
            clave: 'impacto-efectivo',
            titulo: 'El impacto lo ponen los activos',
            cuerpo:
                'El impacto sale de la valoración efectiva de los activos, la que heredan de lo que depende de ellos. Y las salvaguardas apuntan a implantaciones, así que el mismo control prueba cumplimiento y trata el riesgo sin registrarlo dos veces.',
            anclas: ['tabla-filtros', 'nav-riesgos'],
            lado: 'abajo',
        },
        {
            clave: 'residual',
            titulo: 'El residual lo firma una persona',
            cuerpo:
                'El riesgo residual lo declara quien valora y lo acepta la dirección; la herramienta no se lo inventa, pero señala el que baja sin una sola salvaguarda implantada. Estas tarjetas filtran la tabla por lo que pide acción: lo que pasa del umbral, lo pendiente de firma, la reevaluación vencida.',
            anclas: ['riesgos-indicadores', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'nuevo-y-metodologia',
            titulo: 'Nuevo riesgo y la metodología',
            cuerpo:
                'Aquí están «Nuevo riesgo» y «Metodología», donde la dirección fija las escalas y el umbral de aceptación. Cada valoración congela la escala con la que se midió, así que el histórico se puede comparar aunque la metodología cambie.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'tabla-filtros'],
            lado: 'abajo',
        },
    ],
    tareas: [
        {
            clave: 'que-es',
            titulo: 'El plan de acción',
            cuerpo:
                'Lo que hay que hacer, quién lo hace y para cuándo. Una tarea suele nacer de otro registro —una implantación pendiente, un riesgo, una no conformidad— y guarda ese origen para que se sepa por qué existe.',
            anclas: ['nav-tareas'],
            lado: 'derecha',
        },
        {
            clave: 'vistas',
            titulo: 'Tabla o tablero',
            cuerpo:
                'La tabla sirve para buscar, filtrar y cambiar estados en bloque; el tablero, para ver en qué punto está cada cosa. Son dos direcciones distintas, así que el enlace que compartes abre la vista que estabas mirando.',
            anclas: ['cabecera-acciones'],
            lado: 'abajo',
        },
        {
            clave: 'tablero',
            titulo: 'Arrastra, o usa el menú',
            cuerpo:
                'En el tablero mueves una tarjeta a otra columna arrastrándola o desde su menú, que ofrece los mismos destinos y funciona con el teclado. Descartar exige un motivo, por eso no tiene columna: se hace desde ese menú, y cada cambio queda en el histórico con fecha y autor.',
            anclas: ['tareas-tablero', 'cabecera-acciones'],
            lado: 'arriba',
            permiso: 'tareas.gestionar',
        },
        {
            clave: 'plazo',
            titulo: 'El rojo es del plazo',
            cuerpo:
                'Los estados no gastan el rojo: se reserva para la tarea vencida, para que lo atrasado salte a la vista en la tabla, en el tablero y en el calendario. Marcar todos los pasos de una tarea no la cierra; cerrarla es una decisión con su fecha y su autor.',
            anclas: ['tareas-indicadores', 'tareas-tablero', 'tabla-filtros'],
            lado: 'abajo',
        },
    ],
    calendario: [
        {
            clave: 'que-es',
            titulo: 'Todo lo que vence, en un mes',
            cuerpo:
                'Plazos de tareas, evidencias que caducan, revisiones de documentos, formación por renovar, pruebas de continuidad y las obligaciones periódicas, en la misma rejilla. No tiene registros propios: cada fecha sale de su módulo con la misma regla que usan el panel y el correo diario.',
            anclas: ['calendario-rejilla', 'nav-calendario'],
            lado: 'arriba',
        },
        {
            clave: 'fuentes',
            titulo: 'El icono dice qué es',
            cuerpo:
                'Estos chips filtran y son a la vez la leyenda: cada fuente lleva el icono de su módulo en el lateral, y sólo aparecen las de los módulos que puedes ver. El color dice cómo va, y el rojo sólo lo gasta lo vencido.',
            anclas: ['calendario-fuentes', 'calendario-rejilla', 'nav-calendario'],
            lado: 'abajo',
        },
        {
            clave: 'vencidos',
            titulo: 'Lo atrasado no se pierde al cambiar de mes',
            cuerpo:
                'Lo que venció y sigue abierto se arrastra encima de la rejilla, caiga en el mes que caiga. Pulsa el número de un día para abrir su panel al lado y comparar días sin cerrarlo.',
            anclas: ['calendario-rejilla', 'calendario-fuentes', 'nav-calendario'],
            lado: 'arriba',
        },
    ],
    obligaciones: [
        {
            clave: 'que-es',
            titulo: 'Lo que la norma obliga a repetir',
            cuerpo:
                'La revisión por la dirección, la auditoría interna, el informe INES, la renovación de la conformidad del ENS cada dos años: lo periódico que no sale de ningún otro registro. Aquí se asume cada obligación y se apunta cada vez que se cumple.',
            anclas: ['nav-obligaciones'],
            lado: 'derecha',
        },
        {
            clave: 'propuestas',
            titulo: 'El catálogo propone, tú asumes',
            cuerpo:
                'La herramienta propone las que te tocan según los marcos y la categoría derivada de tus sistemas, pero no las asume sola: es una decisión y la toma alguien. Lo asumido se copia, así que un cambio del catálogo no repinta tu compromiso.',
            anclas: ['obligaciones-asumir', 'obligaciones-indicadores', 'tabla-filtros'],
            lado: 'abajo',
        },
        {
            clave: 'ciclo',
            titulo: 'Cada cumplimiento cubre un periodo',
            cuerpo:
                'La próxima fecha sale del último cumplimiento y de la cadencia, y el aviso salta a noventa días porque contratar una auditoría no se hace en un mes. La ficha enseña los huecos: un periodo sin cubrir fue un incumplimiento aunque hoy estés al día.',
            anclas: ['obligaciones-indicadores', 'tabla-filtros', 'obligaciones-asumir'],
            lado: 'abajo',
        },
        {
            clave: 'calendario',
            titulo: 'También en el calendario',
            cuerpo: 'Estas fechas salen en el calendario junto al resto de vencimientos de la organización.',
            anclas: ['cabecera-acciones'],
            lado: 'abajo',
        },
    ],
} satisfies Record<string, PasoRecorrido[]>;
