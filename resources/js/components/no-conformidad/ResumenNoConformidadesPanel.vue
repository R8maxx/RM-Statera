<script setup lang="ts">
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import TarjetaRegistro, { type FilaRegistro } from '@/components/panel/TarjetaRegistro.vue';
import { computed } from 'vue';

type Reparto = App.Http.Resources.Panel.Reparto;

/**
 * Las no conformidades, de un vistazo (§ 4.14).
 *
 * Contesta «qué ha fallado y si se arregló», que es la tercera pregunta de un
 * panel: el cumplimiento dice qué falta, el plan de acción dice quién lo está
 * haciendo, y esto dice qué se rompió por el camino.
 *
 * **«Sin verificar» va arriba, junto a lo vencido, y no dentro del reparto.**
 * Es la única cifra de la tarjeta que está por la norma y no por la pantalla: una
 * no conformidad cerrada y sin verificar se lee como resuelta y no lo está, y la
 * cláusula 10.2 e) es el paso que el auditor comprueba precisamente porque es el
 * que todo el mundo se salta.
 *
 * **Sin porcentaje de cerradas**, como en el plan de acción: esa cifra sube al
 * cerrar y baja al registrar una nueva, así que castigaría por auditar bien.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenNoConformidadesPanel }>();

const segmentos = (reparto: Reparto[]): Segmento[] =>
    reparto.map((tramo) => ({ clave: tramo.clave, etiqueta: tramo.etiqueta, valor: tramo.valor, tono: tramo.tono }));

/* «Sin verificar» y «fuera de plazo» son los dos rojos del módulo, y van delante. */
const filas = computed<FilaRegistro[]>(() => [
    { clave: 'sin_verificar', etiqueta: 'Cerradas sin verificar la eficacia', valor: props.resumen.sinVerificar, href: '/no-conformidades?filter[pendientes_de_verificar]=1', alerta: true },
    { clave: 'vencidas', etiqueta: 'Fuera de plazo', valor: props.resumen.vencidas, href: '/no-conformidades?filter[vencidas]=1', alerta: true },
    { clave: 'sin_accion', etiqueta: 'Sin acción correctiva', valor: props.resumen.sinAccion, href: '/no-conformidades?filter[sin_accion]=1' },
]);
</script>

<template>
    <TarjetaRegistro
        titulo="No conformidades"
        href="/no-conformidades"
        :cifra="resumen.abiertas"
        :unidad="resumen.abiertas === 1 ? 'abierta' : 'abiertas'"
        :de="resumen.total"
        :filas="filas"
        vacio="Todas tratadas y verificadas."
    >
        <BarraSegmentada v-if="resumen.porEstado.length > 0" :segmentos="segmentos(resumen.porEstado)" leyenda />
    </TarjetaRegistro>
</template>
