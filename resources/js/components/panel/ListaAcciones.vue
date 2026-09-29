<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2Icon, ChevronRightIcon } from '@lucide/vue';
import { type Component, computed } from 'vue';

export interface Accion {
    clave: string;
    etiqueta: string;
    /** Una frase que dice por qué importa, o qué la acompaña. */
    detalle?: string | null;
    valor: number;
    href: string;
    icono: Component;
    alerta?: boolean;
}

/**
 * La columna de lo que falta, al lado de la cifra fuerte de una vista.
 *
 * Es la otra mitad de la tarjeta principal: la cifra dice cómo vamos y esto dice
 * qué hacer, con cada fila llevando a la lista exacta que cuenta. Antes esas
 * cifras vivían en otra tarjeta, más abajo y del mismo peso que todo lo demás, y
 * había que ir a buscarlas.
 *
 * **Lo que está a cero no se pinta**, por lo mismo que en `TarjetaRegistro`. Y
 * el icono va siempre con el texto: el rojo sólo agrupa (DESIGN.md § 3).
 */
const props = defineProps<{
    titulo: string;
    descripcion?: string;
    acciones: Accion[];
    /** Lo que se dice cuando no falta nada. */
    vacio: string;
}>();

const visibles = computed(() => props.acciones.filter((accion) => accion.valor > 0));
</script>

<template>
    <div class="flex flex-col gap-3">
        <div>
            <h2 class="text-base font-semibold tracking-[-0.01em]">{{ titulo }}</h2>
            <p v-if="descripcion" class="mt-1 text-sm text-muted-foreground">{{ descripcion }}</p>
        </div>

        <ul v-if="visibles.length > 0">
            <li v-for="accion in visibles" :key="accion.clave" class="border-b last:border-b-0">
                <Link
                    :href="accion.href"
                    class="group -mx-2 flex min-h-14 items-center gap-3 rounded-md px-2 py-2 transition-colors hover:bg-fila-hover"
                >
                    <component
                        :is="accion.icono"
                        class="size-4.5 shrink-0"
                        :class="accion.alerta ? 'text-destructive' : 'text-muted-foreground'"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium">{{ accion.etiqueta }}</span>
                        <span v-if="accion.detalle" class="block text-xs text-muted-foreground">{{ accion.detalle }}</span>
                    </span>
                    <Cifra
                        class="cifra text-xl font-semibold"
                        :class="accion.alerta && 'text-destructive'"
                        :valor="accion.valor"
                    />
                    <ChevronRightIcon
                        class="size-4 shrink-0 text-muted-foreground/60 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </li>
        </ul>

        <p v-else class="flex items-center gap-2 text-sm text-muted-foreground">
            <CheckCircle2Icon class="size-4 shrink-0 text-estado-implantado" aria-hidden="true" />
            {{ vacio }}
        </p>
    </div>
</template>
