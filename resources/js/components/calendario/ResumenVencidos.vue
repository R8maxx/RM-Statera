<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { Link } from '@inertiajs/vue3';
import { ChevronDownIcon, TriangleAlertIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

type Vencimiento = App.Domain.Aviso.Vencimiento;

/**
 * Lo pasado de fecha, **caiga en el mes que caiga**.
 *
 * La rejilla tiene casillas para seis semanas y nada más: una tarea que venció
 * en julio y sigue abierta no tenía dónde pintarse en septiembre, y pasar de mes
 * la hacía desaparecer. Lo que más urgía era justo lo que dejaba de verse. Esta
 * tarjeta no depende del mes que se mira —«pasado de fecha» se dice de hoy—, y
 * es el único elemento fuerte de la pantalla (§ 1): el rojo de la cifra es el
 * mismo que el de las filas vencidas de la rejilla.
 *
 * Sin nada vencido no se pinta. Una tarjeta que dice «0 pasados de fecha» es la
 * fila de ceros que § 9 quita del panel.
 */
const props = defineProps<{
    /** Del más antiguo al más reciente: así llegan del servidor. */
    vencidos: Vencimiento[];
    primerDia: string;
    ultimoDia: string;
    /** Lo que viene, sólo si la rejilla contiene hoy: en otro mes no significa nada. */
    proximos: { hoy: number; semana: number; despues: number } | null;
}>();

/** Cuántos caben antes de plegar el resto. Tres filas en dos columnas. */
const TOPE = 6;

const desplegado = ref(false);

const visibles = computed(() => (desplegado.value ? props.vencidos : props.vencidos.slice(0, TOPE)));

const enLaRejilla = computed(
    () => props.vencidos.filter((vencimiento) => vencimiento.dia >= props.primerDia && vencimiento.dia <= props.ultimoDia).length,
);

const desglose = computed(() => {
    const fuera = props.vencidos.length - enLaRejilla.value;

    if (fuera === 0) {
        return 'todo en este mes';
    }

    if (enLaRejilla.value === 0) {
        return 'ninguno en este mes';
    }

    return `${enLaRejilla.value} en este mes · ${fuera} de otros`;
});

/** «hace 46 días», que es lo que se compara de un vistazo; la fecha va en el título. */
function hace(dias: number): string {
    return dias === -1 ? 'ayer' : `hace ${-dias} días`;
}

const meta = (vencimiento: Vencimiento): string =>
    [vencimiento.fuenteEtiqueta, vencimiento.responsable ?? 'Sin responsable'].join(' · ');
</script>

<template>
    <section
        aria-labelledby="resumen-vencidos"
        class="flex flex-col overflow-hidden rounded-xl border bg-card md:flex-row"
    >
        <div class="flex shrink-0 flex-col gap-3 border-b px-6 py-5 md:w-64 md:border-r md:border-b-0">
            <div class="flex flex-col gap-0.5">
                <h2 id="resumen-vencidos" class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground">
                    <TriangleAlertIcon class="size-4 text-destructive" aria-hidden="true" />
                    Pasado de fecha
                </h2>
                <p class="flex items-baseline gap-2">
                    <Cifra :valor="vencidos.length" class="text-4xl font-bold tracking-tight text-destructive" />
                    <span class="text-[13px] text-muted-foreground">{{ desglose }}</span>
                </p>
            </div>

            <dl v-if="proximos" class="grid grid-cols-3 gap-2">
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs text-muted-foreground">Hoy</dt>
                    <dd class="cifra text-base font-medium">{{ proximos.hoy }}</dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs text-muted-foreground">7 días</dt>
                    <dd class="cifra text-base font-medium">{{ proximos.semana }}</dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs text-muted-foreground">Después</dt>
                    <dd class="cifra text-base font-medium">{{ proximos.despues }}</dd>
                </div>
            </dl>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-1 px-4 py-3">
            <p class="px-2 py-1 text-xs text-muted-foreground">
                Del más antiguo al más reciente. Lo de otros meses sigue aquí hasta que se cierre, aunque la rejilla
                no lo enseñe.
            </p>

            <ul class="grid gap-x-4 sm:grid-cols-2">
                <li v-for="vencimiento in visibles" :key="`${vencimiento.fuente}-${vencimiento.id}`">
                    <Link
                        :href="vencimiento.url"
                        :title="`${vencimiento.titulo} · ${vencimiento.estadoEtiqueta} · ${vencimiento.fecha}`"
                        class="flex min-h-11 items-center gap-2.5 rounded-md px-2 py-1 transition-colors hover:bg-superficie"
                    >
                        <IconoTipo :nombre="vencimiento.icono" clase="size-4 shrink-0 text-destructive" />
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate text-sm font-medium">{{ vencimiento.titulo }}</span>
                            <span class="truncate text-xs text-muted-foreground">{{ meta(vencimiento) }}</span>
                        </span>
                        <span class="cifra shrink-0 text-xs text-destructive">{{ hace(vencimiento.dias) }}</span>
                        <span class="sr-only">· {{ vencimiento.estadoEtiqueta }}</span>
                    </Link>
                </li>
            </ul>

            <button
                v-if="vencidos.length > TOPE"
                type="button"
                class="mt-1 inline-flex min-h-11 items-center gap-1 self-start rounded-md px-2 text-xs font-medium text-primary hover:underline sm:min-h-8"
                :aria-expanded="desplegado"
                @click="desplegado = !desplegado"
            >
                <ChevronDownIcon class="size-4 transition-transform" :class="desplegado ? 'rotate-180' : ''" aria-hidden="true" />
                {{ desplegado ? 'Ver menos' : `Ver los ${vencidos.length}` }}
            </button>
        </div>
    </section>
</template>
