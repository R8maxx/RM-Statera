<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { QrCodeIcon } from '@lucide/vue';

interface Etiqueta {
    id: number;
    codigo: string;
    nombre: string;
    detalle: string;
    svg: string | null;
}

/**
 * La hoja de etiquetas QR, para imprimir y pegar.
 *
 * **El QR lleva a la ficha del activo**, no a un código en texto plano.
 * Escanear la pegatina de un portátil abre su ficha en el móvil; con el código
 * suelto habría que memorizarlo, abrir la aplicación y buscarlo, y a la tercera
 * vez nadie escanea nada.
 *
 * El código impreso al lado no es redundante: se lee sin móvil, y sigue estando
 * cuando el QR se raya o se despega por una esquina.
 *
 * El SVG viene generado en el servidor —`v-html` sobre marcado que produce
 * BaconQrCode, no entrada de usuario—, y no con una librería en el navegador:
 * el día que esta hoja pase por Gotenberg para archivarse en PDF/A no debe
 * ejecutarse JavaScript dentro del documento.
 */
defineProps<{ etiquetas: Etiqueta[]; descartados: number }>();

function imprimir(): void {
    window.print();
}
</script>

<template>
    <AppLayout titulo="Etiquetas QR">
        <div class="no-imprimir">
            <CabeceraPagina
                titulo="Etiquetas QR"
                descripcion="Sólo activos físicos y en uso: un recurso en la nube no tiene carcasa donde pegar nada, y un equipo retirado no se etiqueta, se borra."
            >
                <template #acciones>
                    <Button variant="outline" @click="imprimir">Imprimir</Button>
                </template>
            </CabeceraPagina>

            <p v-if="descartados > 0" class="mb-6 text-sm text-muted-foreground">
                Se han dejado fuera <span class="cifra">{{ descartados }}</span>
                {{ descartados === 1 ? 'activo' : 'activos' }} por no ser físicos o estar ya de baja.
            </p>
        </div>

        <EstadoVacio
            v-if="etiquetas.length === 0"
            :icono="QrCodeIcon"
            titulo="Nada que etiquetar"
            descripcion="Ninguno de los activos seleccionados lleva etiqueta: o no son físicos, o ya están retirados."
            :accion="{ etiqueta: 'Volver al inventario', href: '/activos' }"
        />

        <!--
            Rejilla de 63,5 × 38,1 mm, la medida de etiqueta adhesiva más común.
            En pantalla se ve la misma rejilla para poder comprobar antes de
            gastar una hoja.
        -->
        <ul v-else class="hoja-etiquetas">
            <li v-for="etiqueta in etiquetas" :key="etiqueta.id" class="etiqueta">
                <div class="etiqueta-qr" v-html="etiqueta.svg" />

                <div class="min-w-0">
                    <p class="cifra text-[11px] leading-tight font-semibold">{{ etiqueta.codigo }}</p>
                    <p class="mt-0.5 truncate text-[10px] leading-tight">{{ etiqueta.nombre }}</p>
                    <p class="truncate text-[9px] leading-tight text-muted-foreground">{{ etiqueta.detalle }}</p>
                </div>
            </li>
        </ul>
    </AppLayout>
</template>

<style scoped>
.hoja-etiquetas {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(63.5mm, 1fr));
    gap: 2mm;
}

.etiqueta {
    display: flex;
    align-items: center;
    gap: 2mm;
    height: 38.1mm;
    padding: 2mm;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}

.etiqueta-qr {
    flex-shrink: 0;
    width: 28mm;
    height: 28mm;
}

.etiqueta-qr :deep(svg) {
    width: 100%;
    height: 100%;
}

/*
 * Al imprimir desaparece todo lo que no es etiqueta: cabecera, barra lateral y
 * avisos. Y el borde pasa a discontinuo, que es la línea por donde se corta si
 * el papel no es preperforado.
 */
@media print {
    .hoja-etiquetas {
        gap: 0;
    }

    .etiqueta {
        border-style: dashed;
        border-radius: 0;
        break-inside: avoid;
    }
}
</style>
