<script setup lang="ts">
import { computed } from 'vue';

type ValorEtiquetado = App.Http.Resources.Definicion.ValorEtiquetado;

const props = defineProps<{ valor: ValorEtiquetado | null }>();

/**
 * Los colores se declaran una sola vez en `resources/css/app.css`. Aquí sólo se
 * traduce el tono del dominio a su par de tokens, y lo que no está en la lista
 * cae en el neutro en lugar de inventarse una clase.
 */
const tonos: Record<string, string> = {
    no_iniciado: 'bg-estado-no-iniciado-suave text-estado-no-iniciado',
    planificado: 'bg-estado-planificado-suave text-estado-planificado',
    en_progreso: 'bg-estado-en-progreso-suave text-estado-en-progreso',
    implantado: 'bg-estado-implantado-suave text-estado-implantado',
    no_aplica: 'bg-estado-no-aplica-suave text-estado-no-aplica',
    borrador: 'bg-estado-no-iniciado-suave text-estado-no-iniciado',
    activo: 'bg-estado-implantado-suave text-estado-implantado',
    archivado: 'bg-estado-no-aplica-suave text-estado-no-aplica',
    basica: 'bg-estado-planificado-suave text-estado-planificado',
    media: 'bg-estado-en-progreso-suave text-estado-en-progreso',
    alta: 'bg-destructive/10 text-destructive',
};

const clases = computed(() => tonos[props.valor?.tono ?? ''] ?? 'bg-muted text-muted-foreground');
</script>

<template>
    <span
        v-if="valor"
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="clases"
    >
        {{ valor.etiqueta }}
    </span>
    <span v-else class="text-muted-foreground">—</span>
</template>
