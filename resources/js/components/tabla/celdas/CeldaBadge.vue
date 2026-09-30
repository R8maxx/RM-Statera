<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import { tono } from '@/lib/tonos';
import { computed, ref, watch } from 'vue';

type ValorEtiquetado = App.Http.Resources.Definicion.ValorEtiquetado;

const props = defineProps<{
    valor: ValorEtiquetado | null;
    /**
     * Si el icono aparece cuando el valor **cambia** delante de quien mira: el
     * estado de una ficha tras pulsar su transición. Nunca en el primer pintado
     * ni en una tabla —una página nueva de filas no es un cambio de nada—, y
     * nunca al pasar por encima: un badge no se pulsa (DESIGN.md §10).
     */
    anunciar?: boolean;
}>();

/*
 * Cuántas veces ha cambiado. Va de clave en el icono para que se monte de nuevo
 * y la animación de CSS vuelva a correr; a cero no lleva la clase.
 */
const cambios = ref(0);

watch(
    () => props.valor?.valor,
    (nuevo, anterior) => {
        if (props.anunciar && anterior !== undefined && nuevo !== anterior) {
            cambios.value++;
        }
    },
);

/**
 * El badge de un valor del dominio: color, icono y texto.
 *
 * Los tres canales, que es lo que pide DESIGN.md §3 —«nunca comunicar un estado
 * sólo con color»—. El **color agrupa**, el **icono identifica** y el **texto
 * manda**: es el único que no depende de ver bien ni del tema.
 *
 * El icono **sustituye al punto**, no se suma. Un punto no identifica nada —es
 * el mismo círculo para «Implantado» que para «Bloqueada»— y estaba ahí como
 * respaldo de daltonismo. El icono hace ese trabajo mejor: los dos grises del
 * dominio, `no_iniciado` y `no_aplica`, quedan a ΔE 2.3 con protanopía y sin
 * icono son el mismo badge. El punto se queda para los tonos que llegan sin
 * icono, que es lo que siempre fue: un respaldo.
 *
 * Las clases viven en `lib/tonos.ts`, una sola vez para todo el producto.
 */
const estilo = computed(() => tono(props.valor?.tono));

/** El del servidor manda; el del tono es el respaldo. */
const icono = computed(() => props.valor?.icono ?? estilo.value.icono);
</script>

<template>
    <!--
        `title` con la etiqueta entera: el badge no parte línea, así que en una
        columna estrecha se corta contra el borde. El texto es el único de los
        tres canales que no depende de ver bien, y perderlo deja el estado
        comunicado sólo por color e icono.
    -->
    <span
        v-if="valor"
        :title="valor.etiqueta"
        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-colors"
        :class="estilo.badge"
    >
        <!--
            El icono primero: identifica, y el punto sólo acompaña. Lo que separa
            nueve tipos de activo o dos grises que el color no llega a separar.
        -->
        <IconoTipo v-if="icono" :key="cambios" :nombre="icono" :clase="cambios > 0 ? 'size-3.5 icono-anuncia' : undefined" />

        <!-- Y cuando no hay icono, el punto como respaldo de daltonismo. -->
        <span v-else-if="estilo.punto" class="size-1.5 rounded-full" :class="estilo.punto" aria-hidden="true" />

        {{ valor.etiqueta }}
    </span>
    <span v-else class="text-muted-foreground" aria-label="sin valor">—</span>
</template>
