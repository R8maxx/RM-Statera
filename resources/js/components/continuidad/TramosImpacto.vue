<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { tono } from '@/lib/tonos';
import { computed } from 'vue';

interface Tramo {
    clave: string;
    etiqueta: string;
    horas: number;
    nivel: string;
    nivelEtiqueta: string;
    nivelTono: string;
    nivelIcono: string;
    /** Si es el primer tramo en el que el impacto llega a «muy alto»: el MTPD. */
    esUmbral: boolean;
}

/**
 * Los cinco tramos del MTPD, con el umbral marcado y el RTO enfrentado a él.
 *
 * **Cinco celdas y no una barra**, porque no son un progreso: son cinco
 * preguntas independientes —«¿cómo de grave es no tener el servicio a las 4
 * horas? ¿a 1 día?»— y lo que importa de cada una es su propio nivel, no cuánto
 * suma con las de al lado.
 *
 * **La leyenda de incoherencia va en ámbar (`en_progreso`) y no en rojo.** El
 * rojo del dominio es `caducada`, reservado a lo vencido o lo incumplido
 * (DESIGN.md §3): un RTO que promete más de lo que el propio BIA tolera es una
 * contradicción que hay que corregir, no un incumplimiento consumado —nadie ha
 * dejado de cumplir un plazo todavía—, así que el aviso pide atención sin
 * gastar el único rojo del vocabulario.
 */
const props = defineProps<{
    tramos: Tramo[];
    rtoHoras: number;
    rtoIncoherente: boolean;
}>();

const avisoIncoherencia = computed(() => tono('en_progreso'));
</script>

<template>
    <div class="space-y-3">
        <ol class="grid grid-cols-2 gap-2 sm:grid-cols-5">
            <li
                v-for="tramo in tramos"
                :key="tramo.clave"
                class="flex flex-col items-start gap-1.5 rounded-xl border p-3"
                :class="tramo.esUmbral ? 'border-primary ring-1 ring-primary/25' : 'border-border'"
            >
                <span class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    {{ tramo.etiqueta }}
                    <span v-if="tramo.esUmbral" class="cifra font-medium text-primary" title="El umbral tolerable (MTPD)">
                        · MTPD
                    </span>
                </span>
                <CeldaBadge
                    :valor="{
                        valor: tramo.nivel,
                        etiqueta: tramo.nivelEtiqueta,
                        tono: tramo.nivelTono,
                        icono: tramo.nivelIcono,
                    }"
                />
            </li>
        </ol>

        <p
            v-if="rtoIncoherente"
            class="flex items-center gap-2 rounded-xl border px-3.5 py-2.5 text-sm"
            :class="avisoIncoherencia.badge"
        >
            <IconoTipo :nombre="avisoIncoherencia.icono" clase="size-4 shrink-0" />
            RTO <span class="cifra font-medium">{{ rtoHoras }} h</span> por encima del umbral tolerable: el propio BIA
            no lo aguanta.
        </p>
    </div>
</template>
