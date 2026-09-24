<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Link, router } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';

/**
 * La barra de acciones pegada al pie de la ventana.
 *
 * Los formularios de este dominio son largos —una valoración de cinco
 * dimensiones con su justificación no cabe en una pantalla— y bajar hasta el
 * final para guardar es trabajo que no aporta nada.
 *
 * Vive aparte de `FormularioRecurso` porque no todas las pantallas de este tipo
 * son un `<Form>`: la valoración necesita estado controlado para derivar la
 * categoría en vivo y para previsualizar el recálculo antes de aplicarlo, pero
 * su pie tiene que ser el mismo pie.
 *
 * **Cancelar pregunta cuando hay cambios.** Perder veintiocho campos por pulsar
 * el botón de al lado del que se quería es la clase de error que no se recupera.
 * Sólo se cubren las dos vías reales de perderlos —Cancelar y cerrar la
 * pestaña—: interceptar toda navegación exigiría `router.on('before')`, que es
 * síncrono y no admite esperar a un diálogo, y la vuelta que hay que darle para
 * que lo admita es más máquina de estados de la que este problema pide hoy.
 */
const props = defineProps<{
    urlCancelar: string;
    etiquetaCancelar?: string;
    /** Hay cambios sin guardar. Sin esto, Cancelar navega directo. */
    sucio?: boolean;
    /** El formulario se está enviando: ahí salir no es perder nada. */
    enviando?: boolean;
}>();

const confirmando = ref(false);

function hayCambios(): boolean {
    return props.sucio === true && props.enviando !== true;
}

/**
 * Se intercepta en fase de captura, y no con un `@click` normal sobre el
 * `<Link>`: en burbujeo competiríamos con el manejador propio de Inertia y el
 * orden depende de cómo se mezclen los oyentes. Parándolo antes de que llegue al
 * enlace, la navegación no se llega a plantear.
 */
function alCancelar(evento: MouseEvent): void {
    if (!hayCambios()) {
        return;
    }

    evento.preventDefault();
    evento.stopPropagation();
    confirmando.value = true;
}

function salir(): void {
    confirmando.value = false;
    router.visit(props.urlCancelar);
}

/* Cerrar la pestaña o recargar: el navegador pone su propio texto. */
function alDescargar(evento: BeforeUnloadEvent): void {
    evento.preventDefault();
}

watch(
    () => hayCambios(),
    (avisar) => {
        if (avisar) {
            window.addEventListener('beforeunload', alDescargar);
        } else {
            window.removeEventListener('beforeunload', alDescargar);
        }
    },
);

onUnmounted(() => window.removeEventListener('beforeunload', alDescargar));
</script>

<template>
    <!--
        Pegajosa dentro de su formulario, no fija a la ventana. Era `fixed
        inset-x-0` con un `mx-auto max-w-4xl` dentro, así que se centraba
        respecto al viewport y no respecto al formulario: a 1920 quedaba
        desplazada del botón que acompañaba, y en los formularios anchos era
        más estrecha que ellos. Pegajosa, hereda el ancho de quien la contiene.
    -->
    <div
        class="sticky bottom-0 z-(--z-barra-acciones) mt-8 border-t bg-background/90 py-3 backdrop-blur-sm"
    >
        <!-- Los dos botones van juntos, siempre: a 375 px el envío caía solo a
             una segunda línea, lejos de «Cancelar». Lo que parte línea es la
             nota, que por debajo de `sm` ocupa la fila entera. -->
        <div class="flex flex-wrap items-center gap-x-2 gap-y-2">
            <div class="min-w-0 basis-full text-sm text-muted-foreground empty:hidden sm:flex-1 sm:basis-auto">
                <slot name="nota" />
            </div>

            <div class="ml-auto flex items-center gap-2">
                <span @click.capture="alCancelar">
                    <Button as-child variant="ghost">
                        <Link :href="urlCancelar">{{ etiquetaCancelar ?? 'Cancelar' }}</Link>
                    </Button>
                </span>

                <slot />
            </div>
        </div>
    </div>

    <Dialog v-model:open="confirmando">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Hay cambios sin guardar</DialogTitle>
                <DialogDescription>
                    Si sales ahora se pierde lo que has escrito. No se ha guardado nada todavía.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="confirmando = false">Seguir editando</Button>
                <Button type="button" variant="destructive" @click="salir">Salir sin guardar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
