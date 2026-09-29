<script setup lang="ts">
import RelojNotificacion, { type Reloj } from '@/components/incidente/RelojNotificacion.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';

/**
 * Una notificación a un supervisor, como fila de la tarjeta de plazos.
 *
 * **Uno con reloj y otro sin él, y la diferencia va escrita.** La AEPD tiene 72 h
 * en el artículo 33.1 del RGPD y se dibuja como barra (`RelojNotificacion`); el
 * CCN-CERT no tiene un número —el RD 311/2022 dice «sin dilación»— y en el hueco
 * de la barra va la frase que lo explica. Statera no se inventa una cuenta atrás
 * donde la ley no pone una, que es lo mismo que hace con el riesgo residual.
 *
 * **El badge lleva la palabra y la frase va debajo.** `CeldaBadge` no parte
 * línea a propósito —una etiqueta de estado partida en dos deja de leerse como
 * una—. Es además lo que pide el vocabulario cerrado de los badges: `estado` es
 * la palabra, `etiqueta` es lo que se lee.
 *
 * **Un solo primario por vista**, y es éste cuando el reloj corre: anotar la
 * notificación a la AEPD es lo más urgente que puede hacerse en la ficha. Lo
 * decide quien la pinta con `primaria`.
 */
export interface Notificacion {
    destinatario: string;
    nombre: string;
    notificable: boolean;
    notificado: boolean;
    notificadoEn: string | null;
    vencido: boolean;
    horasRestantes: number | null;
    estado: string;
    etiqueta: string;
    tono: string;
    icono: string;
    fundamento: string;
    reloj: Reloj | null;
}

defineProps<{
    notificacion: Notificacion;
    puedeGestionar: boolean;
    inicioConocido: boolean;
    primaria?: boolean;
}>();

const emit = defineEmits<{ anotar: [destinatario: string] }>();
</script>

<template>
    <div class="grid items-center gap-x-6 gap-y-3 lg:grid-cols-[14rem_minmax(0,1fr)_13rem]">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-sm font-semibold">{{ notificacion.nombre }}</h3>
                <CeldaBadge
                    :valor="{
                        valor: notificacion.destinatario,
                        etiqueta: notificacion.estado,
                        tono: notificacion.tono,
                        icono: notificacion.icono,
                    }"
                />
            </div>
            <!--
                El rojo sólo cuando ya va mal, y con `role="alert"`: es el único
                aviso del módulo que interrumpe al lector de pantalla.
            -->
            <p
                class="text-[13px] font-medium"
                :class="notificacion.vencido && !notificacion.notificado ? 'text-destructive' : 'text-foreground'"
                :role="notificacion.vencido && !notificacion.notificado ? 'alert' : undefined"
            >
                <template v-if="notificacion.reloj?.horasFueraDePlazo && !notificacion.notificado">
                    <span class="cifra">{{ notificacion.reloj.horasFueraDePlazo }} h</span> fuera de plazo, sin
                    notificar
                </template>
                <template v-else>{{ notificacion.etiqueta }}</template>
            </p>
            <p v-if="notificacion.notificadoEn" class="text-xs text-muted-foreground">
                Anotada el <span class="cifra">{{ notificacion.notificadoEn }}</span>.
            </p>
        </div>

        <RelojNotificacion
            v-if="notificacion.reloj"
            :reloj="notificacion.reloj"
            :inicio-conocido="inicioConocido"
        />
        <p v-else class="max-w-prose text-[13px] text-pretty text-muted-foreground">
            {{ notificacion.fundamento }}
        </p>

        <div class="space-y-1.5">
            <!--
                **Cuando no procede notificar, el botón no se ofrece igual.**
                `RegistrarNotificacion` marca `notificable` al anotar —y hace bien:
                lo contrario obligaría a editar el incidente antes de poder decir la
                verdad sobre él—, así que un botón de contorno en una fila que dice
                «Sin datos personales afectados» está invitando a crear una
                obligación que no existía. La puerta se deja abierta, se deja de
                empujar.
            -->
            <Button
                v-if="puedeGestionar && !notificacion.notificado"
                :variant="notificacion.notificable ? (primaria ? 'default' : 'outline') : 'link'"
                :size="notificacion.notificable ? 'default' : 'sm'"
                class="w-full"
                :class="notificacion.notificable ? undefined : 'h-auto justify-start px-0 lg:justify-end'"
                @click="emit('anotar', notificacion.destinatario)"
            >
                {{ notificacion.notificable ? 'Anotar la notificación' : 'Se notificó de todos modos' }}
            </Button>
            <p v-if="notificacion.reloj && !notificacion.notificado" class="text-xs text-muted-foreground lg:text-center">
                {{ notificacion.fundamento }}
            </p>
        </div>
    </div>
</template>
