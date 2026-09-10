<script setup lang="ts">
import {
    AppWindowIcon,
    ArchiveIcon,
    Building2Icon,
    DatabaseIcon,
    GlobeIcon,
    HardDriveIcon,
    NetworkIcon,
    PlugIcon,
    UsersIcon,
} from '@lucide/vue';
import { computed, type Component } from 'vue';

/**
 * El icono de un tipo de activo, resuelto por nombre.
 *
 * Mapa explícito, como `IconoAccion`: importar `@lucide/vue` entero para
 * resolver el nombre en tiempo de ejecución arrastraría el paquete al bundle.
 *
 * Este icono **no es decoración**. Nueve tipos no caben en el hueco de tono que
 * dejan los estados manteniendo ΔE 6 entre ellos —la peor pareja queda en 5.2,
 * medido en DESIGN.md §3—, así que el color agrupa y el icono identifica.
 * Quitarlo deja la distinción por debajo del umbral.
 */
const iconos: Record<string, Component> = {
    AppWindow: AppWindowIcon,
    Archive: ArchiveIcon,
    Building2: Building2Icon,
    Database: DatabaseIcon,
    Globe: GlobeIcon,
    HardDrive: HardDriveIcon,
    Network: NetworkIcon,
    Plug: PlugIcon,
    Users: UsersIcon,
};

const props = withDefaults(defineProps<{ nombre?: string | null; clase?: string }>(), {
    nombre: null,
    clase: 'size-3.5',
});

const icono = computed(() => (props.nombre ? iconos[props.nombre] : undefined));
</script>

<template>
    <component :is="icono" v-if="icono" :class="clase" aria-hidden="true" />
</template>
