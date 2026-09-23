<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';

export interface ServicioComparado {
    id: number;
    codigo: string;
    nombre: string;
    rtoObjetivo: number | null;
    rtoAlcanzado: number | null;
    rpoObjetivo: number | null;
    rpoAlcanzado: number | null;
    /** `true` = se pasó, `false` = se cumplió, `null` = falta un dato para decirlo. */
    excedeRto: boolean | null;
}

/**
 * Por servicio, lo que el BIA prometía frente a lo que la prueba alcanzó de
 * verdad. § 4.11.
 *
 * **`caducada` sólo cuando `excedeRto` es `true`.** Es el único rojo de toda
 * la ficha de una prueba, a propósito: un RTO alcanzado que se queda dentro
 * del objetivo no es una alerta, y uno que todavía no se ha registrado
 * —`null`, mientras la prueba sigue planificada— tampoco lo es. El rojo es
 * para el incumplimiento medido, no para lo que falta por medir.
 *
 * Sobre `ComparativaValoracion.vue`, misma forma de tabla a mano.
 */
defineProps<{ servicios: ServicioComparado[] }>();

function horas(valor: number | null): string {
    return valor === null ? '—' : `${valor} h`;
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[36rem] text-sm">
            <thead>
                <tr class="border-b text-left text-xs text-muted-foreground">
                    <th scope="col" class="pb-2 font-medium">Servicio</th>
                    <th scope="col" class="pb-2 font-medium">RTO objetivo</th>
                    <th scope="col" class="pb-2 font-medium">RTO alcanzado</th>
                    <th scope="col" class="pb-2 font-medium">RPO objetivo</th>
                    <th scope="col" class="pb-2 font-medium">RPO alcanzado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr v-for="servicio in servicios" :key="servicio.id">
                    <th scope="row" class="py-2 text-left font-normal">
                        {{ servicio.nombre }}
                        <span class="cifra ml-1 text-xs text-muted-foreground">{{ servicio.codigo }}</span>
                    </th>
                    <td class="py-2 text-muted-foreground">{{ horas(servicio.rtoObjetivo) }}</td>
                    <td class="py-2">
                        <div class="flex items-center gap-2">
                            <span :class="servicio.excedeRto ? 'font-medium text-destructive' : undefined">
                                {{ horas(servicio.rtoAlcanzado) }}
                            </span>
                            <CeldaBadge
                                v-if="servicio.excedeRto"
                                :valor="{ valor: 'caducada', etiqueta: 'Por encima del RTO', tono: 'caducada' }"
                            />
                        </div>
                    </td>
                    <td class="py-2 text-muted-foreground">{{ horas(servicio.rpoObjetivo) }}</td>
                    <td class="py-2 text-muted-foreground">{{ horas(servicio.rpoAlcanzado) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
