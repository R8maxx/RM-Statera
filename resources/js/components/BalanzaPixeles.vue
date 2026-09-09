<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { extension, nubeBalanza, proyectar, type Punto } from '@/lib/balanza';
import { ambiente } from '@/lib/motion';
import { useDocumentVisibility, useMediaQuery } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * La balanza de la marca, girando como matriz de píxeles.
 *
 * Canvas 2D a mano, sin librería. No hace falta un motor 3D para setecientos
 * puntos, y meter uno en la pantalla de acceso de una herramienta que entra en
 * el alcance de su propio SGSI es peso muerto: cada dependencia del bundle es
 * superficie que hay que mantener y justificar en una revisión.
 *
 * El truco del pixelado no está en dibujar cuadrados, sino en volcarlos a una
 * retícula fija: cada punto proyectado cae en una celda, la celda se queda con
 * el punto más cercano y se pinta entera. Por eso la figura se recompone al
 * girar en lugar de deslizarse, que es lo que la hace parecer una pantalla de
 * puntos y no un objeto con textura.
 *
 * **El tamaño lo decide la caja, no el componente.** Se estira para llenar el
 * hueco que le den, así que ponerlo dentro de un `flex-1` basta para que se
 * adapte a la altura de la ventana sin un solo número por punto de ruptura.
 * `lib/balanza.ts` mide cuánto ocupa la figura en el fotograma más ancho de
 * toda la animación, y de ahí sale el `tam` que cabe: por construcción no hay
 * ángulo en el que un platillo se salga del lienzo.
 *
 * La geometría vive en `lib/balanza.ts` y los tiempos en `lib/motion.ts`. Aquí
 * sólo hay proyección, retícula y las guardas de apagado.
 */

/** Lado de la celda, en píxeles CSS. Por debajo de 4 se pierde el pixelado. */
const CELDA = 5;

/** Aire alrededor de la figura. A 1 tocaría el borde del lienzo. */
const RELLENO = 0.97;

/**
 * Por debajo de esto la figura deja de leerse como una balanza y pasa a ser un
 * borrón de puntos, así que no se pinta. Pasa en ventanas muy bajas, donde la
 * copia del panel se come casi todo el alto disponible.
 */
const TAM_MINIMO = 62;

const lienzo = ref<HTMLCanvasElement | null>(null);
const puntos = nubeBalanza();

const { reducido } = useMovimientoReducido();
const visibilidad = useDocumentVisibility();
/** El panel es `hidden lg:flex`: por debajo de esto no hay nada que pintar. */
const anchoSuficiente = useMediaQuery('(min-width: 1024px)');

const debeAnimar = computed(
    () => anchoSuficiente.value && !reducido.value && visibilidad.value !== 'hidden',
);

let ctx: CanvasRenderingContext2D | null = null;
let observador: ResizeObserver | null = null;
let fotograma = 0;
/**
 * Los tres relojes se acumulan fotograma a fotograma en vez de derivarse del
 * reloj de pared. Es lo que hace que volver de una pestaña en segundo plano no
 * dé un salto: si el ángulo saliera de `performance.now()`, cinco minutos
 * parado y la balanza reaparece girada media vuelta y con el brazo en otra
 * posición, que es justo lo que se nota.
 */
let giro = 0;
let fase = 0;
let avance = 0;
let ultimo = 0;
let pintado = 0;

/** Ancho y alto en píxeles CSS, y el radio del objeto dentro de ellos. */
let ancho = 0;
let alto = 0;
let tam = 0;

/** Celdas encendidas: profundidad de la más cercana, y si esa era de acento. */
let columnas = 0;
let filas = 0;
let profundidades = new Float32Array(0);
let acentos = new Uint8Array(0);

/**
 * Los colores salen de los tokens, no del fichero: ningún hex suelto, y el
 * canvas hereda cualquier retoque de la paleta. No hace falta reaccionar al
 * cambio de tema porque el panel es `bg-marca-800` en claro y en oscuro.
 */
const color = { cerca: '', lejos: '', acento: '' };

function leerColores(): void {
    const estilos = getComputedStyle(document.documentElement);
    color.cerca = estilos.getPropertyValue('--marca-100').trim();
    color.lejos = estilos.getPropertyValue('--marca-500').trim();
    color.acento = estilos.getPropertyValue('--violeta-300').trim();
}

function medir(): void {
    const canvas = lienzo.value;
    if (!canvas || !ctx) {
        return;
    }

    const caja = canvas.getBoundingClientRect();
    if (caja.width === 0 || caja.height === 0) {
        return;
    }

    // Por encima de 2 la nitidez ya no se nota y el coste se multiplica.
    const dpr = Math.min(window.devicePixelRatio || 1, 2);

    ancho = caja.width;
    alto = caja.height;

    // El eje que primero se queda corto es el que manda. Con la extensión real
    // de la figura —y no un `min(ancho, alto)` a ojo— una caja apaisada usa
    // todo el ancho en lugar de desperdiciar la mitad del alto.
    const figura = extension(ambiente.amplitud);
    tam = Math.min(ancho / (2 * figura.x), alto / (2 * figura.y)) * RELLENO;

    canvas.width = Math.round(ancho * dpr);
    canvas.height = Math.round(alto * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    columnas = Math.ceil(ancho / CELDA);
    filas = Math.ceil(alto / CELDA);
    profundidades = new Float32Array(columnas * filas);
    acentos = new Uint8Array(columnas * filas);
}

/** Frenada larga, la misma sensación que la curva de `motion.ts`. */
function suavizar(t: number): number {
    return 1 - Math.pow(1 - t, 3);
}

/**
 * La entrada sube de abajo arriba: primero la base, al final los platillos.
 * Devuelve el factor de opacidad de un punto, entre 0 y 1.
 */
function entrada(p: Punto, avance: number): number {
    if (avance >= 1) {
        return 1;
    }

    const umbral = Math.min(Math.max((p.y + 0.7) / 1.4, 0), 1) * 0.7;

    return Math.min(Math.max((avance - umbral) / 0.3, 0), 1);
}

/**
 * Dónde cae el centro de la figura en un fotograma concreto, en píxeles.
 *
 * La nube se dibuja centrada en el origen del objeto, que es lo correcto
 * mientras gira: encuadrar cada fotograma por su caja haría que la figura
 * nadara por el lienzo en lugar de girar sobre su eje. Pero en un fotograma
 * quieto la perspectiva deja la figura descentrada —hasta un 12 % del ancho—,
 * y ese fotograma es justo el que ven quienes piden movimiento reducido. Así
 * que el estático, y sólo el estático, se recoloca por su caja.
 */
function centroDeLaFigura(inclinacion: number): { x: number; y: number } {
    let minX = Infinity;
    let maxX = -Infinity;
    let minY = Infinity;
    let maxY = -Infinity;

    for (const p of puntos) {
        const q = proyectar(p, giro, inclinacion, tam);
        minX = Math.min(minX, q.x);
        maxX = Math.max(maxX, q.x);
        minY = Math.min(minY, q.y);
        maxY = Math.max(maxY, q.y);
    }

    return { x: (minX + maxX) / 2, y: (minY + maxY) / 2 };
}

function pintar(inclinacion: number, avance: number, desplazamiento = { x: 0, y: 0 }): void {
    if (!ctx || columnas === 0) {
        return;
    }

    if (tam < TAM_MINIMO) {
        ctx.clearRect(0, 0, ancho, alto);

        return;
    }

    profundidades.fill(0);
    acentos.fill(0);

    const cx = ancho / 2;
    const cy = alto / 2;

    for (const p of puntos) {
        const opacidad = entrada(p, avance);
        if (opacidad === 0) {
            continue;
        }

        const q = proyectar(p, giro, inclinacion, tam);
        const columna = Math.floor((cx + q.x - desplazamiento.x) / CELDA);
        const fila = Math.floor((cy + q.y - desplazamiento.y) / CELDA);

        if (columna < 0 || columna >= columnas || fila < 0 || fila >= filas) {
            continue;
        }

        // Sólo la profundidad, atenuada por la entrada: así la celda se queda
        // con el punto que de verdad tapa a los demás.
        const valor = q.profundidad * opacidad;
        const celda = fila * columnas + columna;

        if (valor > profundidades[celda]) {
            profundidades[celda] = valor;
            acentos[celda] = p.acento ? 1 : 0;
        }
    }

    ctx.clearRect(0, 0, ancho, alto);

    for (let celda = 0; celda < profundidades.length; celda++) {
        const profundidad = profundidades[celda];
        if (profundidad === 0) {
            continue;
        }

        ctx.globalAlpha = 0.16 + profundidad * 0.84;
        ctx.fillStyle = acentos[celda] ? color.acento : profundidad > 0.5 ? color.cerca : color.lejos;
        // Un píxel menos que la celda: el hueco es lo que hace la retícula.
        ctx.fillRect((celda % columnas) * CELDA, Math.floor(celda / columnas) * CELDA, CELDA - 1, CELDA - 1);
    }

    ctx.globalAlpha = 1;
}

/** Un solo fotograma, quieto y en un ángulo que enseñe los dos platillos. */
function pintarEstatico(): void {
    giro = 0.6;
    fase = Math.asin(0.6);
    avance = 1;

    const inclinacion = ambiente.amplitud * 0.6;
    pintar(inclinacion, 1, centroDeLaFigura(inclinacion));
}

function bucle(ahora: number): void {
    fotograma = requestAnimationFrame(bucle);

    if (ultimo === 0) {
        ultimo = ahora;
    }

    if (ahora - pintado < 1000 / ambiente.fps) {
        return;
    }

    // El salto se acota: si el navegador entrega un delta enorme tras una
    // pausa, avanzar medio segundo y seguir es preferible a dar un tirón.
    const delta = Math.min((ahora - ultimo) / 1000, 0.5);
    ultimo = ahora;
    pintado = ahora;

    giro = (giro + (delta * Math.PI * 2) / ambiente.vuelta) % (Math.PI * 2);
    fase = (fase + (delta * Math.PI * 2) / ambiente.balanceo) % (Math.PI * 2);
    avance = Math.min(avance + delta / ambiente.entrada, 1);

    pintar(Math.sin(fase) * ambiente.amplitud, suavizar(avance));
}

function arrancar(): void {
    if (fotograma !== 0) {
        return;
    }

    // A cero para que el primer fotograma calcule un delta de cero y no herede
    // el tiempo que la animación ha estado parada.
    ultimo = 0;
    pintado = 0;
    fotograma = requestAnimationFrame(bucle);
}

function parar(): void {
    if (fotograma !== 0) {
        cancelAnimationFrame(fotograma);
        fotograma = 0;
    }
}

onMounted(() => {
    const canvas = lienzo.value;
    if (!canvas) {
        return;
    }

    ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }

    leerColores();
    medir();

    observador = new ResizeObserver(() => {
        medir();

        if (!debeAnimar.value) {
            pintarEstatico();
        }
    });
    observador.observe(canvas);

    if (debeAnimar.value) {
        arrancar();
    } else {
        pintarEstatico();
    }
});

watch(debeAnimar, (animar) => {
    if (animar) {
        arrancar();
    } else {
        parar();
        pintarEstatico();
    }
});

onBeforeUnmount(() => {
    parar();
    observador?.disconnect();
    observador = null;
    ctx = null;
});
</script>

<template>
    <!--
        Decorativo: el nombre accesible de la marca ya lo da `Logotipo`, y un
        lector de pantalla no gana nada anunciando una animación de fondo.
    -->
    <canvas ref="lienzo" class="size-full" aria-hidden="true" role="presentation" />
</template>
