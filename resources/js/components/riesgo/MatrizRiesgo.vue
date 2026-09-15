<script setup lang="ts">
import { tono } from '@/lib/tonos';
import { computed } from 'vue';

/**
 * La cuadrícula probabilidad × impacto, coloreada por banda.
 *
 * Es lo que hace entender qué significan los umbrales. Un «12» solo no dice nada;
 * «12, por encima de vuestro umbral de aceptación, que está en 8» sí, y verlo en
 * la cuadrícula lo dice sin leer.
 *
 * **Rejilla CSS y no SVG, aunque sea una gráfica.** DESIGN.md §9 admite las dos
 * —«SVG y CSS a mano sobre los tokens»— y aquí gana CSS por una razón concreta:
 * la matriz tiene que caber a 375 px sin scroll horizontal, y una rejilla se
 * encoge con su contenedor mientras que un `viewBox` obliga a escalar el texto
 * hasta hacerlo ilegible. Es el mismo criterio que sigue `BarraSegmentada`, que
 * también es divs; `AnilloProgreso` es SVG porque ahí hay un arco que dibujar.
 *
 * **Sin librería**, como todo lo demás: la puerta abierta a `d3` era para una
 * serie histórica con eje de tiempo, y una matriz de 5×5 no lo es.
 *
 * El color de cada celda sale del tono que **declara el dominio** en
 * `CalculoRiesgo::bandas()`, no de una tabla aquí: el mismo tono significa cosas
 * distintas según el módulo, así que el cliente no lo deduce.
 */

interface Banda {
    desde: number;
    hasta: number;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface Escalon {
    valor: number;
    etiqueta: string;
    descripcion: string | null;
}

/** Una celda a señalar: el riesgo intrínseco y el residual de una ficha. */
interface Marca {
    probabilidad: number;
    impacto: number;
    etiqueta: string;
}

const props = withDefaults(
    defineProps<{
        /** Indexadas por nivel, tal como las devuelve `CalculoRiesgo::bandas()`. */
        bandas: Record<string, Banda>;
        probabilidad: Escalon[];
        impacto: Escalon[];
        marcadas?: Marca[];
    }>(),
    { marcadas: () => [] },
);

const bandasOrdenadas = computed(() => Object.values(props.bandas));

/**
 * Las filas van de mayor a menor probabilidad: arriba lo que más pasa, que es
 * como se lee un mapa de calor y como lo espera quien ya ha visto uno.
 */
const filas = computed(() => [...props.probabilidad].sort((a, b) => b.valor - a.valor));

function bandaDe(valor: number): Banda | null {
    return bandasOrdenadas.value.find((banda) => valor >= banda.desde && valor <= banda.hasta) ?? null;
}

function claseDe(valor: number): string {
    const banda = bandaDe(valor);

    // `lib/tonos.ts` trae las clases escritas enteras: aquí se leen, nunca se
    // componen. `bg-estado-${x}` no lo genera Tailwind.
    return banda ? tono(banda.tono).relleno : 'bg-muted';
}

function marcaDe(probabilidad: number, impacto: number): Marca | undefined {
    return props.marcadas.find((marca) => marca.probabilidad === probabilidad && marca.impacto === impacto);
}

function titulo(probabilidad: Escalon, impacto: Escalon): string {
    const valor = probabilidad.valor * impacto.valor;
    const banda = bandaDe(valor);

    return `Probabilidad ${probabilidad.etiqueta.toLowerCase()} × impacto ${impacto.etiqueta.toLowerCase()} = ${valor}${banda ? ` · ${banda.etiqueta}` : ''}`;
}

/**
 * Lo que oye quien no ve la cuadrícula.
 *
 * Se deletrea la banda, no la celda a celda: cincuenta y cinco coordenadas no son
 * una descripción, son una lista. Lo que importa es de qué a qué va cada nivel.
 */
const descripcion = computed(
    () =>
        'Matriz de riesgo. '
        + bandasOrdenadas.value
            .map((banda) =>
                banda.desde === banda.hasta
                    ? `${banda.etiqueta}: ${banda.desde}`
                    : `${banda.etiqueta}: de ${banda.desde} a ${banda.hasta}`,
            )
            .join('. ')
        + '.',
);
</script>

<template>
    <div class="space-y-3">
        <div class="flex gap-2">
            <!--
                El rótulo del eje vertical, girado. `writing-mode` y no un `rotate`
                con posicionamiento absoluto: así ocupa su ancho de verdad y la
                rejilla de al lado se encoge sola cuando la pantalla es estrecha.
            -->
            <p
                class="shrink-0 text-xs text-muted-foreground [writing-mode:vertical-rl] [transform:rotate(180deg)]"
            >
                Probabilidad
            </p>

            <div class="min-w-0 flex-1">
                <!--
                    Los rótulos del eje viven DENTRO de la misma rejilla que las
                    celdas, en una primera columna propia.

                    Antes eran una columna flex aparte, con un paso de 16 px
                    (`aspect-square w-4` + `gap-0.5`) mientras las celdas iban a
                    `1fr`, es decir ~26 px en cuanto la tarjeta era más ancha que
                    5×16. Las dos columnas divergían: el «1» caía en la tercera
                    fila y las dos últimas —donde están las celdas señaladas— se
                    quedaban sin rótulo. Un mapa de calor cuyo eje no se puede
                    leer no es un mapa de calor, y aquí es el gráfico sobre el que
                    alguien firma que la organización convive con una exposición.

                    Compartiendo `grid-template-rows` no pueden desalinearse, y
                    el eje de abajo usa las mismas columnas por lo mismo.
                -->
                <div
                    class="grid min-w-0 gap-0.5"
                    :style="{ gridTemplateColumns: `1rem repeat(${impacto.length}, minmax(0, 1fr))` }"
                    role="img"
                    :aria-label="descripcion"
                >
                    <template v-for="fila in filas" :key="fila.valor">
                        <div
                            class="cifra flex items-center justify-center pr-1 text-[0.625rem] text-muted-foreground"
                            :title="fila.etiqueta"
                        >
                            {{ fila.valor }}
                        </div>

                        <div
                            v-for="columna in impacto"
                            :key="`${fila.valor}-${columna.valor}`"
                            class="relative flex aspect-square items-center justify-center rounded-sm"
                            :class="[
                                claseDe(fila.valor * columna.valor),
                                marcaDe(fila.valor, columna.valor)
                                    ? 'ring-2 ring-foreground ring-offset-1 ring-offset-card'
                                    : '',
                            ]"
                            :title="titulo(fila, columna)"
                        >
                            <!--
                                El número sólo en las celdas señaladas. Pintarlo
                                en las veinticinco convierte el mapa de calor en
                                una tabla de multiplicar, que es justo lo que la
                                cuadrícula evita tener que leer.
                            -->
                            <span
                                v-if="marcaDe(fila.valor, columna.valor)"
                                class="cifra text-[0.625rem] font-semibold text-background"
                            >
                                {{ fila.valor * columna.valor }}
                            </span>
                        </div>
                    </template>

                    <!-- El eje de impacto, en la misma rejilla: primera celda
                         vacía bajo la columna de rótulos. -->
                    <div aria-hidden="true" />
                    <div
                        v-for="columna in impacto"
                        :key="`eje-${columna.valor}`"
                        class="cifra pt-1 text-center text-[0.625rem] text-muted-foreground"
                        :title="columna.etiqueta"
                    >
                        {{ columna.valor }}
                    </div>
                </div>

                <p class="mt-1 text-center text-xs text-muted-foreground">Impacto</p>
            </div>
        </div>

        <!--
            La leyenda va en texto y no sólo en color: DESIGN.md §3 no deja
            comunicar un estado sólo con el tono, y aquí además es donde se lee de
            qué a qué va cada banda, que es el dato que la cuadrícula no dice.
        -->
        <ul class="flex flex-wrap gap-x-3 gap-y-1.5 text-xs">
            <li v-for="(banda, nivel) in bandas" :key="nivel" class="flex items-center gap-1.5">
                <span class="size-2.5 shrink-0 rounded-sm" :class="tono(banda.tono).relleno" aria-hidden="true" />
                <span>{{ banda.etiqueta }}</span>
                <span class="cifra text-muted-foreground">
                    {{ banda.desde === banda.hasta ? banda.desde : `${banda.desde}–${banda.hasta}` }}
                </span>
            </li>
        </ul>

        <!--
            Las celdas señaladas, en texto. El anillo de la cuadrícula no llega a
            quien usa lector de pantalla, y dónde cae ESTE riesgo es justo lo que
            se ha venido a ver.
        -->
        <p v-if="marcadas.length > 0" class="text-xs text-muted-foreground">
            <span v-for="(marca, i) in marcadas" :key="marca.etiqueta">
                <template v-if="i > 0"> · </template>
                {{ marca.etiqueta }}:
                <span class="cifra text-foreground">{{ marca.probabilidad * marca.impacto }}</span>
            </span>
        </p>
    </div>
</template>
