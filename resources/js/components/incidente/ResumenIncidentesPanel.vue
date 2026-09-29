<script setup lang="ts">
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import TarjetaRegistro, { type FilaRegistro } from '@/components/panel/TarjetaRegistro.vue';
import { computed } from 'vue';

/**
 * Los incidentes, de un vistazo (§ 4.10 y `op.exp.7`).
 *
 * **Una sola cifra en rojo, y es un plazo legal**: las 72 h del artículo 33.1 del
 * RGPD pasadas sin notificar a la AEPD. Ni los estados ni la peligrosidad lo
 * gastan — un incidente crítico abierto no va mal, va siendo atendido—, y el
 * CCN-CERT no tiene cuenta atrás porque el RD 311/2022 no fija horas.
 *
 * **Y sin porcentaje de cerrados**, por el argumento de siempre: esa cifra sube
 * al cerrar y baja al registrar uno nuevo, así que castigaría por detectar bien.
 *
 * El reparto por estado **incluye los cerrados**, como en no conformidades y
 * objetivos: la pregunta es «de los que hemos tenido, cuántos hemos llegado a
 * cerrar con su lección aprendida».
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenIncidentesPanel }>();

const segmentos = computed<Segmento[]>(() =>
    props.resumen.porEstado.map((tramo) => ({ clave: tramo.clave, etiqueta: tramo.etiqueta, valor: tramo.valor, tono: tramo.tono })),
);

const filas = computed<FilaRegistro[]>(() => [
    { clave: 'fuera_de_plazo_aepd', etiqueta: 'Fuera de plazo con la AEPD', valor: props.resumen.fueraDePlazoAepd, href: '/incidentes?filter[fuera_de_plazo_aepd]=1', alerta: true },
    { clave: 'en_plazo_aepd', etiqueta: 'Pendientes de notificar a la AEPD', valor: props.resumen.enPlazoAepd, href: '/incidentes?filter[en_plazo_aepd]=1' },
    { clave: 'sin_leccion', etiqueta: 'Resueltos sin lección aprendida', valor: props.resumen.sinLeccion, href: '/incidentes?filter[sin_leccion]=1' },
]);
</script>

<template>
    <TarjetaRegistro
        titulo="Incidentes"
        href="/incidentes"
        :cifra="resumen.abiertos"
        unidad="sin cerrar"
        :de="resumen.total"
        :filas="filas"
        vacio="Ninguno pendiente de notificar ni de cerrar con su lección."
    >
        <BarraSegmentada v-if="resumen.total > 0" :segmentos="segmentos" leyenda />
    </TarjetaRegistro>
</template>
