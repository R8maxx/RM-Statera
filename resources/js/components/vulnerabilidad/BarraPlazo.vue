<script setup lang="ts">
import { fechaLegible } from '@/lib/celdas';
import { computed } from 'vue';

/**
 * El plazo de remediación dibujado como barra: de la detección al límite y de
 * ahí a lo que lo para —hoy si sigue corriendo, o el día en que salió de
 * abierta y de remediación—.
 *
 * **La misma gramática que el reloj de la AEPD** (`incidente/RelojNotificacion`):
 * lo que pasó dentro de plazo en el ámbar de `en_progreso` a media intensidad,
 * lo que queda en `muted` y sólo lo que se pasó en `destructive`. Dos barras de
 * plazo que se leyeran distinto en dos fichas vecinas enseñarían a no leer
 * ninguna.
 *
 * Los días los cuenta `CicloRemediacion` en el servidor; aquí sólo se reparten.
 */
export interface Plazo {
    dias: number;
    detectada: string;
    limite: string;
    fin: string;
    finRotulo: string;
    corre: boolean;
    transcurridos: number;
    fuera: number;
}

const props = defineProps<{ plazo: Plazo }>();

const tramos = computed(() => {
    const { dias, transcurridos, fuera } = props.plazo;

    return {
        enPlazo: Math.min(transcurridos, dias),
        restante: Math.max(0, dias - transcurridos),
        fuera,
    };
});

/**
 * Dónde cae el hito de en medio. El que acaba la barra —el límite o el fin, el
 * que llegue después— va al 100 %; el otro, en su sitio, sujeto lejos de los
 * extremos para que su rótulo no pise los de al lado.
 */
const hitos = computed(() => {
    const { dias, transcurridos, fuera, limite, fin, finRotulo } = props.plazo;
    const total = Math.max(dias, transcurridos) || 1;
    const finHito = { rotulo: finRotulo, cuando: fechaLegible(fin), dia: transcurridos };
    const limiteHito = { rotulo: 'Límite', cuando: fechaLegible(limite), dia: dias };
    const [intermedio, ultimo] = fuera > 0 ? [limiteHito, finHito] : [finHito, limiteHito];

    return {
        intermedio: { ...intermedio, sitio: Math.min(82, Math.max(18, (intermedio.dia / total) * 100)) },
        ultimo,
    };
});

const descripcion = computed(() => {
    const { detectada, transcurridos, dias, fuera } = props.plazo;
    const { intermedio, ultimo } = hitos.value;

    return `Detectada el ${fechaLegible(detectada)}; ${intermedio.rotulo.toLowerCase()}, ${intermedio.cuando}; ${ultimo.rotulo.toLowerCase()}, ${ultimo.cuando}. ${transcurridos} de ${dias} días${
        fuera > 0 ? `, ${fuera} fuera de plazo` : ''
    }.`;
});
</script>

<template>
    <div class="space-y-2" role="img" :aria-label="descripcion">
        <div class="flex h-2.5 gap-0.5" aria-hidden="true">
            <span
                v-if="tramos.enPlazo > 0"
                class="h-full rounded-l-full bg-estado-en-progreso/40"
                :class="{ 'rounded-r-full': tramos.restante === 0 && tramos.fuera === 0 }"
                :style="{ flexGrow: tramos.enPlazo }"
            />
            <span
                v-if="tramos.restante > 0"
                class="h-full rounded-r-full bg-muted"
                :class="{ 'rounded-l-full': tramos.enPlazo === 0 }"
                :style="{ flexGrow: tramos.restante }"
            />
            <span v-if="tramos.fuera > 0" class="h-full rounded-r-full bg-destructive" :style="{ flexGrow: tramos.fuera }" />
        </div>

        <div class="relative h-8 text-xs text-muted-foreground" aria-hidden="true">
            <span class="absolute left-0 flex flex-col">
                <span class="font-medium text-foreground">Detectada</span>
                <span class="cifra">{{ fechaLegible(plazo.detectada) }}</span>
            </span>
            <span class="absolute flex -translate-x-1/2 flex-col items-center" :style="{ left: `${hitos.intermedio.sitio}%` }">
                <span class="font-medium text-foreground">{{ hitos.intermedio.rotulo }}</span>
                <span class="cifra">{{ hitos.intermedio.cuando }}</span>
            </span>
            <span class="absolute right-0 flex flex-col items-end">
                <span class="font-medium text-foreground">{{ hitos.ultimo.rotulo }}</span>
                <span class="cifra">{{ hitos.ultimo.cuando }}</span>
            </span>
        </div>
    </div>
</template>
