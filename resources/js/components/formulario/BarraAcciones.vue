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
    <div
        class="fixed inset-x-0 bottom-0 z-20 border-t bg-background/90 px-4 py-3 backdrop-blur-sm sm:px-6 lg:px-8"
    >
        <div class="mx-auto flex max-w-4xl items-center gap-2">
            <div class="min-w-0 flex-1 text-sm text-muted-foreground">
                <slot name="nota" />
            </div>

            <span @click.capture="alCancelar">
                <Link :href="urlCancelar">
                    <Button type="button" variant="ghost">{{ etiquetaCancelar ?? 'Cancelar' }}</Button>
                </Link>
            </span>

            <slot />
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
