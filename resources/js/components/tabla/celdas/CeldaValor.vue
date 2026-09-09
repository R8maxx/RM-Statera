<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import CeldaEscala from '@/components/tabla/celdas/CeldaEscala.vue';
import TextoResaltado from '@/components/tabla/celdas/TextoResaltado.vue';
import {
    enlaceDe,
    esVacio,
    fechaDe,
    formatoFecha,
    formatoFechaHora,
    formatoNumero,
    progresoDe,
} from '@/lib/celdas';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRightIcon, CheckIcon, MinusIcon } from '@lucide/vue';
import { computed } from 'vue';

type Columna = App.Http.Resources.Definicion.Columna;
type ValorEtiquetado = App.Http.Resources.Definicion.ValorEtiquetado;
type ValorEnlace = App.Http.Resources.Definicion.ValorEnlace;
type ValorEscala = App.Http.Resources.Definicion.ValorEscala;
type ValorProgreso = App.Http.Resources.Definicion.ValorProgreso;

const props = defineProps<{
    columna: Columna;
    valor: unknown;
    /** Lo buscado, para marcarlo dentro del texto de la celda. */
    terminos?: string[];
}>();

const vacio = computed(() => esVacio(props.valor));
const fecha = computed(() => fechaDe(props.valor));

/*
 * `enlace` y `progreso` viajan como objeto. Si llega otra cosa —una cadena
 * suelta desde un `formato()` antiguo— se degrada a texto en lugar de romper la
 * fila entera.
 */
const enlace = computed<ValorEnlace | null>(() => enlaceDe(props.valor));
const progreso = computed<ValorProgreso | null>(() => progresoDe(props.valor));
</script>

<template>
    <CeldaBadge v-if="columna.tipo === 'badge'" :valor="(valor as ValorEtiquetado | null)" />

    <CeldaEscala v-else-if="columna.tipo === 'escala'" :valor="(valor as ValorEscala | null)" />

    <template v-else-if="columna.tipo === 'booleano'">
        <CheckIcon v-if="valor" class="size-4 text-estado-implantado" aria-label="Sí" />
        <MinusIcon v-else class="size-4 text-muted-foreground" aria-label="No" />
    </template>

    <span v-else-if="vacio" class="text-muted-foreground" aria-label="sin valor">—</span>

    <!-- Interno por Inertia; externo abre fuera y lo dice con el icono. -->
    <template v-else-if="columna.tipo === 'enlace' && enlace">
        <a
            v-if="enlace.externo"
            :href="enlace.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1 rounded font-medium text-primary underline-offset-4 hover:underline"
        >
            <TextoResaltado :texto="enlace.etiqueta" :terminos="terminos" />
            <ArrowUpRightIcon class="size-3.5 opacity-70" />
        </a>
        <Link
            v-else
            :href="enlace.url"
            class="rounded font-medium text-primary underline-offset-4 hover:underline"
        >
            <TextoResaltado :texto="enlace.etiqueta" :terminos="terminos" />
        </Link>
    </template>

    <div v-else-if="columna.tipo === 'progreso' && progreso" class="flex items-center justify-end gap-2.5">
        <div
            class="h-1.5 w-20 shrink-0 overflow-hidden rounded-full bg-muted"
            role="img"
            :aria-label="`${progreso.porcentaje} por ciento`"
        >
            <div
                class="h-full rounded-full bg-primary transition-[width] duration-500 ease-marca"
                :style="{ width: `${Math.min(Math.max(progreso.porcentaje, 0), 100)}%` }"
            />
        </div>
        <span class="cifra w-9 shrink-0 text-right text-xs">{{ progreso.porcentaje }}%</span>
        <span v-if="progreso.de !== null" class="cifra shrink-0 text-xs text-muted-foreground">
            {{ progreso.hechas }}/{{ progreso.de }}
        </span>
    </div>

    <span v-else-if="columna.tipo === 'numero'" class="cifra">
        {{ formatoNumero.format(Number(valor)) }}
    </span>

    <span v-else-if="columna.tipo === 'fecha'" class="cifra text-[0.8125rem]">
        {{ fecha ? formatoFecha.format(fecha) : valor }}
    </span>

    <span v-else-if="columna.tipo === 'fecha_hora'" class="cifra text-[0.8125rem]">
        {{ fecha ? formatoFechaHora.format(fecha) : valor }}
    </span>

    <span v-else class="block truncate" :title="String(valor)">
        <TextoResaltado :texto="String(valor)" :terminos="terminos" />
    </span>
</template>
