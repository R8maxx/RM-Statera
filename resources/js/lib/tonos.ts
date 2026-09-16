/**
 * El tono del dominio, traducido a clases.
 *
 * **Un solo sitio.** Antes este mapa estaba copiado en cinco: `CeldaBadge`, la
 * cabecera de columna del tablero, el chip del calendario, `BarraSegmentada` y
 * `GraficaBarras`. Cinco copias del mismo vocabulario es cómo se acaba con un
 * «implantado» verde en una pantalla y gris en otra.
 *
 * Lo que llega del servidor es **el tono**, no la clave del enum: `caducada`,
 * `implantado`, `tipo:datos`. Aquí se traduce, y sólo aquí.
 *
 * **Las clases van escritas enteras y nunca compuestas en ejecución.** Tailwind
 * analiza este fichero como texto: `bg-estado-${tono}-suave` no se genera y el
 * badge sale sin fondo.
 */
export interface Tono {
    /** Fondo y texto del badge. */
    badge: string;
    /** El punto de color, cuando no hay icono que lo sustituya. */
    punto: string | null;
    /** El fondo sólido: el punto de una cabecera, la barra de una gráfica. */
    relleno: string;
    /**
     * El fondo dentro de una barra por tramos.
     *
     * Casi siempre es el mismo que `relleno`. Los dos grises van más apagados a
     * propósito: en una barra, «no iniciado» y «no aplica» son el hueco que
     * queda por llenar, y a plena saturación pesan tanto como lo que sí se ha
     * hecho.
     */
    tramo: string;
    /** El icono de respaldo, si el servidor no manda uno. */
    icono: string | null;
}

/*
 * Los cinco estados del dominio. El icono lo manda normalmente el servidor
 * —`EstadoTarea::icono()` y compañía—; el de aquí es el respaldo para cuando un
 * tono llega sin él, que pasa con los que se calculan al vuelo.
 */
const estados: Record<string, Tono> = {
    no_iniciado: {
        badge: 'bg-estado-no-iniciado-suave text-estado-no-iniciado',
        punto: 'bg-estado-no-iniciado',
        relleno: 'bg-estado-no-iniciado',
        tramo: 'bg-estado-no-iniciado/45',
        icono: 'Circle',
    },
    planificado: {
        badge: 'bg-estado-planificado-suave text-estado-planificado',
        punto: 'bg-estado-planificado',
        relleno: 'bg-estado-planificado',
        tramo: 'bg-estado-planificado',
        icono: 'CalendarClock',
    },
    en_progreso: {
        badge: 'bg-estado-en-progreso-suave text-estado-en-progreso',
        punto: 'bg-estado-en-progreso',
        relleno: 'bg-estado-en-progreso',
        tramo: 'bg-estado-en-progreso',
        icono: 'CircleDotDashed',
    },
    implantado: {
        badge: 'bg-estado-implantado-suave text-estado-implantado',
        punto: 'bg-estado-implantado',
        relleno: 'bg-estado-implantado',
        tramo: 'bg-estado-implantado',
        icono: 'CircleCheck',
    },
    no_aplica: {
        badge: 'bg-estado-no-aplica-suave text-estado-no-aplica',
        punto: 'bg-estado-no-aplica',
        relleno: 'bg-estado-no-aplica',
        tramo: 'bg-estado-no-aplica/35',
        icono: 'CircleSlash',
    },

    /*
     * El violeta de marca, y el único badge de estado que lo gasta. DESIGN.md lo
     * tenía declarado desde el principio y sin flujo detrás; el flujo de
     * aprobación documental es ese flujo, y es exactamente lo que ese acento
     * tiene reservado: revisión y auditoría.
     */
    en_revision: {
        badge: 'bg-estado-en-revision-suave text-estado-en-revision',
        punto: 'bg-estado-en-revision',
        relleno: 'bg-estado-en-revision',
        tramo: 'bg-estado-en-revision',
        icono: 'Eye',
    },

    /*
     * El único rojo del vocabulario. Una evidencia caducada o una tarea vencida
     * son de las pocas cosas del dominio que van mal de verdad; DESIGN.md §3 le
     * reserva el rojo a eso y a nada más.
     */
    caducada: {
        badge: 'bg-destructive/10 text-destructive',
        punto: 'bg-destructive',
        relleno: 'bg-destructive',
        tramo: 'bg-destructive',
        icono: 'TriangleAlert',
    },
};

/* Alias: el mismo tono con el nombre que usa cada módulo. */
const alias: Record<string, string> = {
    borrador: 'no_iniciado',
    activo: 'implantado',
    archivado: 'no_aplica',

    /* Ciclo de vida de un activo, sobre los mismos tokens de estado. */
    en_produccion: 'implantado',
    en_mantenimiento: 'en_progreso',
    en_reparacion: 'en_progreso',
    prestado: 'planificado',
    dado_de_baja: 'no_aplica',
};

/*
 * `retirado` no es un alias limpio de `no_aplica`: en una barra va un punto más
 * presente que lo dado de baja, porque un equipo retirado sigue teniendo los
 * datos dentro y lo dado de baja ya no.
 */
const propios: Record<string, Tono> = {
    retirado: {
        badge: 'bg-estado-no-aplica-suave text-estado-no-aplica',
        punto: 'bg-estado-no-aplica',
        relleno: 'bg-estado-no-aplica',
        tramo: 'bg-estado-no-aplica/60',
        icono: 'Archive',
    },
};

/*
 * Lo ordinal sube en énfasis en vez de cambiar de hue: es lo que hace que se lea
 * el orden.
 *
 * **La prioridad de una tarea tiene su propia familia y ya no toma prestada
 * ésta.** Aquí decía que eran «la misma idea», y el código lo desmentía: la
 * categoría del ENS tiene TRES escalones (básica, media, alta) y la exigencia
 * DOS (exigible, reforzado), así que meter cuatro prioridades ahí obligaba a
 * cruzar las dos escalas —`Baja→basica`, `Media→exigible`— y esas dos claves
 * comparten relleno. Resultado visible en el panel: Media y Baja salían
 * idénticas, y en el gris más oscuro de la tira, con lo que la prioridad más
 * baja pesaba más que la más alta.
 *
 * Mismo reparto que ya separó `--tipo-*` de `--estado-*`: una cosa dice CUÁNTO
 * corre y la otra QUÉ se exige, y con la misma tinta se leen como lo mismo.
 */
const ordinales: Record<string, Tono> = {
    basica: { badge: 'bg-muted text-muted-foreground', punto: 'bg-muted-foreground', relleno: 'bg-muted-foreground', tramo: 'bg-muted-foreground', icono: null },
    media: { badge: 'bg-accent text-accent-foreground', punto: 'bg-primary/70', relleno: 'bg-primary/70', tramo: 'bg-primary/70', icono: null },
    alta: { badge: 'bg-primary/15 text-primary ring-1 ring-primary/25', punto: 'bg-primary', relleno: 'bg-primary', tramo: 'bg-primary', icono: null },
    exigible: { badge: 'bg-muted text-foreground', punto: 'bg-muted-foreground', relleno: 'bg-muted-foreground', tramo: 'bg-muted-foreground', icono: null },
    reforzado: { badge: 'bg-accent text-accent-foreground', punto: 'bg-primary', relleno: 'bg-primary', tramo: 'bg-primary', icono: null },
};

/*
 * Prioridad: cuatro escalones de verdad, y el peso crece con la urgencia.
 *
 * Sin rojo a propósito. El rojo tiene tres dueños —una evidencia caducada, una
 * tarea fuera de plazo y `NivelRiesgo::MuyAlto`— y los tres son «va mal de
 * verdad», no «es grande». Una tarea crítica recién creada y en plazo no
 * incumple nada; pintarla de alarma convertiría en fallo algo que la
 * organización acaba de decidir bien.
 */
const prioridades: Record<string, Tono> = {
    'prioridad-baja': { badge: 'bg-muted text-muted-foreground', punto: 'bg-muted-foreground/50', relleno: 'bg-muted-foreground/35', tramo: 'bg-muted-foreground/35', icono: null },
    'prioridad-media': { badge: 'bg-muted text-foreground', punto: 'bg-muted-foreground', relleno: 'bg-muted-foreground/70', tramo: 'bg-muted-foreground/70', icono: null },
    'prioridad-alta': { badge: 'bg-accent text-accent-foreground', punto: 'bg-primary/70', relleno: 'bg-primary/70', tramo: 'bg-primary/70', icono: null },
    'prioridad-critica': { badge: 'bg-primary/15 text-primary ring-1 ring-primary/25', punto: 'bg-primary', relleno: 'bg-primary', tramo: 'bg-primary', icono: null },
};

/* Procedencia, no estado: chip monoespaciado y sin punto, porque no tiene grados. */
const procedencia: Record<string, Tono> = {
    marco: { badge: 'cifra bg-muted text-muted-foreground', punto: null, relleno: 'bg-muted-foreground/40', tramo: 'bg-muted-foreground/40', icono: null },
};

/*
 * Tipología de activos: familia aparte con prefijo propio para que no se mezcle
 * con los estados. Nueve categorías no caben en el hueco de color que dejan los
 * estados —la peor pareja queda en ΔE 5.2— así que el color agrupa y **el icono
 * identifica**: quitarlo deja la distinción por debajo del umbral.
 */
const tipos: Record<string, Tono> = {
    'tipo:servicios': {
        badge: 'bg-tipo-servicios-suave text-tipo-servicios',
        punto: null,
        relleno: 'bg-tipo-servicios',
        tramo: 'bg-tipo-servicios',
        icono: null,
    },
    'tipo:datos': {
        badge: 'bg-tipo-datos-suave text-tipo-datos',
        punto: null,
        relleno: 'bg-tipo-datos',
        tramo: 'bg-tipo-datos',
        icono: null,
    },
    'tipo:software': {
        badge: 'bg-tipo-software-suave text-tipo-software',
        punto: null,
        relleno: 'bg-tipo-software',
        tramo: 'bg-tipo-software',
        icono: null,
    },
    'tipo:hardware': {
        badge: 'bg-tipo-hardware-suave text-tipo-hardware',
        punto: null,
        relleno: 'bg-tipo-hardware',
        tramo: 'bg-tipo-hardware',
        icono: null,
    },
    'tipo:comunicaciones': {
        badge: 'bg-tipo-comunicaciones-suave text-tipo-comunicaciones',
        punto: null,
        relleno: 'bg-tipo-comunicaciones',
        tramo: 'bg-tipo-comunicaciones',
        icono: null,
    },
    'tipo:soportes': {
        badge: 'bg-tipo-soportes-suave text-tipo-soportes',
        punto: null,
        relleno: 'bg-tipo-soportes',
        tramo: 'bg-tipo-soportes',
        icono: null,
    },
    'tipo:equipamiento_auxiliar': {
        badge: 'bg-tipo-equipamiento-auxiliar-suave text-tipo-equipamiento-auxiliar',
        punto: null,
        relleno: 'bg-tipo-equipamiento-auxiliar',
        tramo: 'bg-tipo-equipamiento-auxiliar',
        icono: null,
    },
    'tipo:instalaciones': {
        badge: 'bg-tipo-instalaciones-suave text-tipo-instalaciones',
        punto: null,
        relleno: 'bg-tipo-instalaciones',
        tramo: 'bg-tipo-instalaciones',
        icono: null,
    },
    'tipo:personal': {
        badge: 'bg-tipo-personal-suave text-tipo-personal',
        punto: null,
        relleno: 'bg-tipo-personal',
        tramo: 'bg-tipo-personal',
        icono: null,
    },
};

const neutro: Tono = {
    badge: 'bg-muted text-muted-foreground',
    punto: 'bg-muted-foreground',
    relleno: 'bg-muted-foreground/40',
    tramo: 'bg-muted-foreground/40',
    icono: null,
};

const mapa: Record<string, Tono> = { ...estados, ...ordinales, ...prioridades, ...procedencia, ...tipos };

for (const [nombre, destino] of Object.entries(alias)) {
    mapa[nombre] = mapa[destino];
}

Object.assign(mapa, propios);

/** El tono pedido, o el neutro. Nunca devuelve nada sin clases. */
export function tono(nombre: string | null | undefined): Tono {
    return mapa[nombre ?? ''] ?? neutro;
}
