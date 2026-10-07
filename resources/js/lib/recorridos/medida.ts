import type { PasoRecorrido } from '@/lib/recorridos';

/**
 * Los recorridos del grupo «Medida» y de la parte de «Alcance» que habla de la
 * organización: indicadores, objetivos, cambios del SGSI, revisión por la
 * dirección, contexto, partes interesadas y comunicación.
 *
 * En las pantallas con tabla, el alta vive en la barra de `DataTable`
 * (`tabla-acciones`) y no en la cabecera; la tabla entera queda de respaldo.
 */
export const recorridosMedida = {
    indicadores: [
        {
            clave: 'que-es',
            titulo: 'Qué se mide y contra qué',
            cuerpo:
                'La cláusula 9.1 de la ISO/IEC 27001:2022 pide decidir qué se mide, cada cuánto y contra qué objetivo. Cada indicador lo declara, y su serie es la que contesta si algo ha mejorado desde la última revisión.',
            anclas: ['indicadores-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'sellado',
            titulo: 'Cada periodo se sella',
            cuerpo:
                'Al cerrar un periodo, los indicadores calculados se miden solos y la cifra se guarda con el objetivo que regía entonces; los manuales se registran a mano. Subir el listón después no reescribe el veredicto de los periodos anteriores.',
            anclas: ['indicadores-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'rojo',
            titulo: 'El rojo es no medir',
            cuerpo:
                'Quedarse por debajo del objetivo es la distancia que queda, no una alarma. Lo que se pinta en rojo es un periodo cerrado sin medición, porque eso es la 9.1 sin hacer.',
            anclas: ['indicadores-tira', 'indicadores-tabla'],
            lado: 'abajo',
        },
        {
            clave: 'nuevo',
            titulo: 'Declarar un indicador',
            cuerpo:
                'El botón «Nuevo indicador», arriba a la derecha de la tabla. Eliges un cálculo de la lista o lo declaras manual, con su cadencia, su sentido y su objetivo.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'indicadores-tabla'],
            lado: 'abajo',
            permiso: 'indicadores.gestionar',
        },
    ],
    objetivos: [
        {
            clave: 'que-es',
            titulo: 'A qué se compromete la organización',
            cuerpo:
                'Los objetivos de seguridad de la cláusula 6.2: qué se hará, con qué recursos, quién responde, para cuándo y cómo se comprobará. Lo que se hará son tareas y cómo se comprueba son indicadores, así que nada se escribe dos veces.',
            anclas: ['objetivos-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'firma',
            titulo: 'Un borrador no es un compromiso',
            cuerpo:
                'Un objetivo propuesto se apunta como se pueda; para aprobarlo hacen falta plazo y firma. Darlo por no alcanzado, retirarlo o reabrirlo exige escribir el motivo, y todo queda en su histórico.',
            anclas: ['objetivos-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'medible',
            titulo: 'Las cifras al lado, el veredicto de quien firma',
            cuerpo:
                'La herramienta enseña cuántos de sus indicadores llegan a su meta, pero quien firma declara si se alcanzó. Un objetivo sin ningún indicador se señala aquí arriba: la 6.2 pide que sea medible.',
            anclas: ['objetivos-tira', 'objetivos-tabla'],
            lado: 'abajo',
        },
        {
            clave: 'nuevo',
            titulo: 'Proponer un objetivo',
            cuerpo:
                'El botón «Nuevo objetivo», arriba a la derecha de la tabla. Desde su ficha se vinculan los indicadores y se abren sus actuaciones.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'objetivos-tabla'],
            lado: 'abajo',
            permiso: 'objetivos.gestionar',
        },
    ],
    'cambios-sgsi': [
        {
            clave: 'que-es',
            titulo: 'Cambios del sistema de gestión',
            cuerpo:
                'La cláusula 6.3 pide que los cambios del SGSI se hagan de forma planificada: alcance, política, roles, procesos, recursos o documentación. Los cambios técnicos de los sistemas no van aquí.',
            anclas: ['cambios-sgsi-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'ciclo',
            titulo: 'Implantar no es revisar',
            cuerpo:
                'Un cambio pasa de propuesto a aprobado, implantado y revisado, y aprobarlo exige firma y fecha prevista. Revisar pide escribir si se cumplió el propósito que se declaró al principio.',
            anclas: ['cambios-sgsi-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'plazo',
            titulo: 'El rojo es de plazo',
            cuerpo:
                'Sólo se pinta en rojo un cambio aprobado cuya fecha prevista pasó sin implantarse. Un propuesto no ha comprometido nada todavía.',
            anclas: ['cambios-sgsi-tira', 'cambios-sgsi-tabla'],
            lado: 'abajo',
        },
        {
            clave: 'nuevo',
            titulo: 'Proponer un cambio',
            cuerpo:
                'El botón «Nuevo cambio», arriba a la derecha de la tabla. Propósito, consecuencias y recursos se pueden rellenar después, antes de pedir la firma.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'cambios-sgsi-tabla'],
            lado: 'abajo',
            permiso: 'cambios_sgsi.gestionar',
        },
    ],
    'revision-direccion': [
        {
            clave: 'que-es',
            titulo: 'La dirección revisa el SGSI',
            cuerpo:
                'La cláusula 9.3 cierra la lista de lo que la dirección tiene que mirar: siete entradas, de las acciones de la revisión anterior a las oportunidades de mejora. Statera las recoge de los demás módulos, no hay que copiarlas.',
            anclas: ['revision-direccion-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'instantanea',
            titulo: 'Al firmar, se congela',
            cuerpo:
                'Mientras la revisión está abierta, sus entradas se ven en vivo; al aprobar el acta quedan congeladas tal como estaban ese día. Así el acta de marzo no enseña las no conformidades de octubre.',
            anclas: ['revision-direccion-sin-firmar', 'revision-direccion-tabla'],
            lado: 'abajo',
        },
        {
            clave: 'decisiones',
            titulo: 'Las decisiones son tareas',
            cuerpo:
                'Lo que la dirección decide se registra como tareas con responsable y plazo. La revisión siguiente las recoge como «acciones de revisiones previas».',
            anclas: ['revision-direccion-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'convocar',
            titulo: 'Convocar una revisión',
            cuerpo:
                'El botón «Convocar revisión», arriba a la derecha de la tabla. Propone como periodo el que sigue a la última aprobada, para que no quede un hueco sin revisar.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'revision-direccion-tabla'],
            lado: 'abajo',
            permiso: 'revision_direccion.gestionar',
        },
    ],
    contexto: [
        {
            clave: 'que-es',
            titulo: 'El contexto de la organización',
            cuerpo:
                'Las cláusulas 4.1 a 4.3: qué tiene la organización a favor y en contra, quién le exige qué y hasta dónde llega el SGSI. De aquí cuelgan la apreciación de riesgos y la revisión por la dirección.',
            anclas: ['contexto-analisis'],
            lado: 'abajo',
        },
        {
            clave: 'congelar',
            titulo: 'Se trabaja en vivo, se aprueba congelado',
            cuerpo:
                'Las cuestiones se editan en un borrador que se abre solo al registrar la primera; aprobarlo congela el DAFO, las partes y el alcance tal como están. Antes hay que contestar si el cambio climático es pertinente, porque «no lo hemos mirado» no vale como respuesta.',
            anclas: ['contexto-analisis'],
            lado: 'abajo',
        },
        {
            clave: 'dafo',
            titulo: 'El DAFO',
            cuerpo:
                'Cada cuadrante tiene su «+» para añadir una cuestión. Una cuestión se vincula a los riesgos que la consideran y a las tareas que la atienden.',
            anclas: ['contexto-dafo'],
            lado: 'arriba',
        },
        {
            clave: 'vistas',
            titulo: 'Matriz, tabla o revisiones',
            cuerpo:
                'Las mismas cuestiones se ven como matriz o como tabla, que es donde se filtran. En «Revisiones» están los análisis aprobados antes, cada uno con lo que se congeló.',
            anclas: ['cabecera-acciones'],
            lado: 'abajo',
        },
    ],
    'partes-interesadas': [
        {
            clave: 'que-es',
            titulo: 'Quién exige qué',
            cuerpo:
                'La cláusula 4.2: las partes interesadas en la seguridad de la organización y lo que le exige cada una. El tipo de parte propone si es interna o externa, pero lo decides tú.',
            anclas: ['partes-interesadas-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'obliga',
            titulo: 'Lo que obliga se ata a una medida',
            cuerpo:
                'Un requisito legal o contractual se vincula a la implantación que lo atiende, y la Declaración de Aplicabilidad lo imprime como «exigido por» al justificar ese control. Una simple expectativa se apunta, pero no obliga.',
            anclas: ['partes-interesadas-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'sin-cubrir',
            titulo: 'Obligaciones sin cubrir',
            cuerpo:
                'Aquí arriba se cuentan las partes con algún requisito que obliga y no tiene medida atada. Cada cifra lleva a su lista.',
            anclas: ['partes-interesadas-tira', 'partes-interesadas-tabla'],
            lado: 'abajo',
        },
        {
            clave: 'nueva',
            titulo: 'Registrar una parte',
            cuerpo:
                'El botón «Nueva parte interesada», arriba a la derecha de la tabla. Sus requisitos se añaden desde su ficha.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'partes-interesadas-tabla'],
            lado: 'abajo',
            permiso: 'contexto.gestionar',
        },
    ],
    'plan-comunicacion': [
        {
            clave: 'que-es',
            titulo: 'El plan de comunicación',
            cuerpo:
                'La cláusula 7.4 pide decidir qué se comunica, cuándo, a quién, quién lo hace y cómo. Cada línea del plan es una de esas decisiones.',
            anclas: ['plan-comunicacion-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'cadencia',
            titulo: 'La próxima fecha se calcula',
            cuerpo:
                'Cada línea periódica vence a partir de lo último que se comunicó, más su cadencia en meses; «cuando proceda» no vence nunca. Registrar lo comunicado es lo que la cumple.',
            anclas: ['plan-comunicacion-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'vencida',
            titulo: 'El único rojo',
            cuerpo:
                'Una línea periódica cuya fecha pasó sin comunicarse. Sale aquí, en el panel y en el calendario.',
            anclas: ['plan-comunicacion-tira', 'plan-comunicacion-tabla'],
            lado: 'abajo',
        },
        {
            clave: 'nueva',
            titulo: 'Añadir una línea al plan',
            cuerpo:
                'El botón «Nueva comunicación», arriba a la derecha de la tabla. Los destinatarios son partes interesadas registradas; el resto se escribe aparte.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'plan-comunicacion-tabla'],
            lado: 'abajo',
            permiso: 'plan_comunicacion.gestionar',
        },
    ],
    comunicaciones: [
        {
            clave: 'que-es',
            titulo: 'Lo comunicado y lo recibido',
            cuerpo:
                'Lo que se comunicó a las partes interesadas y lo que se recibió de ellas, en el mismo registro. Lo comunicado cumple su línea del plan de comunicación.',
            anclas: ['comunicaciones-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'retroalimentacion',
            titulo: 'Lo recibido llega a la dirección',
            cuerpo:
                'Quejas, sugerencias, consultas y encuestas son la retroalimentación que la revisión por la dirección tiene que mirar (9.3.2 e). Una queja apuntada no es una alarma: es una organización que escucha.',
            anclas: ['comunicaciones-tabla'],
            lado: 'arriba',
        },
        {
            clave: 'registrar',
            titulo: 'Registrar un hecho',
            cuerpo:
                'Los botones «Registrar lo comunicado» y «Registrar lo recibido», arriba a la derecha de la tabla. Es algo que ya pasó, así que no admite fecha futura.',
            anclas: ['tabla-acciones', 'cabecera-acciones', 'comunicaciones-tabla'],
            lado: 'abajo',
            permiso: 'plan_comunicacion.gestionar',
        },
    ],
} satisfies Record<string, PasoRecorrido[]>;
