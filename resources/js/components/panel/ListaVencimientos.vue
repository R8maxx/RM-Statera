<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import { fechaLegible } from '@/lib/celdas';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2Icon } from '@lucide/vue';
import { computed } from 'vue';

type Vencimiento = App.Domain.Aviso.Vencimiento;

/**
 * «Lo que vence», al lado del plan de acción.
 *
 * El plan dice cuánto hay abierto y esto dice **para cuándo**, juntando los
 * plazos de todos los registros de la pestaña: una tarea, un compromiso
 * periódico, el plazo de remediación de una vulnerabilidad. Antes cada tarjeta
 * decía sus vencidas por separado y la pregunta «qué vence esta semana» no tenía
 * dónde contestarse sin abrir el calendario.
 *
 * Las filas las elige el servidor (`VencimientosDelPanel`): lo pasado primero,
 * lo más antiguo delante, y sólo las fuentes cuyo rojo cuenta el punto de esta
 * pestaña. Aquí sólo se pintan. Lo que no cabe se cuenta y se enlaza.
 *
 * El rojo es para lo vencido y para nada más, y va con icono y con texto
 * («Venció el…»): el color solo no identifica nada (DESIGN.md § 3).
 */
const props = defineProps<{ vencimientos: App.Http.Resources.Panel.VencimientosPanel }>();

const restantes = computed(
    () => props.vencimientos.pasados + props.vencimientos.proximos - props.vencimientos.filas.length,
);

const cuando = (fila: Vencimiento): string => {
    const dias = Math.abs(fila.dias);
    const plural = dias === 1 ? 'día' : 'días';

    if (fila.dias < 0) {
        return `hace ${dias} ${plural}`;
    }

    return fila.dias === 0 ? 'hoy' : `en ${dias} ${plural}`;
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-baseline justify-between gap-4">
            <h2 class="text-base font-semibold tracking-[-0.01em]">Lo que vence</h2>
            <Link href="/calendario" class="text-sm font-medium text-primary underline-offset-4 hover:underline">
                Ver el calendario
            </Link>
        </div>
        <p class="text-sm text-muted-foreground">
            Lo vencido primero; después, los próximos {{ vencimientos.dias }} días.
        </p>

        <ol v-if="vencimientos.filas.length > 0">
            <li v-for="fila in vencimientos.filas" :key="`${fila.fuente}-${fila.id}`" class="border-b last:border-b-0">
                <Link
                    :href="fila.url"
                    class="-mx-2 grid grid-cols-[1rem_minmax(0,1fr)_auto] items-start gap-3 rounded-md px-2 py-2.5 transition-colors hover:bg-fila-hover"
                >
                    <IconoTipo
                        :nombre="fila.icono"
                        clase="mt-0.5 size-4"
                        :class="fila.dias < 0 ? 'text-destructive' : 'text-muted-foreground'"
                    />
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium">{{ fila.titulo }}</span>
                        <span class="block truncate text-xs text-muted-foreground">
                            {{ fila.fuenteEtiqueta }}<template v-if="fila.responsable"> · {{ fila.responsable }}</template>
                        </span>
                    </span>
                    <span
                        class="text-right text-xs whitespace-nowrap"
                        :class="fila.dias < 0 ? 'font-medium text-destructive' : 'text-secondary-foreground'"
                    >
                        <span class="block">{{ fila.dias < 0 ? 'Venció el' : 'Vence el' }} {{ fechaLegible(fila.dia) }}</span>
                        <span class="block font-normal" :class="fila.dias >= 0 && 'text-muted-foreground'">{{ cuando(fila) }}</span>
                    </span>
                </Link>
            </li>
        </ol>

        <p v-else class="flex items-center gap-2 text-sm text-muted-foreground">
            <CheckCircle2Icon class="size-4 shrink-0 text-estado-implantado" aria-hidden="true" />
            Nada vencido ni por vencer en los próximos {{ vencimientos.dias }} días.
        </p>

        <Link
            v-if="restantes > 0"
            href="/calendario"
            class="text-sm text-muted-foreground underline-offset-4 hover:underline"
        >
            Y {{ restantes }} más en el calendario
        </Link>
    </div>
</template>
