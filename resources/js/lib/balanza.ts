/**
 * La balanza del logotipo, como nube de puntos en tres dimensiones.
 *
 * Esto es geometría y matemáticas, sin DOM y sin canvas: quien quiera cambiar
 * la proporción de un platillo o el grosor del fiel edita números aquí y no
 * tiene que leer un bucle de pintado. `BalanzaPixeles.vue` sólo proyecta y
 * rellena celdas.
 *
 * Las coordenadas salen del `viewBox="0 0 32 32"` de `Logotipo.vue`, movidas al
 * centro y normalizadas a −1…1 con la Y hacia arriba. Cada trazo plano del SVG
 * se revoluciona alrededor de su propio eje para darle volumen. Lo que gira en
 * la pantalla de acceso **es** el logotipo, no un dibujo que se le parece.
 */

export type Punto = {
    x: number;
    y: number;
    z: number;
    /** Va en violeta: el fulcro y las cuerdas. Nada más. */
    acento: boolean;
    /** Cuelga del brazo, así que se inclina con él: brazo, cuerdas, platillos. */
    pende: boolean;
};

/** Del sistema del SVG (0…32, Y hacia abajo) al de la nube (−1…1, Y hacia arriba). */
const ux = (x: number): number => (x - 16) / 16;
const uy = (y: number): number => (16 - y) / 16;

/** Y del brazo, que es también el eje de la inclinación. */
export const Y_BRAZO = uy(10.5);
/** X de los extremos del brazo, de donde cuelga cada platillo. */
const X_BRAZO = ux(27);

/** El platillo derecho cuelga más bajo: en equilibrio perfecto no mide nada. */
const PLATILLOS = [
    { x: -X_BRAZO, y: uy(17.5), radio: 3.5 / 16 },
    { x: X_BRAZO, y: uy(19), radio: 3.5 / 16 },
] as const;

function cilindro(y0: number, y1: number, radio: number, lados: number, pasos: number): Punto[] {
    const puntos: Punto[] = [];

    for (let i = 0; i < pasos; i++) {
        const y = y0 + ((y1 - y0) * i) / (pasos - 1);

        for (let j = 0; j < lados; j++) {
            const a = (j / lados) * Math.PI * 2;
            puntos.push({ x: Math.cos(a) * radio, y, z: Math.sin(a) * radio, acento: false, pende: false });
        }
    }

    return puntos;
}

/** Varilla horizontal: el mismo cilindro tumbado sobre el eje X. */
function varilla(y: number, x0: number, x1: number, radio: number, lados: number, pasos: number): Punto[] {
    const puntos: Punto[] = [];

    for (let i = 0; i < pasos; i++) {
        const x = x0 + ((x1 - x0) * i) / (pasos - 1);

        for (let j = 0; j < lados; j++) {
            const a = (j / lados) * Math.PI * 2;
            puntos.push({ x, y: y + Math.sin(a) * radio, z: Math.cos(a) * radio, acento: false, pende: true });
        }
    }

    return puntos;
}

/** Anillos concéntricos en el plano XZ. La base y, con hondura, los platillos. */
function anillos(
    cx: number,
    cy: number,
    radio: number,
    fracciones: readonly number[],
    lados: number,
    hondura: number,
    pende: boolean,
): Punto[] {
    const puntos: Punto[] = [];

    for (const f of fracciones) {
        for (let j = 0; j < lados; j++) {
            const a = (j / lados) * Math.PI * 2;
            puntos.push({
                x: cx + Math.cos(a) * radio * f,
                y: cy + (1 - f * f) * hondura,
                z: Math.sin(a) * radio * f,
                acento: false,
                pende,
            });
        }
    }

    return puntos;
}

/** Esfera de Fibonacci: puntos repartidos sin acumularse en los polos. */
function esfera(cy: number, radio: number, total: number): Punto[] {
    const puntos: Punto[] = [];
    const dorado = Math.PI * (3 - Math.sqrt(5));

    for (let i = 0; i < total; i++) {
        const y = 1 - (i / (total - 1)) * 2;
        const r = Math.sqrt(Math.max(0, 1 - y * y));
        const a = dorado * i;
        puntos.push({
            x: Math.cos(a) * r * radio,
            y: cy + y * radio,
            z: Math.sin(a) * r * radio,
            acento: true,
            pende: false,
        });
    }

    return puntos;
}

/**
 * Una cuerda de suspensión. Sólo su tramo alto va en violeta.
 *
 * Pintarla entera de acento subía el violeta al 20 % de las celdas encendidas,
 * el doble de lo que le toca. Concentrándolo arriba, además, el acento queda
 * donde significa algo: el punto del que cuelga el platillo.
 */
function cuerda(desde: Punto, hasta: Punto, pasos: number, tramoAcento: number): Punto[] {
    const puntos: Punto[] = [];

    for (let i = 0; i < pasos; i++) {
        const t = i / (pasos - 1);
        puntos.push({
            x: desde.x + (hasta.x - desde.x) * t,
            y: desde.y + (hasta.y - desde.y) * t,
            z: desde.z + (hasta.z - desde.z) * t,
            acento: t <= tramoAcento,
            pende: true,
        });
    }

    return puntos;
}

/**
 * La nube completa, ~700 puntos. Se calcula una vez y no cambia: lo que se
 * mueve en cada fotograma son los dos ángulos, no la geometría.
 *
 * El violeta se queda en el fulcro y en el tramo alto de las cuerdas. Lo que
 * cuenta para el reparto 60 / 30 / 10 de DESIGN.md §3 no son los puntos sino las
 * celdas encendidas, y medido a la escala real da un 10 % de media y un 12 % en
 * el ángulo peor. Cualquier retoque de la geometría debería volver a medirlo.
 */
let cache: readonly Punto[] | null = null;

export function nubeBalanza(): readonly Punto[] {
    if (cache) {
        return cache;
    }

    const puntos: Punto[] = [
        // El fiel y su base. El radio es fino a propósito: con la retícula de
        // píxeles todos los puntos de un anillo caen en las mismas dos celdas,
        // así que un cilindro generoso se pinta como una losa opaca.
        ...cilindro(uy(25.5), uy(6.5), 0.034, 7, 24),
        ...anillos(0, uy(26.5), 0.3125, [0.55, 1], 22, 0, false),
        // El brazo, y el fulcro en su centro.
        ...varilla(Y_BRAZO, -X_BRAZO, X_BRAZO, 0.026, 5, 40),
        ...esfera(Y_BRAZO, 0.075, 22),
    ];

    for (const platillo of PLATILLOS) {
        puntos.push(...anillos(platillo.x, platillo.y, platillo.radio, [0.4, 0.72, 1], 16, 0.055, true));

        // Tres cuerdas por platillo, del extremo del brazo al borde del cuenco.
        for (let i = 0; i < 3; i++) {
            const a = (i / 3) * Math.PI * 2;
            puntos.push(
                ...cuerda(
                    { x: platillo.x, y: Y_BRAZO, z: 0, acento: true, pende: true },
                    {
                        x: platillo.x + Math.cos(a) * platillo.radio,
                        y: platillo.y,
                        z: Math.sin(a) * platillo.radio,
                        acento: true,
                        pende: true,
                    },
                    9,
                    0.45,
                ),
            );
        }
    }

    cache = Object.freeze(puntos);

    return cache;
}

/** Lo que necesita el pintado de cada punto, ya en píxeles y con su profundidad. */
export type Proyeccion = {
    /** Desplazamiento en píxeles respecto al centro del lienzo. */
    x: number;
    y: number;
    /** 0 al fondo, 1 al frente. Gobierna la opacidad. */
    profundidad: number;
};

/** Cuánto encoge lo que está lejos. Poco: es un objeto pequeño, no un pasillo. */
const PERSPECTIVA = 0.35;

/**
 * La cámara mira desde algo por encima, 17°, y esto no es gusto: es lo que
 * salva la animación.
 *
 * Una balanza es plana. Girándola a cámara horizontal, cada cuarto de vuelta se
 * pone de canto y la figura se derrumba en una raya vertical durante varios
 * segundos: deja de leerse el objeto y lo que queda parece ruido. Con la cámara
 * elevada, de canto se siguen viendo los dos platillos como elipses separadas
 * en profundidad —uno arriba y pequeño, otro abajo y grande— y la balanza no
 * desaparece en ningún ángulo. La esfera de Cloudflare no tiene este problema
 * porque una esfera es igual desde todos los lados; esto no.
 */
const ELEVACION = 0.3;
const COS_ELEVACION = Math.cos(ELEVACION);
const SEN_ELEVACION = Math.sin(ELEVACION);

/**
 * Gira sobre el eje vertical, inclina lo que cuelga del brazo y proyecta.
 *
 * La inclinación pivota en el fulcro, no en el centro del lienzo: si no, el
 * brazo se desplaza en lugar de bascular y la balanza deja de leerse como una
 * balanza.
 */
export function proyectar(p: Punto, giro: number, inclinacion: number, tam: number): Proyeccion {
    let { x, y } = p;

    if (p.pende) {
        const dx = x;
        const dy = y - Y_BRAZO;
        const cos = Math.cos(inclinacion);
        const sen = Math.sin(inclinacion);
        x = dx * cos - dy * sen;
        y = Y_BRAZO + dx * sen + dy * cos;
    }

    const cos = Math.cos(giro);
    const sen = Math.sin(giro);
    const gx = x * cos + p.z * sen;
    const gz = -x * sen + p.z * cos;

    const ey = y * COS_ELEVACION - gz * SEN_ELEVACION;
    const ez = y * SEN_ELEVACION + gz * COS_ELEVACION;

    const escala = 1 / (1 + ez * PERSPECTIVA);

    return {
        x: gx * escala * tam,
        y: -ey * escala * tam,
        profundidad: (1 - ez) / 2,
    };
}

/**
 * Cuánto ocupa la figura, como mucho, en unidades de `tam`.
 *
 * Existe para que el tamaño lo decida la caja y no un número escrito a mano.
 * Antes el envoltorio llevaba anchos y altos en `rem` por punto de ruptura,
 * ajustados a ojo contra el navegador: cada vez que cambiaba el reparto de la
 * rejilla había que volver a medirlo, y entre medias algún platillo se salía
 * por el borde. Midiendo la extensión de verdad, el componente calcula el `tam`
 * que cabe y deja de haber números mágicos.
 *
 * Se recorre una vuelta completa a la inclinación más extrema en los dos
 * sentidos y se guarda el punto que más se aleja del centro, así que el valor
 * vale para **cualquier** fotograma de la animación, no para el de ahora.
 *
 * El resultado se cachea: son ~56.000 proyecciones y no dependen de nada que
 * cambie en caliente.
 */
const extensiones = new Map<number, { x: number; y: number }>();

export function extension(inclinacionMaxima: number): { x: number; y: number } {
    const guardada = extensiones.get(inclinacionMaxima);
    if (guardada) {
        return guardada;
    }

    const puntos = nubeBalanza();
    const PASOS = 48;
    let x = 0;
    let y = 0;

    for (let i = 0; i < PASOS; i++) {
        const giro = (i / PASOS) * Math.PI * 2;

        for (const inclinacion of [-inclinacionMaxima, inclinacionMaxima]) {
            for (const p of puntos) {
                const q = proyectar(p, giro, inclinacion, 1);
                x = Math.max(x, Math.abs(q.x));
                y = Math.max(y, Math.abs(q.y));
            }
        }
    }

    const medida = { x, y };
    extensiones.set(inclinacionMaxima, medida);

    return medida;
}
