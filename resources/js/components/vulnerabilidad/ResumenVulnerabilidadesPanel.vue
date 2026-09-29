<script setup lang="ts">
import TarjetaRegistro, { type FilaRegistro } from '@/components/panel/TarjetaRegistro.vue';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Las vulnerabilidades, de un vistazo (invariante 8, A.8.8 y `op.exp.4`).
 *
 * **Una sola cifra en rojo, y es el plazo vencido**, como en el registro: una
 * crítica recién detectada no va mal, se está atendiendo. El reparto es por
 * severidad y sólo de las vivas, que es lo que pesa ahora.
 *
 * **Y sin porcentaje de cerradas**, por el argumento de siempre: castigaría por
 * detectar bien.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenVulnerabilidadesPanel }>();

/*
 * La severidad en chips con su cifra y no en barras: son cuatro valores que se
 * leen en una línea, y cada chip lleva a su lista.
 */
const severidades = computed(() => props.resumen.porSeveridad.filter((tramo) => tramo.valor > 0));

const filas = computed<FilaRegistro[]>(() => [
    { clave: 'fuera_de_plazo', etiqueta: 'Fuera del plazo de remediación', valor: props.resumen.fueraDePlazo, href: '/vulnerabilidades?filter[fuera_de_plazo]=1', alerta: true },
    { clave: 'criticas_abiertas', etiqueta: props.resumen.criticasAbiertas === 1 ? 'Crítica abierta' : 'Críticas abiertas', valor: props.resumen.criticasAbiertas, href: '/vulnerabilidades?filter[criticas_abiertas]=1' },
    { clave: 'sin_verificar', etiqueta: 'Mitigadas sin verificar', valor: props.resumen.sinVerificar, href: '/vulnerabilidades?filter[sin_verificar]=1' },
    { clave: 'aceptadas', etiqueta: 'Aceptadas como riesgo asumido', valor: props.resumen.aceptadas, href: '/vulnerabilidades?filter[aceptadas]=1' },
]);
</script>

<template>
    <TarjetaRegistro
        titulo="Vulnerabilidades"
        href="/vulnerabilidades"
        :cifra="resumen.vivas"
        :unidad="resumen.vivas === 1 ? 'viva' : 'vivas'"
        :de="resumen.total"
        :filas="filas"
        vacio="Ninguna fuera de plazo ni pendiente de verificar."
    >
        <ul v-if="severidades.length > 0" class="flex flex-wrap gap-1.5" aria-label="Vivas por severidad">
            <li v-for="tramo in severidades" :key="tramo.clave">
                <Link
                    :href="tramo.filtro ? `/vulnerabilidades?${tramo.filtro}` : '/vulnerabilidades'"
                    class="inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium"
                    :class="tono(tramo.tono).badge"
                >
                    {{ tramo.etiqueta }}
                    <span class="cifra">{{ tramo.valor }}</span>
                </Link>
            </li>
        </ul>
    </TarjetaRegistro>
</template>
