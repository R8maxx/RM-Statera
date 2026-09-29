<script setup lang="ts">
import TarjetaRegistro, { type FilaRegistro } from '@/components/panel/TarjetaRegistro.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Las obligaciones periódicas, de un vistazo (§ 4.16).
 *
 * Va detrás del plan de acción porque es la misma pregunta con otra cadencia: el
 * plan dice qué hay abierto esta semana y esto qué vuelve solo cada año.
 *
 * **La línea que de verdad se lee es la próxima con su nombre.** «3 pendientes»
 * no contesta lo que se viene a mirar; «Informe INES — vence en 41 días» sí.
 *
 * **Sin anillo y sin porcentaje de cumplimiento**, por el mismo motivo que el
 * plan de acción: el denominador crece cada vez que alguien declara una
 * obligación, así que la cifra bajaría justo al hacer lo correcto.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenObligacionesPanel }>();

/*
 * Las fuera de plazo salen también una a una en «Lo que vence»; aquí va el
 * recuento con el filtro que las reúne, que es lo que la lista no da.
 */
const filas = computed<FilaRegistro[]>(() => [
    { clave: 'vencidas', etiqueta: 'Fuera de plazo', valor: props.resumen.vencidas, href: '/obligaciones?filter[vencidas]=1', alerta: true },
    { clave: 'por_vencer', etiqueta: 'Vencen en 90 días', valor: props.resumen.porVencer, href: '/obligaciones?filter[por_vencer]=1' },
    { clave: 'nunca_cumplidas', etiqueta: 'Nunca cumplidas', valor: props.resumen.nuncaCumplidas, href: '/obligaciones?filter[nunca_cumplidas]=1' },
    { clave: 'sin_responsable', etiqueta: 'Sin responsable', valor: props.resumen.sinResponsable, href: '/obligaciones?filter[sin_responsable]=1' },
]);
</script>

<template>
    <TarjetaRegistro
        titulo="Obligaciones periódicas"
        href="/obligaciones"
        :cifra="resumen.total"
        unidad="vigentes"
        :filas="filas"
        vacio="Todas al día."
    >
        <!-- La próxima, con nombre y fecha: «3 pendientes» no contesta lo que
             se viene a mirar; «Informe INES — vence en 41 días» sí. -->
        <Link
            v-if="resumen.proxima"
            :href="`/obligaciones/${resumen.proxima.id}`"
            class="block rounded-md bg-superficie px-3 py-2.5 transition-colors hover:bg-accent"
        >
            <span class="block text-xs text-muted-foreground">La próxima</span>
            <span class="block text-sm font-medium">{{ resumen.proxima.titulo }}</span>
            <span class="block text-xs" :class="resumen.proxima.dias < 0 ? 'text-destructive' : 'text-secondary-foreground'">
                {{ resumen.proxima.cuando }} · {{ resumen.proxima.fecha }}
            </span>
        </Link>
    </TarjetaRegistro>
</template>
