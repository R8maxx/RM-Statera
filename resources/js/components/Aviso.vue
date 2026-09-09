<script setup lang="ts">
import { AlertCircleIcon, CheckCircle2Icon, InfoIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Un aviso dentro del contenido, no un toast.
 *
 * Los toasts sirven para confirmar algo que ya pasó y desaparecer. Lo que va
 * aquí es lo que tiene que quedarse a la vista: por qué no ha entrado, que el
 * enlace de restablecimiento ya está enviado, que la sesión ha caducado.
 *
 * `role="alert"` sólo en el tono de error: un lector de pantalla interrumpe lo
 * que esté diciendo, y hacerlo para un mensaje informativo es ruido.
 */
const props = withDefaults(
    defineProps<{ tono?: 'error' | 'exito' | 'info'; titulo?: string }>(),
    { tono: 'info' },
);

const estilos = {
    error: {
        caja: 'border-destructive/40 bg-destructive/5 text-destructive',
        icono: AlertCircleIcon,
    },
    exito: {
        caja: 'border-estado-implantado/40 bg-estado-implantado/5 text-estado-implantado',
        icono: CheckCircle2Icon,
    },
    info: {
        caja: 'border-border bg-muted/60 text-foreground',
        icono: InfoIcon,
    },
} as const;

const estilo = computed(() => estilos[props.tono]);
</script>

<template>
    <div
        class="flex items-start gap-2.5 rounded-xl border px-3.5 py-3 text-sm"
        :class="estilo.caja"
        :role="tono === 'error' ? 'alert' : 'status'"
    >
        <component :is="estilo.icono" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <div class="min-w-0">
            <p v-if="titulo" class="font-medium">{{ titulo }}</p>
            <p :class="titulo && 'mt-0.5 opacity-90'"><slot /></p>
        </div>
    </div>
</template>
