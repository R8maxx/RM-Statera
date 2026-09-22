<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Subir, cambiar o quitar una de las dos piezas de marca.
 *
 * **La previsualización va sobre tablero blanco**, no sobre el fondo de la
 * aplicación, y no es un capricho: el documento es siempre tema claro y la
 * portada es blanca, así que un logo pensado para fondo oscuro tiene que
 * enseñarse aquí como se va a imprimir. Es el mismo argumento que ya lleva
 * escrito `EtiquetaQr`, donde el QR se pinta sobre blanco también en oscuro.
 *
 * Se envía al elegir y no con un botón aparte, como la foto de perfil: subir un
 * logo es un gesto completo, y un «Guardar» detrás sólo añade un paso que se
 * olvida con el fichero ya elegido.
 */
const props = defineProps<{
    pieza: 'logo' | 'simbolo';
    etiqueta: string;
    ayuda: string;
    /** La URL servida, o nula si no hay pieza. La compone el servidor. */
    url: string | null;
    /**
     * Alto de la caja de previsualización.
     *
     * **El mismo para las dos piezas**, aunque el logo sea apaisado y el
     * símbolo cuadrado: puestas lado a lado, dos cajas de alto distinto dejan
     * los botones a distinta altura. Es el mismo defecto que arrastraba
     * `CampoBase` con los campos que llevan ayuda.
     */
    alto: string;
}>();

const formulario = useForm<{ pieza: File | null }>({ pieza: null });
const selector = ref<HTMLInputElement | null>(null);

function alElegir(evento: Event): void {
    const elegido = (evento.target as HTMLInputElement).files?.[0];

    if (!elegido) {
        return;
    }

    formulario.pieza = elegido;

    formulario.post(`/organizacion/marca/${props.pieza}`, {
        preserveScroll: true,
        onFinish: () => {
            formulario.reset();

            // Sin esto, volver a elegir el MISMO fichero no dispara `change`.
            if (selector.value) {
                selector.value.value = '';
            }
        },
    });
}

function quitar(): void {
    router.delete(`/organizacion/marca/${props.pieza}`, { preserveScroll: true });
}
</script>

<template>
    <div class="grid content-start gap-2">
        <p class="text-sm font-medium">{{ etiqueta }}</p>

        <div
            class="flex items-center justify-center rounded-xl border bg-white p-3"
            :style="{ height: alto }"
        >
            <img v-if="url" :src="url" :alt="`${etiqueta} de la organización`" class="max-h-full max-w-full object-contain" />
            <p v-else class="text-xs text-neutral-500">Sin {{ etiqueta.toLowerCase() }}</p>
        </div>

        <input
            ref="selector"
            type="file"
            accept="image/svg+xml,image/png,image/jpeg,image/webp"
            class="sr-only"
            :aria-label="`Elegir ${etiqueta.toLowerCase()}`"
            @change="alElegir"
        />

        <div class="flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" :disabled="formulario.processing" @click="selector?.click()">
                {{ formulario.processing ? 'Subiendo…' : url ? 'Cambiar' : 'Subir' }}
            </Button>

            <Button v-if="url" variant="ghost" size="sm" @click="quitar">Quitar</Button>
        </div>

        <p class="text-xs text-muted-foreground">{{ ayuda }}</p>

        <p v-if="formulario.errors.pieza" class="text-sm text-destructive">{{ formulario.errors.pieza }}</p>
    </div>
</template>
