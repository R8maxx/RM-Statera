<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DownloadIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * El PDF de verdad, dentro de la aplicación.
 *
 * El editor pinta la hoja con la misma hoja de estilos que se imprime, así que
 * la tipografía, el color y la medida ya coinciden. Lo que **no** puede saber es
 * dónde corta Chromium: la paginación, las viudas, la cabecera de tabla repetida
 * en cada hoja y el pie con su «página N de M». Eso es lo que se mira aquí antes
 * de emitir.
 *
 * Un `<iframe>` y no un visor propio: el del navegador ya sabe paginar, buscar,
 * ampliar e imprimir, y meter un PDF.js en una herramienta que entra en el
 * alcance de su propio SGSI es una dependencia que habría que justificar.
 */
const props = defineProps<{
    abierto: boolean;
    documentoId: number;
    versionId: number | null;
    /** Cuenta de generaciones: fuerza a recargar el `iframe` con el PDF nuevo. */
    sello: number;
}>();

const emit = defineEmits<{ 'update:abierto': [boolean] }>();

/*
 * El sello va en la URL para que el navegador no sirva el PDF anterior de su
 * caché: regenerar escribe una clave nueva en el disco, pero la ruta que la
 * redirige es la misma y eso basta para que alguien mire el borrador de hace
 * media hora creyendo que mira el suyo.
 */
const fuente = computed(() =>
    props.versionId === null
        ? ''
        : `/documentos/${props.documentoId}/versiones/${props.versionId}/ver?v=${props.sello}`,
);
</script>

<template>
    <Dialog :open="abierto" @update:open="emit('update:abierto', $event)">
        <DialogContent class="flex h-[92vh] max-w-[min(96vw,1400px)] flex-col gap-0 p-0 sm:max-w-[min(96vw,1400px)]">
            <DialogHeader class="border-b border-border px-6 py-4">
                <DialogTitle>Cómo se imprime</DialogTitle>
                <DialogDescription>
                    El borrador tal cual sale de Gotenberg: con sus saltos de página, su cabecera y su
                    pie. Es lo único que el editor no puede enseñar.
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 bg-muted">
                <iframe
                    v-if="fuente !== ''"
                    :src="fuente"
                    title="Borrador del documento en PDF"
                    class="size-full border-0"
                />
            </div>

            <div class="flex justify-end border-t border-border px-6 py-3">
                <Button v-if="versionId !== null" variant="outline" as-child>
                    <a :href="`/documentos/${documentoId}/versiones/${versionId}/descargar`">
                        <DownloadIcon class="size-4" />
                        Descargar
                    </a>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
