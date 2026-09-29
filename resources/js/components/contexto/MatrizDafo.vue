<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';
import { Link2OffIcon, PlusIcon, ThermometerIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * La matriz del DAFO: cuatro cuadrantes sobre dos ejes.
 *
 * **Rejilla CSS y no SVG**, calcada de `riesgo/MatrizRiesgo.vue` y por la misma
 * razón concreta: tiene que caber a 375 px sin scroll horizontal, y una rejilla se
 * encoge con su contenedor mientras que un `viewBox` escala el texto hasta hacerlo
 * ilegible. Y sin librería: un 2×2 de listas no es una serie histórica con eje de
 * tiempo, que es la única puerta que el stack deja abierta.
 *
 * **Los rótulos de los ejes viven DENTRO de la misma rejilla**, en una primera
 * columna y una primera fila propias. Sacarlos a un flex aparte es el fallo que ya
 * se corrigió en la matriz de riesgo: las dos rejillas divergen y quedan filas sin
 * rótulo.
 *
 * ### Un tono por cuadrante, de la familia `--dafo-*`
 *
 * Los cuatro cuadrantes tienen color propio, en la tercera familia semántica que
 * declara `DESIGN.md` § 3. Los hues van por pares —verde y azul lo favorable, ocre
 * y magenta lo adverso—, así que el color dice a la vez de qué mitad es y qué
 * cuadrante exacto.
 *
 * Aun así los rótulos de los dos ejes van **escritos**, y también al apilarse por
 * debajo de `sm`: un 2×2 en columna pierde la posición, que es el otro canal que
 * distingue «interno» de «externo». Y el badge conserva su icono, porque contra el
 * rojo de `destructive` el color no puede cargar solo.
 *
 * ### Lo que pide acción se ve dentro del cuadrante
 *
 * Una debilidad o una amenaza sin riesgo detrás lleva el chip «Sin riesgo», y su
 * cuadrante lo cuenta bajo el título. **Neutro, nunca rojo**: es una pregunta
 * pendiente (ISO 6.1.1), no un incumplimiento, y el rojo tiene tres dueños. Qué
 * cuestión está en ese caso lo dice el servidor con el mismo scope que la cifra
 * del panel; aquí sólo se pinta.
 *
 * El «+» de cada cabecera abre el formulario con el tipo ya puesto, que es lo que
 * `CuestionContextoController::create()` acepta por `?tipo=`. Sin él, añadir una
 * fortaleza desde la matriz obligaba a pasar por la tabla.
 *
 * El tono llega del servidor, nunca se deduce aquí: el mismo tono significa cosas
 * distintas según el módulo, y un segundo mapa tipo→color en este fichero sería la
 * copia que `lib/tonos.ts` existe para evitar.
 */

interface Cuestion {
    id: number;
    codigo: string;
    titulo: string;
    tipo: string;
    tipoEtiqueta: string;
    tono: string;
    icono: string;
    materia: string;
    esClimatica: boolean;
    responsable: string | null;
    riesgos: number;
    tareas: number;
    sinRiesgo: boolean;
}

interface TipoEje {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    signo: string;
    signoEtiqueta: string;
}

interface AmbitoEje {
    valor: string;
    etiqueta: string;
    ayuda: string;
    tipos: TipoEje[];
}

const props = withDefaults(
    defineProps<{
        ambitos: AmbitoEje[];
        dafo: Record<string, Cuestion[]>;
        /**
         * Cuántas se pintan por cuadrante antes del «y N más».
         *
         * Mismo patrón que el tope por columna del tablero y que el de tres
         * vencimientos por día del calendario: un cuadrante con veinticinco
         * entradas estira la matriz hasta que deja de caber en la pantalla, y una
         * matriz que no se ve entera deja de ser una matriz.
         */
        tope?: number;
        /** Si se pinta el «+» de cada cuadrante: sólo con `contexto.gestionar`. */
        puedeAnadir?: boolean;
    }>(),
    { tope: 6, puedeAnadir: false },
);

/** Los rótulos de la fila de cabecera: los signos, en el orden del primer ámbito. */
const signos = computed(() => props.ambitos[0]?.tipos ?? []);

function cuestionesDe(tipo: string): Cuestion[] {
    return props.dafo[tipo] ?? [];
}

function visibles(tipo: string): Cuestion[] {
    return cuestionesDe(tipo).slice(0, props.tope);
}

function restantes(tipo: string): number {
    return Math.max(0, cuestionesDe(tipo).length - props.tope);
}

function sinRiesgoEn(tipo: string): number {
    return cuestionesDe(tipo).filter((cuestion) => cuestion.sinRiesgo).length;
}

function cuantas(cifra: number, singular: string, plural: string): string {
    return `${cifra} ${cifra === 1 ? singular : plural}`;
}

/**
 * La descripción que lee un lector de pantalla en lugar de recorrer la rejilla.
 *
 * Por cuadrante y no celda a celda, igual que la matriz de riesgo: lo que hay que
 * poder saber sin ver es cuántas cuestiones hay en cada cuadrante.
 */
const descripcion = computed(() =>
    props.ambitos
        .flatMap((ambito) =>
            ambito.tipos.map(
                (tipo) => `${tipo.etiqueta}: ${cuestionesDe(tipo.valor).length}`,
            ),
        )
        .join('. '),
);
</script>

<template>
    <div>
        <!--
            Apilada por debajo de `sm` y en rejilla a partir de ahí. Los rótulos
            de ámbito de la columna izquierda se ocultan al apilar, porque cada
            cuadrante lleva el suyo escrito en la cabecera.
        -->
        <div
            class="grid gap-3 sm:grid-cols-[auto_1fr_1fr]"
            role="img"
            :aria-label="`Matriz DAFO. ${descripcion}`"
        >
            <!-- Fila de cabecera: hueco del eje + los dos signos. -->
            <div class="hidden sm:block" aria-hidden="true" />
            <div
                v-for="signo in signos"
                :key="`cabecera-${signo.signo}`"
                class="hidden pb-1 text-xs font-medium text-muted-foreground sm:block"
                aria-hidden="true"
            >
                {{ signo.signoEtiqueta }}
            </div>

            <template v-for="ambito in ambitos" :key="ambito.valor">
                <!-- Rótulo del eje vertical, dentro de la misma rejilla. -->
                <div
                    class="hidden items-center sm:flex"
                    :title="ambito.ayuda"
                    aria-hidden="true"
                >
                    <span
                        class="rotate-180 text-xs font-medium text-muted-foreground [writing-mode:vertical-rl] [text-orientation:mixed]"
                    >
                        {{ ambito.etiqueta }}
                    </span>
                </div>

                <section
                    v-for="tipo in ambito.tipos"
                    :key="tipo.valor"
                    class="flex min-w-0 flex-col overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10"
                >
                    <!-- El filete del cuadrante: fondo sólido, nunca alfa. -->
                    <div class="h-0.5 w-full" :class="tono(tipo.tono).relleno" aria-hidden="true" />

                    <header class="flex items-center gap-3 py-3 pr-3 pl-4">
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-md"
                            :class="tono(tipo.tono).badge"
                            aria-hidden="true"
                        >
                            <IconoTipo :nombre="tipo.icono" clase="size-4.5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-semibold">{{ tipo.etiqueta }}</h3>
                            <!-- Los dos ejes escritos: es lo que sobrevive al apilado. -->
                            <p class="text-xs text-muted-foreground">
                                {{ ambito.etiqueta }} · {{ tipo.signoEtiqueta }}<template v-if="sinRiesgoEn(tipo.valor) > 0">
                                    · {{ sinRiesgoEn(tipo.valor) }} sin riesgo vinculado</template>
                            </p>
                        </div>
                        <span class="cifra text-xl font-bold">{{ cuestionesDe(tipo.valor).length }}</span>
                        <Link
                            v-if="puedeAnadir"
                            :href="`/contexto/cuestiones/crear?tipo=${tipo.valor}`"
                            class="flex size-11 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:size-8"
                            :aria-label="`Añadir: ${tipo.etiqueta.toLowerCase()}`"
                        >
                            <PlusIcon class="size-4" aria-hidden="true" />
                        </Link>
                    </header>

                    <ul v-if="cuestionesDe(tipo.valor).length > 0" class="flex-1">
                        <li v-for="cuestion in visibles(tipo.valor)" :key="cuestion.id" class="border-t border-border/60">
                            <Link
                                :href="`/contexto/cuestiones/${cuestion.id}`"
                                class="flex gap-3 px-4 py-2.5 transition-colors hover:bg-fila-hover"
                            >
                                <span class="cifra w-12 shrink-0 pt-0.5 text-xs text-muted-foreground">{{ cuestion.codigo }}</span>
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="text-sm font-medium">{{ cuestion.titulo }}</span>
                                    <span class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                        <span>{{ cuestion.materia }}</span>
                                        <span v-if="cuestion.riesgos > 0">{{ cuantas(cuestion.riesgos, 'riesgo', 'riesgos') }}</span>
                                        <span v-if="cuestion.tareas > 0">{{ cuantas(cuestion.tareas, 'tarea', 'tareas') }}</span>
                                        <span v-if="cuestion.esClimatica" class="inline-flex items-center gap-0.5">
                                            <ThermometerIcon class="size-3" aria-hidden="true" />
                                            Climática
                                        </span>
                                    </span>
                                </span>
                                <span
                                    v-if="cuestion.sinRiesgo"
                                    class="inline-flex h-5 shrink-0 items-center gap-1 self-start rounded-full bg-muted px-2 text-xs font-medium whitespace-nowrap text-secondary-foreground"
                                >
                                    <Link2OffIcon class="size-3" aria-hidden="true" />
                                    Sin riesgo
                                </span>
                            </Link>
                        </li>
                    </ul>

                    <p v-else class="flex-1 border-t border-border/60 px-4 py-3 text-sm text-muted-foreground">
                        Sin nada apuntado todavía.
                    </p>

                    <!--
                        El resto no se esconde: se cuenta y se enlaza a la tabla
                        ya filtrada por este cuadrante.
                    -->
                    <Link
                        v-if="restantes(tipo.valor) > 0"
                        :href="`/contexto/cuestiones?filter[tipo]=${tipo.valor}&filter[vigentes]=1`"
                        class="border-t border-border px-4 py-2.5 text-sm font-medium text-primary hover:underline"
                    >
                        y {{ restantes(tipo.valor) }} más
                    </Link>
                </section>
            </template>
        </div>

        <!-- Los ejes también en prosa: a 375 px la columna de rótulos no se pinta. -->
        <p class="mt-3 text-xs text-muted-foreground">
            <template v-for="(ambito, i) in ambitos" :key="ambito.valor">
                <span v-if="i > 0"> · </span>
                <strong class="font-medium text-foreground">{{ ambito.etiqueta }}:</strong>
                {{ ambito.ayuda }}
            </template>
        </p>
    </div>
</template>
