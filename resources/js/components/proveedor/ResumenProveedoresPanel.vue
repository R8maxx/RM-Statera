<script setup lang="ts">
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import TarjetaRegistro, { type FilaRegistro } from '@/components/panel/TarjetaRegistro.vue';
import { computed } from 'vue';

/**
 * Los proveedores, de un vistazo (§ 4.9).
 *
 * **Dos rojos, y los dos caducan solos**: la reevaluación vencida y el
 * certificado caducado de un proveedor con el que se sigue trabajando. El
 * reparto es por la criticidad que manda, que es lo que dice cuánto depende la
 * organización de terceros.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenProveedoresPanel }>();

const barras = computed<Barra[]>(() =>
    props.resumen.porCriticidad.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        de: props.resumen.total,
        tono: tramo.tono,
    })),
);

const filas = computed<FilaRegistro[]>(() => [
    { clave: 'reevaluacion_vencida', etiqueta: 'Con la reevaluación vencida', valor: props.resumen.reevaluacionVencida, href: '/proveedores?filter[reevaluacion_vencida]=1', alerta: true },
    { clave: 'certificacion_caducada', etiqueta: 'Con una certificación caducada', valor: props.resumen.certificacionCaducada, href: '/proveedores?filter[certificacion_caducada]=1', alerta: true },
    { clave: 'sin_evaluar', etiqueta: 'Sin evaluar', valor: props.resumen.sinEvaluar, href: '/proveedores?filter[sin_evaluar]=1' },
    { clave: 'condicionados', etiqueta: props.resumen.condicionados === 1 ? 'Condicionado' : 'Condicionados', valor: props.resumen.condicionados, href: '/proveedores?filter[condicionados]=1' },
]);
</script>

<template>
    <TarjetaRegistro
        titulo="Proveedores y terceros"
        href="/proveedores"
        :cifra="resumen.total"
        unidad="en activo"
        :filas="filas"
        vacio="Todos evaluados y al día."
    >
        <div v-if="resumen.total > 0">
            <p class="mb-3 text-xs font-medium text-muted-foreground">Por criticidad</p>
            <GraficaBarras :barras="barras" />
        </div>
    </TarjetaRegistro>
</template>
