<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link } from '@inertiajs/vue3';
import { CheckSquareIcon, EllipsisVerticalIcon, GripVerticalIcon, PaperclipIcon } from '@lucide/vue';
import { draggable } from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { onBeforeUnmount, onMounted, ref } from 'vue';

export interface Tarjeta {
    id: number;
    titulo: string;
    estado: string;
    prioridad: string;
    prioridadEtiqueta: string;
    prioridadTono: string;
    responsable: string | null;
    plazo: { etiqueta: string; tono: string; fecha: string | null };
    requisitos: number;
    pasos: number;
    pasosHechos: number;
    /** A qué columnas puede ir esta tarjeta. Lo decide el dominio, no el cliente. */
    transiciones: string[];
    descartable: boolean;
}

const props = defineProps<{
    tarjeta: Tarjeta;
    /** Cómo se llama cada estado, para el menú. */
    etiquetas: Record<string, string>;
}>();

const emit = defineEmits<{
    mover: [id: number, estado: string];
    descartar: [id: number, titulo: string];
}>();

const elemento = ref<HTMLElement>();
const arrastrando = ref(false);

/**
 * El arrastre es un añadido, no el mecanismo.
 *
 * DESIGN.md § 11 exige que todo sea accionable por teclado, y ninguna librería
 * de arrastrar y soltar lo da gratis. El menú de la tarjeta —que ofrece
 * exactamente los mismos destinos— es el camino canónico: funciona con teclado,
 * en táctil y con lector de pantalla. Esto de aquí es para quien tiene ratón.
 */
onMounted(() => {
    if (!elemento.value) {
        return;
    }

    limpiar = draggable({
        element: elemento.value,
        getInitialData: () => ({
            tareaId: props.tarjeta.id,
            desde: props.tarjeta.estado,
            transiciones: props.tarjeta.transiciones,
        }),
        onDragStart: () => (arrastrando.value = true),
        onDrop: () => (arrastrando.value = false),
    });
});

let limpiar: (() => void) | null = null;

onBeforeUnmount(() => limpiar?.());
</script>

<template>
    <article
        ref="elemento"
        class="group rounded-xl border bg-card p-3 transition-shadow"
        :class="arrastrando ? 'opacity-40' : 'hover:shadow-sombra-1'"
    >
        <div class="flex items-start gap-1.5">
            <!--
                El asa existe para decir que esto se arrastra. No es el único
                camino: el menú de al lado hace lo mismo sin ratón.
            -->
            <GripVerticalIcon
                class="mt-0.5 size-4 shrink-0 cursor-grab text-muted-foreground/40 transition-colors group-hover:text-muted-foreground"
                aria-hidden="true"
            />

            <Link :href="`/tareas/${tarjeta.id}`" class="min-w-0 flex-1 text-sm font-medium hover:underline">
                {{ tarjeta.titulo }}
            </Link>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon-xs" :aria-label="`Mover «${tarjeta.titulo}»`">
                        <EllipsisVerticalIcon class="size-4" />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Mover a</DropdownMenuLabel>

                    <!-- Sólo los destinos que el dominio permite: una opción que
                         va a fallar no debería poder elegirse. -->
                    <DropdownMenuItem
                        v-for="destino in tarjeta.transiciones"
                        :key="destino"
                        @select="emit('mover', tarjeta.id, destino)"
                    >
                        {{ etiquetas[destino] ?? destino }}
                    </DropdownMenuItem>

                    <template v-if="tarjeta.descartable">
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @select="emit('descartar', tarjeta.id, tarjeta.titulo)">
                            Descartar…
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-1.5 pl-5.5">
            <CeldaBadge
                :valor="{ valor: tarjeta.plazo.fecha, etiqueta: tarjeta.plazo.etiqueta, tono: tarjeta.plazo.tono }"
            />
            <CeldaBadge
                :valor="{ valor: tarjeta.prioridad, etiqueta: tarjeta.prioridadEtiqueta, tono: tarjeta.prioridadTono }"
            />
        </div>

        <p class="mt-2 flex items-center gap-2 pl-5.5 text-xs text-muted-foreground">
            <span class="truncate">{{ tarjeta.responsable ?? 'Sin responsable' }}</span>
            <span v-if="tarjeta.requisitos > 0" class="flex shrink-0 items-center gap-1">
                <PaperclipIcon class="size-3" aria-hidden="true" />
                {{ tarjeta.requisitos }}
            </span>

            <!-- Con cero pasos no se pinta nada: un «0/0» es ruido. -->
            <span
                v-if="tarjeta.pasos > 0"
                class="cifra flex shrink-0 items-center gap-1"
                :title="`${tarjeta.pasosHechos} de ${tarjeta.pasos} pasos hechos`"
            >
                <CheckSquareIcon class="size-3" aria-hidden="true" />
                {{ tarjeta.pasosHechos }}/{{ tarjeta.pasos }}
            </span>
        </p>
    </article>
</template>
