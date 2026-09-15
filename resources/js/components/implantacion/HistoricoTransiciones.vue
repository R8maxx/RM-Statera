<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { motion } from 'motion-v';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { formatoFechaHora } from '@/lib/celdas';

export interface Transicion {
    id: number;
    anterior: string | null;
    nuevo: string;
    claveNuevo: string;
    usuario: string | null;
    nota: string | null;
    fecha: string | null;
}

/**
 * El histórico de estados, que es la respuesta a «¿desde cuándo?».
 *
 * El auditor no pregunta si algo está implantado, pregunta desde cuándo, y por
 * eso el invariante 7 obliga a registrar cada transición con fecha y autor.
 * Aquí se lee del más reciente al más antiguo porque lo que se consulta casi
 * siempre es lo último que pasó.
 */
defineProps<{ transiciones: Transicion[] }>();

function cuando(fecha: string | null): string {
    return fecha ? formatoFechaHora.format(new Date(fecha)) : '—';
}
const { reducido } = useMovimientoReducido();

/*
 * La línea se recorre de arriba abajo, 40 ms por hito.
 *
 * Es una cronología: que los hitos lleguen en orden es lo que la hace leerse
 * como tal en vez de como una lista. Tope a diez para que un requisito con
 * treinta transiciones no tarde más de medio segundo en estar entero.
 */
const retrasoDe = (indice: number): number => (reducido.value ? 0 : Math.min(indice, 10) * 0.04);
</script>

<template>
    <ol v-if="transiciones.length > 0" class="relative space-y-5 border-l pl-5">
        <motion.li
            v-for="(transicion, indice) in [...transiciones].reverse()"
            :key="transicion.id"
            :initial="reducido ? { opacity: 1 } : { opacity: 0, y: 6 }"
            :animate="{ opacity: 1, y: 0 }"
            :transition="{ duration: reducido ? 0 : 0.22, delay: retrasoDe(indice), ease: [0.16, 1, 0.3, 1] }"
            class="relative"
        >
            <span
                class="absolute top-1.5 -left-[1.4375rem] size-2 rounded-full bg-border ring-4 ring-background"
                aria-hidden="true"
            />

            <div class="flex flex-wrap items-center gap-2">
                <span v-if="transicion.anterior" class="text-sm text-muted-foreground">
                    {{ transicion.anterior }} →
                </span>
                <span v-else class="text-sm text-muted-foreground">Se da de alta →</span>

                <CeldaBadge
                    :valor="{ valor: transicion.claveNuevo, etiqueta: transicion.nuevo, tono: transicion.claveNuevo }"
                />
            </div>

            <p class="mt-1 text-xs text-muted-foreground">
                {{ cuando(transicion.fecha) }} ·
                <!-- Sin autor significa que la provocó el recálculo, no una
                     persona. Decirlo evita que parezca un dato perdido. -->
                {{ transicion.usuario ?? 'recálculo del sistema' }}
            </p>

            <p v-if="transicion.nota" class="mt-1.5 text-sm">{{ transicion.nota }}</p>
        </motion.li>
    </ol>

    <p v-else class="text-sm text-muted-foreground">
        Todavía no hay transiciones registradas.
    </p>
</template>
