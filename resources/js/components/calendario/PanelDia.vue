<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { formatoFecha } from '@/lib/celdas';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type Vencimiento = App.Domain.Aviso.Vencimiento;

/**
 * Todo lo que cae un día, cuando no cabe en la casilla.
 *
 * **Era un `<p>` muerto** —«y 4 más», sin decir qué son y sin llevar a ninguna
 * parte—, y con tantas fuentes salta constantemente. Es el mismo callejón sin
 * salida que el panel cerró para sus cifras: un número que no lleva a la lista no
 * se acciona, se mira.
 *
 * Arregla además un fallo de accesibilidad que ya existía: un párrafo no es
 * alcanzable con el tabulador, así que lo que el tope escondía **no tenía ninguna
 * otra puerta**. Aquí el estado va visible y no en `sr-only`, porque hay sitio y
 * § 11 pide los tres canales.
 */
const props = defineProps<{
    dia: string;
    vencimientos: Vencimiento[];
    ocultos: number;
}>();

const fechaLarga = computed(() => formatoFecha.format(new Date(`${props.dia}T00:00:00`)));

const claseDe = (vencimiento: Vencimiento): string => tono(vencimiento.estadoTono).badge;
</script>

<template>
    <Popover>
        <PopoverTrigger
            class="mt-1 rounded-sm px-1 text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            y {{ ocultos }} más
        </PopoverTrigger>

        <PopoverContent class="w-80 p-3">
            <p class="mb-2 text-sm font-medium">{{ fechaLarga }}</p>

            <ul class="space-y-1">
                <li v-for="vencimiento in vencimientos" :key="`${vencimiento.fuente}-${vencimiento.id}`">
                    <Link
                        :href="vencimiento.url"
                        class="flex min-h-11 items-center gap-2 rounded-md px-2 text-sm transition-colors hover:bg-muted"
                    >
                        <IconoTipo :nombre="vencimiento.icono" />
                        <span class="min-w-0 flex-1 truncate">{{ vencimiento.titulo }}</span>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs"
                            :class="claseDe(vencimiento)"
                        >
                            {{ vencimiento.estadoEtiqueta }}
                        </span>
                    </Link>
                </li>
            </ul>
        </PopoverContent>
    </Popover>
</template>
