<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';

/**
 * El código QR del activo, tal y como va a quedar pegado en la carcasa.
 *
 * **La URL se enseña, no sólo se codifica.** Es lo único que permite darse
 * cuenta de que apunta a `localhost` *antes* de imprimir trescientas pegatinas;
 * una etiqueta impresa dura años y equivocarse ahí no se arregla con un
 * despliegue. Se cambia en `organizaciones.url_base_etiquetas`.
 *
 * El SVG llega generado por el servidor (`GeneradorEtiquetas`), no por una
 * librería en el navegador: el día que la hoja pase por Gotenberg a PDF/A no
 * debe ejecutarse JavaScript dentro del documento.
 */
defineProps<{ activoId: number; svg: string; url: string }>();
</script>

<template>
    <div class="flex items-start gap-4">
        <!-- Fondo blanco siempre, también en oscuro: un QR en negativo no lo
             lee la mitad de los móviles, y esto es una previsualización de lo
             que se va a imprimir en papel. -->
        <div class="size-24 shrink-0 rounded-md bg-white p-1.5" v-html="svg" />

        <div class="min-w-0 flex-1">
            <p class="text-xs text-muted-foreground">Al escanearla abre</p>
            <p class="cifra mt-0.5 text-xs break-all">{{ url }}</p>

            <Link :href="`/activos/etiquetas?ids[]=${activoId}`" class="mt-3 inline-block">
                <Button variant="outline" size="sm">Imprimir la etiqueta</Button>
            </Link>
        </div>
    </div>
</template>

<style scoped>
:deep(svg) {
    width: 100%;
    height: 100%;
}
</style>
