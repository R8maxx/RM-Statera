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
    /** Sólo para el progreso, que cuenta algo mientras crece. */
    narrativa: 0.9,
} as const;

/** Salida rápida y frenada larga: se percibe como respuesta, no como espera. */
export const curva = [0.16, 1, 0.3, 1] as const;

/** Para lo que se arrastra o rebota levemente: paneles, popovers. */
export const muelle = { type: 'spring', stiffness: 220, damping: 26, mass: 0.9 } as const;

export const transicion = { duration: duracion.normal, ease: curva } as const;
export const transicionLenta = { duration: duracion.lenta, ease: curva } as const;

/**
 * Entrada de una página o de un bloque. El desplazamiento es corto a propósito:
 * ocho píxeles ordenan la lectura, treinta la interrumpen.
 */
export const entrada: Variantes = {
    oculto: { opacity: 0, y: 8 },
    visible: { opacity: 1, y: 0, transition: transicion },
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
} as const;

/** Variantes ya degradadas para quien pide menos movimiento. */
export const estatico: Variantes = {
    oculto: { opacity: 1, y: 0 },
    visible: { opacity: 1, y: 0, transition: { duration: 0 } },
};
