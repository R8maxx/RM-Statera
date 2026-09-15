<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { Link } from '@inertiajs/vue3';
import { CornerDownRightIcon } from '@lucide/vue';
import { motion } from 'motion-v';

export interface ActivoDelGrafo {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    tipoEtiqueta: string;
    profundidad: number;
    nota: string | null;
    directa: boolean;
}

/**
 * Una rama del grafo, como lista sangrada por profundidad.
 *
 * Deliberadamente no es un diagrama de nodos: en el ancho de una ficha, una
 * cadena de cinco saltos dibujada se lee peor que la misma cadena en una lista,
 * y un grafo con veinte activos dibujado no se lee en absoluto. El sangrado
 * dice la distancia y el texto dice el resto.
 *
 * Sólo se puede retirar un vínculo DIRECTO: los indirectos no existen como fila,
 * son consecuencia de otros. Ofrecer un botón «quitar» sobre algo que está tres
 * saltos más allá prometería una acción que no se puede cumplir aquí.
 */
defineProps<{
    activos: ActivoDelGrafo[];
    activoId: number;
    vacio: string;
    /** Sólo el sentido «depende de» se opera desde esta ficha. */
    retirable?: boolean;
}>();

defineEmits<{ retirar: [id: number] }>();

const { reducido } = useMovimientoReducido();

/**
 * La cadena se despliega de arriba abajo, un nivel cada 60 ms.
 *
 * Aquí el escalonado no es ritmo: la lista está sangrada por profundidad y lo
 * que se ve avanzar **es la dependencia**. Que el nivel 3 llegue después del 2
 * dice que cuelga de él, que es lo que una lista sangrada pide que deduzcas
 * leyendo. Y es lo que justifica el valor efectivo que se enseña al lado: la
 * valoración sube por esta cadena.
 *
 * Por nivel y no por fila: dos hermanos del mismo nivel entran a la vez, porque
 * ninguno depende del otro.
 */
function retrasoDe(profundidad: number): number {
    return reducido.value ? 0 : Math.min(profundidad - 1, 6) * 0.06;
}
</script>

<template>
    <ul v-if="activos.length > 0" class="divide-y divide-border">
        <motion.li
            v-for="activo in activos"
            :key="activo.id"
            :initial="reducido ? { opacity: 1 } : { opacity: 0, x: -6 }"
            :animate="{ opacity: 1, x: 0 }"
            :transition="{
                duration: reducido ? 0 : 0.22,
                delay: retrasoDe(activo.profundidad),
                ease: [0.16, 1, 0.3, 1],
            }"
            class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0"
        >
            <div class="flex min-w-0 flex-1 gap-2" :style="{ paddingInlineStart: `${(activo.profundidad - 1) * 1.25}rem` }">
                <CornerDownRightIcon
                    v-if="activo.profundidad > 1"
                    class="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />

                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="`/activos/${activo.id}`"
                            class="text-sm font-medium underline-offset-4 hover:underline"
                        >
                            <span class="cifra text-muted-foreground">{{ activo.codigo }}</span>
                            {{ activo.nombre }}
                        </Link>
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ activo.tipoEtiqueta }}
                        <template v-if="!activo.directa">
                            · a {{ activo.profundidad }} saltos, por otro activo
                        </template>
                    </p>
                    <p v-if="activo.nota" class="mt-1 text-sm">{{ activo.nota }}</p>
                </div>
            </div>

            <Button
                v-if="retirable && activo.directa"
                variant="ghost"
                size="sm"
                @click="$emit('retirar', activo.id)"
            >
                Retirar
            </Button>
        </motion.li>
    </ul>

    <p v-else class="text-sm text-muted-foreground">{{ vacio }}</p>
</template>
