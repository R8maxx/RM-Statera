<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';

/**
 * El hilo de 2 px que dice que hay una consulta viva (DESIGN.md §9, «Carga»).
 *
 * Vivía dentro de `DataTable` y era la única señal de reconsulta del producto:
 * filtrar el tablero o cambiar de mes en el calendario —las dos son consultas
 * de servidor— dejaba la pantalla quieta y parecía que no había pasado nada.
 * Se saca aquí para que las tres lo pinten igual.
 *
 * Es uno de los dos bucles del producto y dura lo que dura la petición. Con
 * movimiento reducido se pinta quieto, que sigue diciendo «cargando».
 *
 * Quien lo usa tiene que ser `relative`: el hilo se pega a su borde superior.
 * El `z-40` es local, como los de la tabla: tiene que quedar por encima de su
 * cabecera pegajosa, que está en `z-30` dentro del mismo contenedor.
 */
defineProps<{ activo: boolean }>();

const { reducido } = useMovimientoReducido();
</script>

<template>
    <div
        v-if="activo"
        class="absolute inset-x-0 top-0 z-40 h-0.5 overflow-hidden bg-primary/10"
        aria-hidden="true"
    >
        <div :class="reducido ? 'h-full w-full bg-primary/40' : 'hilo-de-carga h-full w-1/3 bg-primary'" />
    </div>
</template>
