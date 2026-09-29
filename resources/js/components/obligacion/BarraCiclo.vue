<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import { fechaLegible, formatoNumero } from '@/lib/celdas';
import { tono } from '@/lib/tonos';
import { computed } from 'vue';

type ValorEtiquetado = App.Http.Resources.Definicion.ValorEtiquetado;

export interface Tramo {
    tipo: ValorEtiquetado;
    desde: string;
    hasta: string;
    dias: number;
    cumplimiento: number | null;
}

/**
 * La vida de un compromiso en una barra: dónde estuvo cubierto y dónde hubo un
 * hueco.
 *
 * Los tramos los calcula el servidor (`CicloCompromiso`), con la misma regla que
 * la próxima fecha; aquí sólo se reparten a lo ancho. **Cada tramo ocupa lo que
 * duró**, con `flex-grow` en días, y los 2 px de separación son los de
 * `BarraSegmentada` (DESIGN.md § 3): pegados se leen como una mancha.
 *
 * La barra no carga sola con la información. Debajo va la lista de tramos con
 * icono, palabra y fechas, que es lo que lee un lector de pantalla y lo que
 * contesta la pregunta sin tener que medir nada a ojo.
 */
const props = defineProps<{
    tramos: Tramo[];
    hoy: string;
    /** La próxima fecha, o `null` si el compromiso está retirado y ya no vence. */
    vence: string | null;
}>();

const dia = 86_400_000;

function instante(fecha: string): number {
    return Date.parse(`${fecha}T00:00:00Z`);
}

const inicio = computed(() => instante(props.tramos[0]?.desde ?? props.hoy));

const fin = computed(() => Math.max(instante(props.tramos.at(-1)?.hasta ?? props.hoy), instante(props.hoy)));

const total = computed(() => Math.max(1, (fin.value - inicio.value) / dia));

/** Lo que queda entre el último tramo y hoy: sólo pasa con uno retirado. */
const colaSinTramo = computed(() => (fin.value - instante(props.tramos.at(-1)?.hasta ?? props.hoy)) / dia);

function posicion(fecha: string): number {
    return Math.min(100, Math.max(0, ((instante(fecha) - inicio.value) / dia / total.value) * 100));
}

const posicionHoy = computed(() => posicion(props.hoy));
const posicionVence = computed(() => (props.vence === null ? null : posicion(props.vence)));

/*
 * Si hoy y el vencimiento caen casi en el mismo sitio, las dos etiquetas se
 * pisarían encima de la barra: la del vencimiento baja.
 */
const venceDebajo = computed(
    () => posicionVence.value !== null && Math.abs(posicionVence.value - posicionHoy.value) < 22,
);

const descripcion = computed(() =>
    props.tramos
        .map((tramo) => `${tramo.tipo.etiqueta} del ${fechaLegible(tramo.desde)} al ${fechaLegible(tramo.hasta)}`)
        .join('; '),
);

const dias = (n: number) => `${formatoNumero.format(n)} ${n === 1 ? 'día' : 'días'}`;
</script>

<template>
    <div class="space-y-5">
        <div class="relative pt-6 pb-6">
            <div class="flex h-8 gap-0.5" role="img" :aria-label="descripcion">
                <div
                    v-for="tramo in tramos"
                    :key="`${tramo.tipo.valor}-${tramo.desde}`"
                    class="min-w-0.5 basis-0 rounded-md first:rounded-l-lg last:rounded-r-lg"
                    :class="tono(tramo.tipo.tono).tramo"
                    :style="{ flexGrow: Math.max(tramo.dias, 1) }"
                />
                <div
                    v-if="colaSinTramo > 0"
                    class="basis-0 rounded-md bg-muted"
                    :style="{ flexGrow: colaSinTramo }"
                />
            </div>

            <!-- Hoy, en tinta de texto: es la referencia contra la que se lee el resto. -->
            <div
                class="pointer-events-none absolute top-4 bottom-4 w-0.5 -translate-x-1/2 rounded-full bg-foreground"
                :style="{ left: `${posicionHoy}%` }"
                aria-hidden="true"
            />
            <span
                class="absolute top-0 text-xs font-semibold whitespace-nowrap"
                :class="posicionHoy > 85 ? '-translate-x-full' : posicionHoy < 15 ? '' : '-translate-x-1/2'"
                :style="{ left: `${posicionHoy}%` }"
                aria-hidden="true"
            >
                Hoy
            </span>

            <template v-if="vence !== null && posicionVence !== null">
                <div
                    class="pointer-events-none absolute top-4 bottom-4 w-0.5 -translate-x-1/2 rounded-full bg-primary"
                    :style="{ left: `${posicionVence}%` }"
                    aria-hidden="true"
                />
                <span
                    class="absolute text-xs font-semibold whitespace-nowrap text-primary"
                    :class="[
                        venceDebajo ? 'bottom-0' : 'top-0',
                        posicionVence > 85 ? '-translate-x-full' : posicionVence < 15 ? '' : '-translate-x-1/2',
                    ]"
                    :style="{ left: `${posicionVence}%` }"
                    aria-hidden="true"
                >
                    Vence · {{ fechaLegible(vence) }}
                </span>
            </template>
        </div>

        <div class="-mt-4 flex justify-between text-xs text-muted-foreground" aria-hidden="true">
            <span class="cifra">{{ fechaLegible(tramos[0]?.desde ?? hoy) }}</span>
            <span class="cifra">{{ fechaLegible(new Date(fin).toISOString().slice(0, 10)) }}</span>
        </div>

        <ul class="grid gap-2.5 text-sm">
            <li
                v-for="tramo in tramos"
                :key="`${tramo.tipo.valor}-${tramo.desde}`"
                class="flex items-start gap-2.5"
            >
                <IconoTipo
                    :nombre="tramo.tipo.icono"
                    :clase="`mt-0.5 size-4 shrink-0 ${tono(tramo.tipo.tono).texto ?? 'text-muted-foreground'}`"
                />
                <span class="min-w-0">
                    <span class="font-medium">{{ tramo.tipo.etiqueta }}</span>
                    <span class="text-muted-foreground">
                        · del {{ fechaLegible(tramo.desde) }} al {{ fechaLegible(tramo.hasta) }},
                        <span class="cifra">{{ dias(tramo.dias) }}</span>
                    </span>
                </span>
            </li>
        </ul>
    </div>
</template>
