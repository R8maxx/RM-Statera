<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';
import { XIcon } from '@lucide/vue';
import { computed, onMounted, useTemplateRef } from 'vue';

type Vencimiento = App.Domain.Aviso.Vencimiento;

/**
 * Todo lo que cae un día, en un panel al lado de la rejilla.
 *
 * **Era un popover colgado de «y 4 más»**, y antes de eso un `<p>` muerto. El
 * popover cerraba el callejón —lo que el tope escondía tenía por fin puerta—,
 * pero tapaba la casilla que lo abría y se cerraba al primer clic fuera, así que
 * comparar dos días seguidos era abrir, cerrar y volver a abrir. El panel se
 * queda: se cambia de día pulsando otro número, con el mes a la vista.
 *
 * **No es modal**, y por eso no atrapa el foco ni pone velo: la rejilla sigue
 * viva debajo. Al abrirse, el foco va al título, que es lo que el lector de
 * pantalla tiene que leer primero; Escape lo cierra, y quien lo abrió recupera el
 * foco (eso lo hace la página, que sabe desde dónde se abrió).
 *
 * Aquí el estado va visible y no en `sr-only`: hay sitio, y § 11 pide los tres
 * canales.
 */
const props = defineProps<{
    dia: string;
    vencimientos: Vencimiento[];
}>();

const emit = defineEmits<{ cerrar: [] }>();

/** «Martes, 29 de septiembre de 2026»: el día entero, que es de lo que habla el panel. */
const formatoDia = new Intl.DateTimeFormat('es-ES', { dateStyle: 'full' });

const titulo = useTemplateRef<HTMLHeadingElement>('titulo');

const fechaLarga = computed(() => {
    const texto = formatoDia.format(new Date(`${props.dia}T00:00:00`));

    return texto.charAt(0).toUpperCase() + texto.slice(1);
});

const resumen = computed(() => {
    const cuantos = props.vencimientos.length;

    return cuantos === 1 ? '1 vencimiento' : `${cuantos} vencimientos`;
});

const claseDe = (vencimiento: Vencimiento): string => tono(vencimiento.estadoTono).badge;

const tintaDe = (vencimiento: Vencimiento): string => tono(vencimiento.estadoTono).texto ?? 'text-muted-foreground';

const iconoDeEstado = (vencimiento: Vencimiento): string | null => tono(vencimiento.estadoTono).icono;

onMounted(() => titulo.value?.focus());
</script>

<template>
    <aside
        aria-labelledby="panel-dia-titulo"
        class="flex max-h-full w-96 max-w-full flex-col overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-sombra-3"
        @keydown.esc.stop="emit('cerrar')"
    >
        <div class="flex items-start gap-3 border-b py-3 pr-3 pl-5">
            <div class="flex min-w-0 flex-1 flex-col gap-0.5 pt-1">
                <h3
                    id="panel-dia-titulo"
                    ref="titulo"
                    tabindex="-1"
                    class="text-base font-semibold tracking-tight outline-none"
                >
                    {{ fechaLarga }}
                </h3>
                <p class="text-[13px] text-muted-foreground">{{ resumen }}</p>
            </div>

            <button
                type="button"
                aria-label="Cerrar el día"
                class="inline-flex size-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                @click="emit('cerrar')"
            >
                <XIcon class="size-4" aria-hidden="true" />
            </button>
        </div>

        <p v-if="vencimientos.length === 0" class="px-5 py-6 text-sm text-muted-foreground">
            Nada vence este día.
        </p>

        <ul v-else class="flex flex-col gap-0.5 overflow-y-auto p-2">
            <li v-for="vencimiento in vencimientos" :key="`${vencimiento.fuente}-${vencimiento.id}`">
                <Link
                    :href="vencimiento.url"
                    class="flex items-start gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-superficie"
                >
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-md" :class="claseDe(vencimiento)">
                        <IconoTipo :nombre="vencimiento.icono" :clase="`size-4 ${tintaDe(vencimiento)}`" />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="text-sm font-medium">{{ vencimiento.titulo }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ vencimiento.fuenteEtiqueta }} · {{ vencimiento.responsable ?? 'Sin responsable' }}
                        </span>
                        <span
                            class="inline-flex h-5 items-center gap-1 self-start rounded-full px-2 text-xs font-medium"
                            :class="claseDe(vencimiento)"
                        >
                            <IconoTipo :nombre="iconoDeEstado(vencimiento)" clase="size-3" />
                            {{ vencimiento.estadoEtiqueta }}
                        </span>
                    </span>
                </Link>
            </li>
        </ul>
    </aside>
</template>
