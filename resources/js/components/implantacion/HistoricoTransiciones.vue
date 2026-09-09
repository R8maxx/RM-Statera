<script setup lang="ts">
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
</script>

<template>
    <ol v-if="transiciones.length > 0" class="relative space-y-5 border-l pl-5">
        <li v-for="transicion in [...transiciones].reverse()" :key="transicion.id" class="relative">
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
        </li>
    </ol>

    <p v-else class="text-sm text-muted-foreground">
        Todavía no hay transiciones registradas.
    </p>
</template>
