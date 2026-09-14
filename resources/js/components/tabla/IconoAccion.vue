<script setup lang="ts">
import {
    CalendarCheckIcon,
    CircleDotIcon,
    ExternalLinkIcon,
    EyeIcon,
    ListChecksIcon,
    PencilIcon,
    PlusIcon,
    QrCodeIcon,
    ScaleIcon,
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
    CalendarCheck: CalendarCheckIcon,
    CircleDot: CircleDotIcon,
    ExternalLink: ExternalLinkIcon,
    Eye: EyeIcon,
    ListChecks: ListChecksIcon,
    Pencil: PencilIcon,
    Plus: PlusIcon,
    QrCode: QrCodeIcon,
    // La balanza: es el símbolo de la marca, y aquí lleva a la metodología, que
    // es literalmente dónde la organización fija el fiel de su balanza.
    Scale: ScaleIcon,
    SlidersHorizontal: SlidersHorizontalIcon,
    Trash2: Trash2Icon,
};

const props = defineProps<{ nombre: string | null }>();

const icono = computed(() => (props.nombre ? iconos[props.nombre] : undefined));
</script>

<template>
    <component :is="icono" v-if="icono" class="size-4" />
</template>
