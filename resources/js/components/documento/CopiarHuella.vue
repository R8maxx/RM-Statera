<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { CheckIcon, CopyIcon } from '@lucide/vue';
import { ref } from 'vue';

/**
 * Copia la huella SHA-256 **entera** al portapapeles.
 *
 * La entera y no el prefijo que se enseña en la tabla: es lo que se contrasta
 * con el fichero que se le entrega al auditor, y media huella no contrasta nada.
 */
const props = defineProps<{ huella: string; etiqueta: string }>();

const copiada = ref(false);

async function copiar(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.huella);
        copiada.value = true;
        window.setTimeout(() => (copiada.value = false), 2000);
    } catch {
        // Sin portapapeles —contexto no seguro, permiso denegado— la huella
        // sigue estando a la vista y se puede seleccionar a mano.
    }
}
</script>

<template>
    <Button variant="ghost" size="icon" :aria-label="`Copiar la huella SHA-256 de ${etiqueta}`" @click="copiar">
        <CheckIcon v-if="copiada" class="size-4 text-estado-implantado" />
        <CopyIcon v-else class="size-4" />
    </Button>
</template>
