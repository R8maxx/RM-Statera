<script setup lang="ts">
/**
 * Campos cortos, uno al lado del otro.
 *
 * Un select, una fecha o un número no necesitan 880 px: a lo ancho del
 * formulario, «Prioridad» ocupaba lo mismo que la descripción. Ésta es la fila
 * que se escribía a mano como `grid gap-5 sm:grid-cols-2` en quince sitios.
 *
 * Qué va aquí y qué no: selects, fechas y números, sí; textareas, casillas,
 * títulos y escalas (`CampoOpciones`) van a lo ancho, porque se escriben o se
 * comparan en horizontal. Y nunca un campo que aparece y desaparece junto a
 * otro que se queda: deja media fila vacía (ver `activos/Formulario.vue`).
 *
 * `CampoBase` lleva `content-start` precisamente para esto: dos campos de la
 * misma fila alinean sus controles aunque uno tenga ayuda y el otro no.
 */
withDefaults(
    defineProps<{
        columnas?: 2 | 3;
        /**
         * El código y el nombre de lo que se registra: el código es corto y de
         * formato fijo —`R-001`, `SIS-0003`— y el nombre se escribe.
         */
        codigo?: boolean;
    }>(),
    { columnas: 2, codigo: false },
);
</script>

<template>
    <div
        class="grid gap-5"
        :class="
            codigo
                ? 'sm:grid-cols-[12rem_minmax(0,1fr)]'
                : columnas === 3
                  ? 'sm:grid-cols-2 lg:grid-cols-3'
                  : 'sm:grid-cols-2'
        "
    >
        <slot />
    </div>
</template>
