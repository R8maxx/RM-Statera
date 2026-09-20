<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';

/**
 * El estado de una notificación a un supervisor.
 *
 * **Uno con reloj y otro sin él, y la diferencia va escrita.** La AEPD tiene 72 h
 * en el artículo 33.1 del RGPD; el CCN-CERT no tiene un número: el RD 311/2022
 * dice «sin dilación». Statera no se inventa una cuenta atrás donde la ley no
 * pone una, que es lo mismo que hace con el riesgo residual.
 */
export interface Notificacion {
    destinatario: string;
    nombre: string;
    notificable: boolean;
    notificado: boolean;
    notificadoEn: string | null;
    vencido: boolean;
    horasRestantes: number | null;
    etiqueta: string;
    tono: string;
    fundamento: string;
}

defineProps<{ notificacion: Notificacion; puedeGestionar: boolean }>();

const emit = defineEmits<{ anotar: [destinatario: string] }>();
</script>

<template>
    <div class="space-y-2 rounded-xl border border-border p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold">{{ notificacion.nombre }}</h3>
            <CeldaBadge
                :valor="{
                    valor: notificacion.destinatario,
                    etiqueta: notificacion.etiqueta,
                    tono: notificacion.tono,
                    icono: null,
                }"
            />
        </div>

        <p v-if="notificacion.notificadoEn" class="text-sm text-muted-foreground">
            Anotada el {{ notificacion.notificadoEn }}.
        </p>

        <p class="text-xs text-muted-foreground">{{ notificacion.fundamento }}</p>

        <Button
            v-if="puedeGestionar && !notificacion.notificado"
            variant="outline"
            size="sm"
            @click="emit('anotar', notificacion.destinatario)"
        >
            Anotar la notificación
        </Button>
    </div>
</template>
