<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { CheckIcon, MinusIcon } from '@lucide/vue';
import { computed } from 'vue';

type Columna = App.Http.Resources.Definicion.Columna;
type ValorEtiquetado = App.Http.Resources.Definicion.ValorEtiquetado;

const props = defineProps<{ columna: Columna; valor: unknown }>();

const vacio = computed(() => props.valor === null || props.valor === undefined || props.valor === '');

const fecha = computed(() => {
    if (typeof props.valor !== 'string') {
        return null;
    }

    const instante = new Date(props.valor);

    return Number.isNaN(instante.getTime()) ? null : instante;
});

const formatoFecha = new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' });
const formatoFechaHora = new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' });
const formatoNumero = new Intl.NumberFormat('es-ES');
</script>

<template>
    <CeldaBadge v-if="columna.tipo === 'badge'" :valor="(valor as ValorEtiquetado | null)" />

    <template v-else-if="columna.tipo === 'booleano'">
        <CheckIcon v-if="valor" class="size-4 text-estado-implantado" aria-label="Sí" />
        <MinusIcon v-else class="size-4 text-muted-foreground" aria-label="No" />
    </template>

    <span v-else-if="vacio" class="text-muted-foreground">—</span>

    <span v-else-if="columna.tipo === 'numero'" class="tabular-nums">
        {{ formatoNumero.format(Number(valor)) }}
    </span>

    <span v-else-if="columna.tipo === 'fecha'">
        {{ fecha ? formatoFecha.format(fecha) : valor }}
    </span>

    <span v-else-if="columna.tipo === 'fecha_hora'">
        {{ fecha ? formatoFechaHora.format(fecha) : valor }}
    </span>

    <span v-else class="truncate">{{ valor }}</span>
</template>
