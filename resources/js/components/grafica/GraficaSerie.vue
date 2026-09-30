<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { curva, curvaEnPantalla, duracion } from '@/lib/motion';
import { tono as tonoDe } from '@/lib/tonos';
import { useElementSize } from '@vueuse/core';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';

/**
 * La serie histórica de un indicador: § 4.14 y cláusula 9.1.
 *
 * **Sin librería de gráficas, y esta vez había permiso para meterla.**
 * `CLAUDE.md` dejó la puerta abierta a `d3-scale` y `d3-shape` «el día que haya
 * una serie histórica con eje de tiempo», y este es ese día — pero el eje no es
 * tiempo continuo: son **cubos etiquetados y equiespaciados** («T1 2026», «T2
 * 2026»), que es lo que impone `Periodicidad`. Lo que `d3-scale` compra es
 * elegir ticks legibles sobre un eje continuo, y aquí los ticks vienen escritos
 * de casa.
 *
 * **Se dibuja en píxeles reales, no en un `viewBox` estirado.** Antes el SVG
 * medía 600 de ancho con `preserveAspectRatio="none"`, y eso obligaba a sacar
 * todo texto fuera y a `vector-effect="non-scaling-stroke"`, con el que la línea
 * no se puede trazar: `pathLength` y el desplazamiento del trazo se calculan en
 * el espacio estirado. Midiendo la caja, las cifras van junto a su punto y la
 * línea se dibuja de izquierda a derecha.
 *
 * **La línea de objetivo se dibuja de puntos y no de color**, y los puntos
 * fuera de objetivo van **huecos**: si sólo los distinguiera el tono, con
 * protanopía serían la misma serie.
 */
export interface Punto {
    periodo: string;
    etiqueta: string;
    valor: number;
    valorEscrito: string;
    objetivo: number | null;
    objetivoEscrito: string | null;
    fraccion: string | null;
    cumplimiento: string;
    cumplimientoEtiqueta: string;
    tono: string;
}

const props = withDefaults(
    defineProps<{
        puntos: Punto[];
        /** Qué se mide, para la descripción que lee un lector de pantalla. */
        nombre: string;
        /**
         * El techo natural de la escala, si lo tiene: 100 en un porcentaje.
         *
         * Sin él la escala sale de los datos, y un 63 % dibujado contra un techo
         * de 67 parece casi lleno.
         */
        techo?: number | null;
        /** El periodo que cerró sin medir, que se pinta como hueco al final. */
        pendiente?: string | null;
        /** El periodo que la lista de mediciones tiene bajo el puntero. */
        resaltado?: string | null;
        alto?: number;
    }>(),
    { techo: null, pendiente: null, resaltado: null, alto: 220 },
);

const emit = defineEmits<{
    /** El periodo que la gráfica tiene bajo el puntero o el foco. */
    enfocar: [etiqueta: string | null];
    /** Un periodo nuevo acaba de entrar en la serie. */
    sellado: [etiqueta: string];
}>();

const { reducido } = useMovimientoReducido();

const contenedor = useTemplateRef<HTMLElement>('contenedor');
const { width } = useElementSize(contenedor);
const ancho = computed(() => Math.max(width.value, 240));

/** El hueco de los rótulos del eje vertical y el aire hasta el primer punto. */
const EJE = 36;
const AIRE = 24;
const ARRIBA = 28;
const ABAJO = 8;

const huecos = computed(() => props.puntos.length + (props.pendiente ? 1 : 0));

/**
 * La escala vertical.
 *
 * **Con techo, de cero al techo.** Sin él, arranca en cero salvo que la serie no
 * lo toque nunca, y entonces en su mínimo: una serie de madurez que va de 3,1 a
 * 3,4 dibujada desde cero es una recta plana que no dice nada. El eje va rotulado
 * con sus extremos para que el recorte no engañe.
 */
const escala = computed(() => {
    const valores = props.puntos.flatMap((p) => (p.objetivo === null ? [p.valor] : [p.valor, p.objetivo]));

    if (props.techo !== null) {
        return { min: 0, max: Math.max(props.techo, ...valores) };
    }

    if (valores.length === 0) {
        return { min: 0, max: 1 };
    }

    const max = Math.max(...valores);
    const min = Math.min(...valores);
    const recorrido = max - min;
    const desdeCero = min <= 0 || recorrido === 0 || recorrido > min;
    const suelo = desdeCero ? Math.min(0, min) : min - recorrido * 0.25;
    const techo = max === suelo ? suelo + 1 : max + recorrido * 0.1;

    return { min: suelo, max: techo };
});

const ordenada = (valor: number): number => {
    const { min, max } = escala.value;
    const util = props.alto - ARRIBA - ABAJO;

    return props.alto - ABAJO - ((valor - min) / (max - min)) * util;
};

const abscisa = (indice: number): number => {
    const inicio = EJE + AIRE;
    const fin = ancho.value - AIRE;

    return huecos.value <= 1 ? (inicio + fin) / 2 : inicio + (indice * (fin - inicio)) / (huecos.value - 1);
};

const formato = new Intl.NumberFormat('es-ES', { maximumFractionDigits: 1 });

/** Cinco marcas con techo; sin él, los dos extremos y el medio. */
const marcas = computed(() => {
    const { min, max } = escala.value;
    const valores = props.techo !== null ? [0, 0.25, 0.5, 0.75, 1].map((f) => max * f) : [min, (min + max) / 2, max];

    return valores.map((valor) => ({ valor, y: ordenada(valor), texto: formato.format(valor) }));
});

const coordenadas = computed(() =>
    props.puntos.map((punto, indice) => ({
        ...punto,
        x: abscisa(indice),
        y: ordenada(punto.valor),
        tinta: tonoDe(punto.tono).texto ?? 'text-muted-foreground',
        relleno: tonoDe(punto.tono).relleno,
        alcanzado: punto.cumplimiento === 'en_objetivo',
    })),
);

const trazado = computed(() =>
    coordenadas.value.map((c, i) => `${i === 0 ? 'M' : 'L'}${c.x.toFixed(1)} ${c.y.toFixed(1)}`).join(' '),
);

/** Cuánto de la línea queda antes de cada punto, de 0 a 1. */
const recorridos = computed(() => {
    const tramos = coordenadas.value.map((c, i) => {
        const previo = coordenadas.value[i - 1];

        return previo ? Math.hypot(c.x - previo.x, c.y - previo.y) : 0;
    });
    const total = tramos.reduce((suma, tramo) => suma + tramo, 0) || 1;
    let acumulado = 0;

    return tramos.map((tramo) => (acumulado += tramo) / total);
});

/** La línea de objetivo sólo se dibuja si es la misma en toda la serie. */
const objetivo = computed(() => {
    const objetivos = props.puntos.map((p) => p.objetivo);

    if (objetivos.length === 0 || objetivos.some((o) => o === null || o !== objetivos[0])) {
        return null;
    }

    return { y: ordenada(objetivos[0] as number), escrito: props.puntos[0].objetivoEscrito };
});

const hueco = computed(() => (props.pendiente ? { x: abscisa(props.puntos.length), y: ordenada(escala.value.min) } : null));

/** Las cifras junto al punto sólo si caben; si no, se leen en el detalle. */
const rotulosCaben = computed(() => ancho.value / Math.max(huecos.value, 1) >= 52);

const enfocado = ref<number | null>(null);
const activo = computed(() => {
    const indice = enfocado.value ?? coordenadas.value.findIndex((c) => c.etiqueta === props.resaltado);

    return indice === -1 || indice === null ? null : (coordenadas.value[indice] ?? null);
});

const enfocar = (indice: number | null): void => {
    enfocado.value = indice;
    emit('enfocar', indice === null ? null : (coordenadas.value[indice]?.etiqueta ?? null));
};

/** El detalle se queda dentro de la caja aunque el punto esté en un extremo. */
const ANCHO_DETALLE = 192;
const posicionDetalle = computed(() =>
    activo.value
        ? {
              left: `${Math.min(Math.max(activo.value.x - ANCHO_DETALLE / 2, 0), ancho.value - ANCHO_DETALLE)}px`,
              top: `${Math.max(activo.value.y - 112, -40)}px`,
          }
        : {},
);

/**
 * Lo que oye quien no ve la gráfica.
 *
 * `DESIGN.md` § 11 no admite que un dato dependa del color, y una polilínea sin
 * esto es un adorno para un lector de pantalla.
 */
const descripcion = computed(() => {
    if (props.puntos.length === 0) {
        return `${props.nombre}: todavía no hay mediciones.`;
    }

    const primero = props.puntos[0];
    const ultimo = props.puntos[props.puntos.length - 1];
    const pendiente = props.pendiente ? ` ${props.pendiente} sin medir.` : '';

    return `${props.nombre}: ${props.puntos.length} medición(es), de ${primero.valorEscrito} en ${primero.etiqueta} a ${ultimo.valorEscrito} en ${ultimo.etiqueta}.${pendiente}`;
});

/*
 * ─── El movimiento ────────────────────────────────────────────────────────
 *
 * Es el quinto momento de DESIGN.md §10, y son dos gestos:
 *
 * 1. **Al abrir la ficha, la serie se traza** de izquierda a derecha y cada
 *    punto aparece cuando la línea llega a él. Una vez por visita: una recarga
 *    parcial no la vuelve a trazar.
 * 2. **Al sellar un periodo, el tramo nuevo se alarga** hasta el punto, que
 *    aterriza; si ha alcanzado el objetivo deja una onda, una vez. Es lo que
 *    confirma que la cifra entró en la serie, y pasa una vez por periodo.
 *
 * Va con la API de animaciones web y no con CSS porque los retrasos salen de la
 * geometría —cuánto de línea hay antes de cada punto— y porque el `@media`
 * global de `app.css` no alcanza a `element.animate()`: con movimiento reducido
 * se decide aquí, y lo que queda es un fundido corto del punto nuevo.
 */

const linea = useTemplateRef<SVGPathElement>('linea');
const guias = useTemplateRef<SVGGElement>('guias');
/*
 * Los grupos de cada punto, para animarlos. **Un array normal y no un `ref`**:
 * la ref en función del `v-for` se llama en cada render —con `null` y luego con
 * el elemento—, y escribir ahí en algo reactivo pide otro render, que vuelve a
 * llamarla. Colgaba la pestaña al pasar el ratón.
 */
const nodos: (Element | null)[] = [];
const onda = ref<string | null>(null);
const circuloOnda = useTemplateRef<SVGCircleElement>('circuloOnda');
const puntoOnda = computed(() => coordenadas.value.find((c) => c.etiqueta === onda.value) ?? null);

const bezier = (a: number, b: number, s: number): number => 3 * a * s * (1 - s) ** 2 + 3 * b * s ** 2 * (1 - s) + s ** 3;

/**
 * En qué instante de la curva la línea ha recorrido esa fracción.
 *
 * `--curva-en-pantalla` acelera y frena, así que la mitad de la línea no está a
 * mitad de tiempo exacta; sin esto los puntos del centro aparecen antes de que
 * la línea llegue.
 */
const instanteDe = (fraccion: number): number => {
    const [x1, y1, x2, y2] = curvaEnPantalla;
    let bajo = 0;
    let alto = 1;

    for (let paso = 0; paso < 24; paso++) {
        const medio = (bajo + alto) / 2;

        if (bezier(y1, y2, medio) < fraccion) {
            bajo = medio;
        } else {
            alto = medio;
        }
    }

    return bezier(x1, x2, (bajo + alto) / 2);
};

const easing = (c: readonly number[]): string => `cubic-bezier(${c.join(',')})`;
const puedeMoverse = (): boolean => !reducido.value && document.visibilityState === 'visible';

const aparecer = (elemento: Element | null | undefined, retraso: number, duracionMs = duracion.normal * 1000): void => {
    elemento?.animate(
        [
            { opacity: 0, transform: 'scale(0.6)' },
            { opacity: 1, transform: 'scale(1)' },
        ],
        { duration: duracionMs, delay: retraso, easing: easing(curva), fill: 'backwards' },
    );
};

const trazar = (): void => {
    if (!puedeMoverse() || coordenadas.value.length === 0) {
        return;
    }

    const total = duracion.trazo * 1000;

    guias.value?.animate([{ opacity: 0 }, { opacity: 1 }], { duration: duracion.normal * 1000, easing: easing(curva) });
    linea.value?.animate([{ strokeDashoffset: 1 }, { strokeDashoffset: 0 }], {
        duration: total,
        easing: easing(curvaEnPantalla),
    });
    recorridos.value.forEach((fraccion, i) => aparecer(nodos[i], instanteDe(fraccion) * total));
};

const sellar = (): void => {
    const ultimo = coordenadas.value[coordenadas.value.length - 1];
    emit('sellado', ultimo.etiqueta);

    if (reducido.value) {
        nodos[coordenadas.value.length - 1]?.animate([{ opacity: 0 }, { opacity: 1 }], {
            duration: duracion.rapida * 1000,
        });

        return;
    }

    const total = duracion.sello * 1000;
    const antes = recorridos.value[recorridos.value.length - 2] ?? 0;

    linea.value?.animate([{ strokeDashoffset: 1 - antes }, { strokeDashoffset: 0 }], {
        duration: total,
        easing: easing(curvaEnPantalla),
    });
    aparecer(nodos[coordenadas.value.length - 1], total * 0.92);

    if (!ultimo.alcanzado) {
        return;
    }

    onda.value = ultimo.etiqueta;
    void nextTick(() => {
        circuloOnda.value
            ?.animate(
                [
                    { opacity: 0.5, transform: 'scale(1)' },
                    { opacity: 0, transform: 'scale(3.2)' },
                ],
                { duration: 720, delay: total, easing: easing(curva), fill: 'backwards' },
            )
            .finished.then(() => (onda.value = null))
            .catch(() => (onda.value = null));
    });
};

/*
 * La primera traza espera a que la caja tenga ancho: antes de que el
 * `ResizeObserver` conteste, la geometría es la de reserva y la línea se
 * trazaría en un sitio para saltar a otro.
 */
const detenerEntrada = watch(width, (medido) => {
    if (medido > 0) {
        detenerEntrada();
        void nextTick(trazar);
    }
});

/*
 * Un periodo nuevo es uno que no estaba y que va después del último. Corregir
 * una cifra, borrar una fila o filtrar no cuentan: sólo se celebra lo que entra.
 */
watch(
    () => props.puntos.map((p) => p.periodo),
    (ahora, antes) => {
        const ultimo = ahora[ahora.length - 1];
        const previo = antes[antes.length - 1];

        if (ultimo && !antes.includes(ultimo) && (!previo || ultimo > previo)) {
            void nextTick(sellar);
        }
    },
);
</script>

<template>
    <div v-if="puntos.length > 0" class="space-y-3">
        <div ref="contenedor" class="relative" @mouseleave="enfocar(null)">
            <svg :width="ancho" :height="alto" class="block overflow-visible" role="img" :aria-label="descripcion">
                <g ref="guias">
                    <g v-for="marca in marcas" :key="marca.valor">
                        <line :x1="EJE + 8" :x2="ancho" :y1="marca.y" :y2="marca.y" class="stroke-border" stroke-width="1" />
                        <text :x="EJE" :y="marca.y + 4" text-anchor="end" class="cifra fill-muted-foreground text-xs">
                            {{ marca.texto }}
                        </text>
                    </g>

                    <!-- El objetivo, de puntos: si sólo lo distinguiera el color sería
                         otra serie más. -->
                    <template v-if="objetivo">
                        <line
                            :x1="EJE + 8"
                            :x2="ancho"
                            :y1="objetivo.y"
                            :y2="objetivo.y"
                            class="stroke-muted-foreground/60"
                            stroke-width="1.5"
                            stroke-dasharray="4 4"
                        />
                        <text :x="EJE + 12" :y="objetivo.y - 7" class="fill-muted-foreground text-xs font-medium">
                            Objetivo {{ objetivo.escrito }}
                        </text>
                    </template>
                </g>

                <path
                    ref="linea"
                    :d="trazado"
                    pathLength="1"
                    stroke-dasharray="1"
                    fill="none"
                    class="stroke-primary"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <!-- El periodo que cerró sin medir: un hueco en rojo donde tendría
                     que estar su punto. Es el único rojo del módulo. -->
                <Transition leave-active-class="transition-opacity duration-(--duracion-salida)" leave-to-class="opacity-0">
                    <circle
                        v-if="hueco"
                        :cx="hueco.x"
                        :cy="hueco.y"
                        r="6"
                        class="fill-card stroke-destructive"
                        stroke-width="1.5"
                        stroke-dasharray="2.5 2.5"
                    />
                </Transition>

                <circle
                    v-if="puntoOnda"
                    ref="circuloOnda"
                    :cx="puntoOnda.x"
                    :cy="puntoOnda.y"
                    r="6"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    :class="puntoOnda.tinta"
                    style="transform-box: fill-box; transform-origin: center"
                />

                <g
                    v-for="(punto, i) in coordenadas"
                    :key="punto.periodo"
                    :ref="(el) => (nodos[i] = el as Element | null)"
                    style="transform-box: fill-box; transform-origin: center"
                >
                    <circle
                        :cx="punto.x"
                        :cy="punto.y"
                        r="5.5"
                        stroke="currentColor"
                        :fill="punto.alcanzado ? 'currentColor' : undefined"
                        :stroke-width="punto.alcanzado ? 0 : 2.5"
                        :class="[punto.tinta, punto.alcanzado ? '' : 'fill-card', { 'scale-125': activo?.periodo === punto.periodo }]"
                        class="transition-transform"
                        style="transform-box: fill-box; transform-origin: center"
                    />
                    <text
                        v-if="rotulosCaben"
                        :x="punto.x"
                        :y="punto.y - 14"
                        text-anchor="middle"
                        class="cifra fill-foreground text-xs font-semibold"
                    >
                        {{ punto.valorEscrito }}
                    </text>
                </g>
            </svg>

            <!--
                Un botón por punto, encima del SVG: lo que se señala con el ratón
                tiene que poder alcanzarse con el teclado, y un `<circle>` no
                recibe foco. Llevan su lectura entera en `aria-label`.
            -->
            <button
                v-for="(punto, i) in coordenadas"
                :key="`nodo-${punto.periodo}`"
                type="button"
                class="absolute size-8 -translate-x-1/2 -translate-y-1/2 rounded-full focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                :style="{ left: `${punto.x}px`, top: `${punto.y}px` }"
                :aria-label="`${punto.etiqueta}: ${punto.valorEscrito}, ${punto.cumplimientoEtiqueta.toLowerCase()}`"
                @mouseenter="enfocar(i)"
                @focus="enfocar(i)"
                @blur="enfocar(null)"
            />

            <div
                v-if="activo"
                class="pointer-events-none absolute z-(--z-pegajoso) rounded-lg border border-border bg-popover px-3 py-2.5 text-popover-foreground shadow-sombra-2"
                :style="{ ...posicionDetalle, width: `${ANCHO_DETALLE}px` }"
                aria-hidden="true"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm font-semibold">{{ activo.etiqueta }}</span>
                    <span v-if="activo.fraccion" class="cifra text-xs text-muted-foreground">{{ activo.fraccion }}</span>
                </div>
                <p class="cifra text-xl font-bold">{{ activo.valorEscrito }}</p>
                <p v-if="activo.objetivoEscrito" class="text-xs text-muted-foreground">
                    Objetivo aplicado <span class="cifra">{{ activo.objetivoEscrito }}</span>
                </p>
                <p class="mt-1 text-xs font-semibold" :class="activo.tinta">{{ activo.cumplimientoEtiqueta }}</p>
            </div>

            <!--
                Las etiquetas del eje en HTML y a partir de `sm`. Doce columnas a
                375 px son doce etiquetas de treinta píxeles, donde «T1 2026» no se
                lee ni truncado: por debajo de `sm` la serie se lee en vertical.
            -->
            <ol class="relative hidden h-5 text-xs text-muted-foreground sm:block" aria-hidden="true">
                <li
                    v-for="punto in coordenadas"
                    :key="punto.periodo"
                    class="absolute w-24 -translate-x-1/2 truncate text-center"
                    :class="{ 'font-medium text-foreground': activo?.periodo === punto.periodo }"
                    :style="{ left: `${punto.x}px` }"
                >
                    {{ punto.etiqueta }}
                </li>
                <li
                    v-if="hueco && pendiente"
                    class="absolute w-36 -translate-x-1/2 truncate text-center font-medium text-destructive"
                    :style="{ left: `${hueco.x}px` }"
                >
                    {{ pendiente }} · sin medir
                </li>
            </ol>
        </div>

        <ol class="divide-y divide-border text-sm sm:hidden">
            <li v-if="pendiente" class="flex items-center gap-2 py-1.5 text-destructive">
                <span class="size-2 shrink-0 rounded-full border border-dashed border-destructive" aria-hidden="true" />
                <span class="min-w-0 flex-1 truncate">{{ pendiente }}</span>
                <span class="shrink-0 font-medium">Sin medir</span>
            </li>
            <li v-for="punto in [...coordenadas].reverse()" :key="punto.periodo" class="flex items-center gap-2 py-1.5">
                <span
                    class="size-2.5 shrink-0 rounded-full border-2 border-current"
                    :class="[punto.tinta, punto.alcanzado ? punto.relleno : '']"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 truncate text-muted-foreground">{{ punto.etiqueta }}</span>
                <span class="text-xs" :class="punto.tinta">{{ punto.cumplimientoEtiqueta }}</span>
                <span class="cifra w-14 shrink-0 text-right font-medium">{{ punto.valorEscrito }}</span>
            </li>
        </ol>
    </div>
</template>
