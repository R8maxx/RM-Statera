<script setup lang="ts">
import { computed } from 'vue';

type ValorEtiquetado = App.Http.Resources.Definicion.ValorEtiquetado;

const props = defineProps<{ valor: ValorEtiquetado | null }>();

/**
 * Los colores se declaran una sola vez en `resources/css/app.css`. Aquí sólo se
 * traduce el tono del dominio a su par de tokens, y lo que no está en la lista
 * cae en el neutro en lugar de inventarse una clase.
 *
 * `punto: null` para lo que no es un estado: el marco de un requisito no tiene
 * grados, y un punto de color delante sugeriría que sí.
 */
const tonos: Record<string, { badge: string; punto: string | null }> = {
    no_iniciado: { badge: 'bg-estado-no-iniciado-suave text-estado-no-iniciado', punto: 'bg-estado-no-iniciado' },
    planificado: { badge: 'bg-estado-planificado-suave text-estado-planificado', punto: 'bg-estado-planificado' },
    en_progreso: { badge: 'bg-estado-en-progreso-suave text-estado-en-progreso', punto: 'bg-estado-en-progreso' },
    implantado: { badge: 'bg-estado-implantado-suave text-estado-implantado', punto: 'bg-estado-implantado' },
    no_aplica: { badge: 'bg-estado-no-aplica-suave text-estado-no-aplica', punto: 'bg-estado-no-aplica' },
    borrador: { badge: 'bg-estado-no-iniciado-suave text-estado-no-iniciado', punto: 'bg-estado-no-iniciado' },
    activo: { badge: 'bg-estado-implantado-suave text-estado-implantado', punto: 'bg-estado-implantado' },
    archivado: { badge: 'bg-estado-no-aplica-suave text-estado-no-aplica', punto: 'bg-estado-no-aplica' },

    /*
     * La categoría del ENS es ordinal —básica < media < alta—, así que sube en
     * énfasis, no cambia de significado. `alta` estaba en rojo `destructive`, y
     * eso mentía: una categoría alta no es un error, es un sistema que exige
     * más. El rojo se reserva para lo que va mal.
     */
    basica: { badge: 'bg-muted text-muted-foreground', punto: 'bg-muted-foreground' },
    media: { badge: 'bg-accent text-accent-foreground', punto: 'bg-primary/70' },
    alta: { badge: 'bg-primary/15 text-primary ring-1 ring-primary/25', punto: 'bg-primary' },

    /* Exigencia: un refuerzo es «aplica, y además esto», y se ve que pesa más. */
    exigible: { badge: 'bg-muted text-foreground', punto: 'bg-muted-foreground' },
    reforzado: { badge: 'bg-accent text-accent-foreground', punto: 'bg-primary' },

    /* Procedencia, no estado: chip neutro y monoespaciado, sin punto. */
    marco: { badge: 'cifra bg-muted text-muted-foreground', punto: null },
};

const tono = computed(
    () => tonos[props.valor?.tono ?? ''] ?? { badge: 'bg-muted text-muted-foreground', punto: 'bg-muted-foreground' },
);
</script>

<template>
    <span
        v-if="valor"
        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-colors duration-200"
        :class="tono.badge"
    >
        <!--
            El punto sí codifica estado: es el respaldo de quien no distingue
            estos verdes de estos ámbares, y la única razón por la que aquí un
            punto de color está justificado.
        -->
        <span v-if="tono.punto" class="size-1.5 rounded-full" :class="tono.punto" aria-hidden="true" />
        {{ valor.etiqueta }}
    </span>
    <span v-else class="text-muted-foreground" aria-label="sin valor">—</span>
</template>
