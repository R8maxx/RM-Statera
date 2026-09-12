<script setup lang="ts">
import TarjetaTarea, { type Tarjeta } from '@/components/tarea/TarjetaTarea.vue';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Link } from '@inertiajs/vue3';
import { dropTargetForElements } from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { onBeforeUnmount, onMounted, ref } from 'vue';

export interface Columna {
    estado: string;
    etiqueta: string;
    tono: string;
    total: number;
    ocultas: number;
    filtro: string;
    tarjetas: Tarjeta[];
}

const props = defineProps<{
    columna: Columna;
    etiquetas: Record<string, string>;
}>();

const emit = defineEmits<{
    mover: [id: number, estado: string];
    descartar: [id: number, titulo: string];
}>();

/*
 * Escritas enteras y nunca compuestas: Tailwind analiza el fichero como texto,
 * y una clase construida en ejecución —`bg-estado-${tono}`— no se genera y el
 * punto sale sin color. Misma regla que en `CeldaBadge`.
 */
const puntos: Record<string, string> = {
    no_iniciado: 'bg-estado-no-iniciado',
    en_progreso: 'bg-estado-en-progreso',
    planificado: 'bg-estado-planificado',
    implantado: 'bg-estado-implantado',
    no_aplica: 'bg-estado-no-aplica',
};

const elemento = ref<HTMLElement>();

/** Encima y admite la tarjeta que se arrastra. */
const receptiva = ref(false);

/** Encima pero NO la admite: el dominio no permite esa transición. */
const cerrada = ref(false);

let limpiar: (() => void) | null = null;

onMounted(() => {
    if (!elemento.value) {
        return;
    }

    limpiar = dropTargetForElements({
        element: elemento.value,
        getData: () => ({ estado: props.columna.estado }),

        /*
         * El destino se decide con lo que el servidor mandó en la tarjeta
         * (`transiciones`), no con una regla escrita otra vez aquí. Así una
         * columna prohibida se marca como tal **durante** el arrastre, en vez de
         * aceptar el soltado y fallar después con un mensaje.
         *
         * El servidor lo vuelve a comprobar igual —`CambiarEstadoTarea` es quien
         * manda—: esto es para que el gesto no mienta, no para fiarse del
         * navegador.
         */
        canDrop: ({ source }) => {
            const destinos = source.data.transiciones;

            return Array.isArray(destinos) && destinos.includes(props.columna.estado);
        },

        onDragEnter: ({ source }) => {
            const destinos = source.data.transiciones;
            const admite = Array.isArray(destinos) && destinos.includes(props.columna.estado);

            receptiva.value = admite;
            cerrada.value = !admite && source.data.desde !== props.columna.estado;
        },
        onDragLeave: () => {
            receptiva.value = false;
            cerrada.value = false;
        },
        onDrop: ({ source }) => {
            receptiva.value = false;
            cerrada.value = false;

            const id = source.data.tareaId;

            if (typeof id === 'number' && source.data.desde !== props.columna.estado) {
                emit('mover', id, props.columna.estado);
            }
        },
    });
});

onBeforeUnmount(() => limpiar?.());
</script>

<template>
    <section
        ref="elemento"
        class="flex min-h-0 flex-col rounded-xl border bg-muted/30 transition-colors"
        :class="{
            'border-primary bg-accent/50': receptiva,
            'opacity-50': cerrada,
        }"
        :aria-label="`${columna.etiqueta}: ${columna.total}`"
    >
        <header class="flex items-baseline justify-between gap-2 px-3 py-2.5">
            <h2 class="flex items-center gap-2 text-sm font-medium">
                <span
                    class="size-1.5 rounded-full"
                    :class="puntos[columna.tono] ?? 'bg-muted-foreground'"
                    aria-hidden="true"
                />
                {{ columna.etiqueta }}
            </h2>
            <span class="cifra text-xs text-muted-foreground">{{ columna.total }}</span>
        </header>

        <ScrollArea class="min-h-0 flex-1 px-2 pb-2">
            <div class="space-y-2">
                <TarjetaTarea
                    v-for="tarjeta in columna.tarjetas"
                    :key="tarjeta.id"
                    :tarjeta="tarjeta"
                    :etiquetas="etiquetas"
                    @mover="(id, estado) => emit('mover', id, estado)"
                    @descartar="(id, titulo) => emit('descartar', id, titulo)"
                />

                <p v-if="columna.tarjetas.length === 0" class="px-1 py-6 text-center text-xs text-muted-foreground">
                    Nada aquí.
                </p>

                <!--
                    Un tope por columna, con la cuenta real y su salida. Meter
                    quinientas tarjetas en el DOM no es un tablero, es una lista
                    lenta; la lista de verdad está en la tabla.
                -->
                <Link
                    v-if="columna.ocultas > 0"
                    :href="`/tareas?${columna.filtro}`"
                    class="block rounded-md px-1 py-2 text-center text-xs text-muted-foreground underline-offset-4 hover:underline"
                >
                    y {{ columna.ocultas }} más →
                </Link>
            </div>
        </ScrollArea>
    </section>
</template>
