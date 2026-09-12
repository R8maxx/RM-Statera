<script setup lang="ts">
import {
    AppWindowIcon,
    ArchiveIcon,
    Building2Icon,
    DatabaseIcon,
    GlobeIcon,
    HardDriveIcon,
    ListTodoIcon,
    NetworkIcon,
    PaperclipIcon,
    PlugIcon,
    UsersIcon,
} from '@lucide/vue';
import { computed, type Component } from 'vue';

/**
 * El icono de un tipo, resuelto por nombre.
 *
 * Lo usan la tipología de activos y las fuentes de un vencimiento. El nombre lo
 * decide el servidor —el enum— y aquí sólo se resuelve.
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

    // Las fuentes de un vencimiento en el calendario: ahí el icono hace el mismo
    // trabajo que aquí —distinguir de qué es cada cosa cuando el color agrupa—,
    // y § 4.16 irá añadiendo las suyas.
    ListTodo: ListTodoIcon,
    Paperclip: PaperclipIcon,
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
