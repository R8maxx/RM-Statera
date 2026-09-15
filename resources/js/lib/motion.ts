import type { VariantType } from 'motion-v';

/**
 * El mapa de variantes tal y como lo tipa `<motion.*>`.
 *
 * No se usa el `Variants` que motion-v reexporta de framer-motion: es más laxo
 * y el prop `variants` del componente no lo acepta.
 */
export type Variantes = Record<string, VariantType>;

/**
 * La especificación de movimiento de Statera, declarada una sola vez.
 *
 * Mismo criterio que los colores de estado en `app.css`: si cada componente
 * elige su propia duración y su propia curva, la interfaz deja de moverse como
 * una sola cosa. Ningún componente escribe estos números a mano.
 *
 * El listón para que algo se anime es que comunique jerarquía, narrativa,
 * feedback o un cambio de estado. Lo decorativo no entra: esto es una
 * herramienta que alguien tiene abierta ocho horas, y un bucle infinito en la
 * periferia cansa mucho antes de lo que parece.
 */

/** Duraciones, en segundos, que es la unidad de motion-v. */
export const duracion = {
    rapida: 0.12,
    normal: 0.22,
    lenta: 0.38,
    /**
     * Toda salida dura menos que su entrada.
     *
     * Al entrar, algo se está presentando y conviene poder seguirlo. Al salir ya
     * se ha decidido y sólo falta que se quite de en medio: una salida tan larga
     * como su entrada se percibe como que la interfaz tarda en obedecer.
     */
    salida: 0.16,
    /** Sólo para el progreso, que cuenta algo mientras crece. */
    narrativa: 0.9,
} as const;

/** Salida rápida y frenada larga: se percibe como respuesta, no como espera. */
export const curva = [0.16, 1, 0.3, 1] as const;

/**
 * Para lo que se desplaza DE un sitio A otro, no para lo que aparece.
 *
 * Una tarjeta que viaja de una columna a otra del tablero, o el panel del
 * recorrido persiguiendo al foco, no están apareciendo: están yendo. Con una
 * curva de salida arrancan de golpe, como si se las hubiera empujado. Ésta
 * acelera y frena.
 */
export const curvaEnPantalla = [0.77, 0, 0.175, 1] as const;

/** Paneles laterales. Nunca `ease-in` en algo que entra. */
export const curvaPanel = [0.32, 0.72, 0, 1] as const;

/** Para lo que se arrastra o rebota levemente: paneles, popovers. */
export const muelle = { type: 'spring', stiffness: 220, damping: 26, mass: 0.9 } as const;

export const transicion = { duration: duracion.normal, ease: curva } as const;
export const transicionLenta = { duration: duracion.lenta, ease: curva } as const;
export const transicionSalida = { duration: duracion.salida, ease: curva } as const;
export const transicionEnPantalla = { duration: 0.28, ease: curvaEnPantalla } as const;

/**
 * Entrada de una página o de un bloque. El desplazamiento es corto a propósito:
 * ocho píxeles ordenan la lectura, treinta la interrumpen.
 */
export const entrada: Variantes = {
    oculto: { opacity: 0, y: 8 },
    visible: { opacity: 1, y: 0, transition: transicion },
};

/**
 * La marcha de un bloque, y no es la entrada al revés.
 *
 * Algo que se va **por donde vino** sólo tiene sentido cuando hay dirección: un
 * panel lateral, un toast. Un bloque que desaparece en su sitio —un aviso, una
 * fila, una tarjeta descartada— se va corto y hacia arriba, porque lo que
 * importa no es el recorrido, es que deje de ocupar sitio sin que lo de abajo
 * pegue un tirón.
 */
export const salida: Variantes = {
    oculto: { opacity: 0, y: -4, transition: transicionSalida },
};

/**
 * Contenedor que escalona a sus hijos. Se usa una vez por pantalla, en el
 * primer render; escalonar también las recargas parciales convertiría cada
 * filtrado en una animación y eso estorba.
 */
export const escalonado = (retraso = 0.04): Variantes => ({
    oculto: {},
    visible: { transition: { staggerChildren: retraso, delayChildren: 0.02 } },
});

/** Aparición y desaparición de una barra contextual, sin salto seco. */
export const barraContextual: Variantes = {
    oculto: { opacity: 0, y: -6, height: 0 },
    visible: { opacity: 1, y: 0, height: 'auto', transition: transicion },
};

/**
 * La única excepción al párrafo de arriba, y está acotada a un sitio: la
 * balanza del panel de acceso.
 *
 * Un bucle infinito en la periferia cansa cuando lo tienes ocho horas delante,
 * pero el panel de acceso se mira quince segundos y está fuera del chrome de
 * trabajo. Además se apaga entero con `prefers-reduced-motion`, con la pestaña
 * en segundo plano y por debajo de `lg`, donde el panel ni se pinta.
 *
 * Los tiempos son largos a propósito: una balanza que gira deprisa parece un
 * cargador, y esto no está diciendo que espere nadie.
 */
export const ambiente = {
    /** Segundos por vuelta completa sobre el eje vertical. */
    vuelta: 55,
    /** Segundos por ciclo de basculación del brazo. */
    balanceo: 9,
    /** Amplitud de la basculación, en radianes. Poco más de 3°: mide, no se agita. */
    amplitud: 0.055,
    /** Duración de la aparición inicial, de abajo arriba. Ocurre una sola vez. */
    entrada: 1.1,
    /** Un bucle decorativo no necesita 60 fps, y a 30 gasta la mitad. */
    fps: 30,

    /*
     * Los dos gestos que la balanza hace UNA vez, cuando alguien intenta entrar.
     *
     * No son ambiente: son respuesta. Y están aquí porque son el único sitio del
     * producto donde la marca contesta a lo que ha hecho el usuario — al enviar
     * se queda quieta y cuadra, y si las credenciales no valen se desequilibra y
     * vuelve. Una balanza que se estabiliza y una que se descuadra significan
     * exactamente lo que parece que significan, y eso no hay que explicarlo.
     *
     * La sacudida acompaña al aviso de error escrito; no lo sustituye. §11: un
     * estado no se comunica nunca sólo con movimiento.
     */
    sacudida: 1.1,
    /** Segundos por oscilación del desequilibrio. Da dos vueltas y se apaga. */
    cicloSacudida: 0.55,
    /** Cuánto más amplia que la basculación normal. Se nota, no se agita. */
    factorSacudida: 3.4,
    /** Segundos que tarda el brazo en quedarse plano al enviar. */
    asentar: 0.8,
} as const;

/** Variantes ya degradadas para quien pide menos movimiento. */
export const estatico: Variantes = {
    oculto: { opacity: 1, y: 0 },
    visible: { opacity: 1, y: 0, transition: { duration: 0 } },
};
