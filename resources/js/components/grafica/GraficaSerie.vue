<script setup lang="ts">
import { tono as tonoDe } from '@/lib/tonos';
import { computed } from 'vue';

/**
 * La serie histórica de un indicador: § 4.14 y cláusula 9.1.
 *
 * **Sin librería de gráficas, y esta vez había permiso para meterla.**
 * `CLAUDE.md` dejó la puerta abierta a `d3-scale` y `d3-shape` «el día que haya
 * una serie histórica con eje de tiempo», y este es ese día — pero el eje no es
 * tiempo continuo: son **cubos etiquetados y equiespaciados** («T1 2026», «T2
 * 2026»), que es lo que impone `Periodicidad`. Lo que `d3-scale` compra es
 * elegir ticks legibles sobre un eje continuo, y aquí los ticks vienen escritos
 * de casa. La puerta sigue abierta para el día que haya una serie con fechas
 * irregulares; hoy sería peso muerto en una herramienta que entra en el alcance
 * de su propio SGSI.
 *
 * SVG y no rejilla CSS, al revés que `MatrizRiesgo` y `BarraSegmentada`: aquí sí
 * hay una línea que dibujar entre puntos, que es el caso de `AnilloProgreso`.
 * El `viewBox` escala con la caja y las etiquetas viven **fuera** del SVG, en
 * HTML, para que no encojan con él a 375 px.
 *
 * **La línea de objetivo se dibuja de puntos y no de color**: si sólo la
 * distinguiera el tono, a 375 px y con protanopía sería otra serie más.
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
        alto?: number;
    }>(),
    { alto: 120 },
);

const ANCHO = 600;
const MARGEN = 8;

/**
 * La escala vertical.
 *
 * **Arranca en cero salvo que la serie no lo toque nunca**, y entonces arranca
 * en su mínimo. Una serie de madurez que va de 3,1 a 3,4 dibujada desde cero es
 * una recta plana que no dice nada; y una de porcentajes que empieza en su
 * mínimo exagera una subida de dos puntos hasta que parece un despegue. Se elige
 * lo segundo sólo cuando lo primero no distingue nada, y el eje va rotulado con
 * sus extremos para que no engañe.
 */
const escala = computed(() => {
    const valores = props.puntos.flatMap((p) => (p.objetivo === null ? [p.valor] : [p.valor, p.objetivo]));

    if (valores.length === 0) {
        return { min: 0, max: 1 };
    }

    const max = Math.max(...valores);
    const min = Math.min(...valores);
    const recorrido = max - min;

    // Si todo cabe holgadamente por encima de cero y la variación es pequeña, se
    // recorta el eje; si no, cero manda.
    const desdeCero = min <= 0 || recorrido === 0 || recorrido > min;

    const suelo = desdeCero ? Math.min(0, min) : min - recorrido * 0.25;
    const techo = max === suelo ? suelo + 1 : max + recorrido * 0.1;

    return { min: suelo, max: techo };
});

const posicion = (valor: number): number => {
    const { min, max } = escala.value;
    const alto = props.alto - MARGEN * 2;

    return props.alto - MARGEN - ((valor - min) / (max - min)) * alto;
};

const abscisa = (indice: number): number => {
    if (props.puntos.length <= 1) {
        return ANCHO / 2;
    }

    return MARGEN + (indice * (ANCHO - MARGEN * 2)) / (props.puntos.length - 1);
};

const coordenadas = computed(() =>
    props.puntos.map((punto, indice) => ({
        ...punto,
        x: abscisa(indice),
        y: posicion(punto.valor),
        relleno: tonoDe(punto.tono).relleno,
    })),
);

const linea = computed(() => coordenadas.value.map((c) => `${c.x},${c.y}`).join(' '));

/** La línea de objetivo sólo se dibuja si es la misma en toda la serie. */
const objetivo = computed(() => {
    const objetivos = props.puntos.map((p) => p.objetivo);

    if (objetivos.length === 0 || objetivos.some((o) => o === null || o !== objetivos[0])) {
        return null;
    }

    return { valor: objetivos[0] as number, y: posicion(objetivos[0] as number) };
});

/**
 * Lo que oye quien no ve la gráfica.
 *
 * `DESIGN.md` § 11 no admite que un dato dependa del color, y una polilínea sin
 * esto es un adorno para un lector de pantalla. Va con la primera y la última
 * cifra, que es lo que la gráfica viene a comparar.
 */
const descripcion = computed(() => {
    if (props.puntos.length === 0) {
        return `${props.nombre}: todavía no hay mediciones.`;
    }

    const primero = props.puntos[0];
    const ultimo = props.puntos[props.puntos.length - 1];

    return `${props.nombre}: ${props.puntos.length} medición(es), de ${primero.valorEscrito} en ${primero.etiqueta} a ${ultimo.valorEscrito} en ${ultimo.etiqueta}.`;
});
</script>

<template>
    <div v-if="puntos.length > 0" class="space-y-2">
        <svg
            :viewBox="`0 0 ${ANCHO} ${alto}`"
            class="h-32 w-full overflow-visible"
            preserveAspectRatio="none"
            role="img"
            :aria-label="descripcion"
        >
            <!-- El objetivo, de puntos: si sólo lo distinguiera el color sería
                 otra serie más. -->
            <line
                v-if="objetivo"
                :x1="MARGEN"
                :x2="ANCHO - MARGEN"
                :y1="objetivo.y"
                :y2="objetivo.y"
                class="stroke-muted-foreground/60"
                stroke-width="1"
                stroke-dasharray="4 4"
                vector-effect="non-scaling-stroke"
            />

            <polyline
                :points="linea"
                fill="none"
                class="stroke-primary"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                vector-effect="non-scaling-stroke"
            />
        </svg>

        <!--
            Los puntos y sus etiquetas van en HTML y no dentro del SVG: con
            `preserveAspectRatio="none"` el texto se deformaría con la caja.

            **Y en dos formas, no en una.** Doce columnas a 375 px son doce
            etiquetas de treinta píxeles, donde «T1 2026» no se lee ni truncado;
            meterlas en un carrusel horizontal sería scroll lateral, que § 11 no
            admite. Por debajo de `sm` la serie se lee en vertical, que es como
            se lee una tabla de dos columnas en un móvil.
        -->
        <ol
            class="hidden gap-1 text-xs sm:grid"
            :style="{ gridTemplateColumns: `repeat(${puntos.length}, minmax(0, 1fr))` }"
        >
            <li v-for="punto in coordenadas" :key="punto.periodo" class="min-w-0 text-center">
                <span class="mx-auto mb-1 block size-2 rounded-full" :class="punto.relleno" aria-hidden="true" />
                <span class="cifra block truncate font-medium text-foreground" :title="punto.valorEscrito">
                    {{ punto.valorEscrito }}
                </span>
                <span class="block truncate text-muted-foreground" :title="punto.etiqueta">{{ punto.etiqueta }}</span>
            </li>
        </ol>

        <ol class="divide-y divide-border text-sm sm:hidden">
            <li
                v-for="punto in coordenadas"
                :key="punto.periodo"
                class="flex items-center gap-2 py-1.5"
            >
                <span class="size-2 shrink-0 rounded-full" :class="punto.relleno" aria-hidden="true" />
                <span class="min-w-0 flex-1 truncate text-muted-foreground">{{ punto.etiqueta }}</span>
                <span class="cifra shrink-0 font-medium">{{ punto.valorEscrito }}</span>
            </li>
        </ol>

        <p v-if="objetivo" class="text-xs text-muted-foreground">
            La línea de puntos es el objetivo: {{ puntos[0].objetivoEscrito }}.
        </p>
    </div>
</template>
