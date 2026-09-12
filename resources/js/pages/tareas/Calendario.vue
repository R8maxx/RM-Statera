<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ConmutadorVista from '@/components/tarea/ConmutadorVista.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import { Link } from '@inertiajs/vue3';
import { ChevronLeftIcon, ChevronRightIcon } from '@lucide/vue';
import { computed } from 'vue';

type Vencimiento = App.Domain.Aviso.Vencimiento;

interface Dia {
    dia: string;
    numero: number;
    delMes: boolean;
    esHoy: boolean;
    finDeSemana: boolean;
}

const props = defineProps<{
    rejilla: {
        mes: string;
        etiqueta: string;
        anterior: string;
        siguiente: string;
        primerDia: string;
        ultimoDia: string;
        dias: Dia[];
    };
    vencimientos: Vencimiento[];
}>();

const cabeceras = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

/** Lo que cae cada día, indexado por `Y-m-d`. */
const porDia = computed(() => {
    const mapa = new Map<string, Vencimiento[]>();

    for (const vencimiento of props.vencimientos) {
        const dia = mapa.get(vencimiento.dia);

        if (dia === undefined) {
            mapa.set(vencimiento.dia, [vencimiento]);
        } else {
            dia.push(vencimiento);
        }
    }

    return mapa;
});

const del = (dia: string): Vencimiento[] => porDia.value.get(dia) ?? [];

/*
 * El rojo sólo donde ya se ha pasado la fecha, igual que en la tabla. Las clases
 * van escritas enteras: Tailwind analiza el fichero como texto y una compuesta
 * en ejecución no se genera.
 */
const tonos: Record<string, string> = {
    caducada: 'border-destructive/30 bg-destructive/5 text-destructive',
    en_progreso: 'border-estado-en-progreso/30 bg-estado-en-progreso-suave text-estado-en-progreso',
    implantado: 'border-border bg-muted/50 text-foreground',
};

const claseDe = (vencimiento: Vencimiento): string => tonos[vencimiento.tono] ?? tonos.implantado;

/** Sólo los días con algo, para la agenda de móvil. */
const agenda = computed(() =>
    props.rejilla.dias
        .filter((dia) => del(dia.dia).length > 0)
        .map((dia) => ({ ...dia, vencimientos: del(dia.dia) })),
);

const fechaLarga = (dia: string): string => formatoFecha.format(new Date(`${dia}T00:00:00`));
</script>

<template>
    <AppLayout titulo="Calendario">
        <CabeceraPagina
            titulo="Calendario"
            descripcion="Qué hay que atender y cuándo: plazos de tareas y caducidades de evidencias en el mismo sitio."
        >
            <template #acciones>
                <ConmutadorVista />
            </template>
        </CabeceraPagina>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <Link :href="`/tareas/calendario?mes=${rejilla.anterior}`">
                <Button variant="outline" size="icon-sm" aria-label="Mes anterior">
                    <ChevronLeftIcon class="size-4" />
                </Button>
            </Link>

            <h2 class="min-w-48 text-base font-medium">{{ rejilla.etiqueta }}</h2>

            <Link :href="`/tareas/calendario?mes=${rejilla.siguiente}`">
                <Button variant="outline" size="icon-sm" aria-label="Mes siguiente">
                    <ChevronRightIcon class="size-4" />
                </Button>
            </Link>

            <Link href="/tareas/calendario" class="ml-1">
                <Button variant="ghost" size="sm">Hoy</Button>
            </Link>
        </div>

        <!--
            La rejilla desde `md`. Siete columnas a 400 px no se leen: por debajo
            va la agenda, que es la misma información en la forma que cabe.
        -->
        <div class="hidden md:block">
            <div class="grid grid-cols-7 gap-px rounded-xl border bg-border">
                <div
                    v-for="(inicial, indice) in cabeceras"
                    :key="inicial"
                    class="bg-card py-2 text-center text-xs font-medium text-muted-foreground"
                    :class="indice >= 5 ? 'text-muted-foreground/60' : ''"
                >
                    {{ inicial }}
                </div>

                <div
                    v-for="dia in rejilla.dias"
                    :key="dia.dia"
                    class="min-h-24 bg-card p-1.5"
                    :class="[
                        dia.delMes ? '' : 'bg-muted/40',
                        dia.finDeSemana && dia.delMes ? 'bg-muted/20' : '',
                    ]"
                >
                    <p class="mb-1 text-right text-xs">
                        <span
                            class="inline-flex size-5 items-center justify-center rounded-full"
                            :class="[
                                dia.esHoy ? 'bg-primary font-medium text-primary-foreground' : '',
                                dia.delMes ? 'text-foreground' : 'text-muted-foreground/50',
                            ]"
                        >
                            {{ dia.numero }}
                        </span>
                    </p>

                    <ul class="space-y-1">
                        <li v-for="vencimiento in del(dia.dia)" :key="`${vencimiento.fuente}-${vencimiento.id}`">
                            <Link
                                :href="vencimiento.url"
                                class="flex items-center gap-1 rounded border px-1.5 py-1 text-xs transition-colors hover:brightness-95"
                                :class="claseDe(vencimiento)"
                                :title="`${vencimiento.titulo} · ${vencimiento.fecha}`"
                            >
                                <IconoTipo :nombre="vencimiento.icono" />
                                <span class="truncate">{{ vencimiento.titulo }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- La agenda: la misma información, en la forma que cabe en un móvil. -->
        <div class="md:hidden">
            <p v-if="agenda.length === 0" class="rounded-xl border p-6 text-center text-sm text-muted-foreground">
                No vence nada este mes.
            </p>

            <ol v-else class="space-y-4">
                <li v-for="dia in agenda" :key="dia.dia">
                    <h3 class="mb-1.5 text-sm font-medium" :class="dia.esHoy ? 'text-primary' : ''">
                        {{ fechaLarga(dia.dia) }}
                        <span v-if="dia.esHoy" class="text-xs font-normal">· hoy</span>
                    </h3>

                    <ul class="space-y-1.5">
                        <li v-for="vencimiento in dia.vencimientos" :key="`${vencimiento.fuente}-${vencimiento.id}`">
                            <Link
                                :href="vencimiento.url"
                                class="flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm"
                                :class="claseDe(vencimiento)"
                            >
                                <IconoTipo :nombre="vencimiento.icono" />
                                <span class="min-w-0 flex-1 truncate">{{ vencimiento.titulo }}</span>
                                <span class="shrink-0 text-xs">{{ vencimiento.responsable ?? 'sin asignar' }}</span>
                            </Link>
                        </li>
                    </ul>
                </li>
            </ol>
        </div>

        <p class="mt-4 text-xs text-muted-foreground">
            Se ven los plazos de las tareas abiertas y las caducidades de las evidencias. Cuando lleguen los
            demás vencimientos periódicos —revisión por la dirección, auditoría interna— aparecerán aquí.
        </p>
    </AppLayout>
</template>
