<script setup lang="ts">
import {
    CircleDotIcon,
    ExternalLinkIcon,
    EyeIcon,
    ListChecksIcon,
    PencilIcon,
    PlusIcon,
    SlidersHorizontalIcon,
    Trash2Icon,
} from '@lucide/vue';
import { computed, type Component } from 'vue';

/**
 * Mapa explícito de iconos.
 *
 * Importar todo `@lucide/vue` para resolver el nombre en tiempo de ejecución
 * arrastraría el paquete entero al bundle. Los iconos que puede pedir un
 * `Accion` se declaran aquí, y lo que no esté no pinta nada.
 */
const iconos: Record<string, Component> = {
    CircleDot: CircleDotIcon,
    ExternalLink: ExternalLinkIcon,
    Eye: EyeIcon,
    ListChecks: ListChecksIcon,
    Pencil: PencilIcon,
    Plus: PlusIcon,
    SlidersHorizontal: SlidersHorizontalIcon,
    Trash2: Trash2Icon,
};

const props = defineProps<{ nombre: string | null }>();

const icono = computed(() => (props.nombre ? iconos[props.nombre] : undefined));
</script>

<template>
    <component :is="icono" v-if="icono" class="size-4" />
</template>
