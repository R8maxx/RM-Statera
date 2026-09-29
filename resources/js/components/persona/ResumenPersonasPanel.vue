<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import TarjetaRegistro, { type FilaRegistro } from '@/components/panel/TarjetaRegistro.vue';
import { computed } from 'vue';

/**
 * Las personas, de un vistazo (§ 4.8 y cláusula 5.3).
 *
 * **La proporción que se enseña es la de roles ENS designados, no la del
 * personal formado.** Es la decisión que da forma a la tarjeta: la cobertura del
 * 5.3 tiene un denominador estable —los sistemas por los cinco roles— y mide
 * cuánto está decidido. Iba en anillo; con la tarjeta de tercio va como
 * fracción y barra, que es como DESIGN.md § 9 pide dar una cifra sola. El porcentaje de formados sube al impartir
 * una sesión y baja solo al pasar doce meses, así que castigaría por tener
 * plantilla nueva — el mismo argumento por el que el plan de acción no lleva
 * anillo.
 *
 * **Una sola cifra en rojo**: quien se fue con la checklist de salida a medias.
 * Ni no estar formado ni no tener acuerdo lo gastan: son la distancia que queda,
 * y un indicador que castiga por apuntar lo que falta enseña a no apuntarlo.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenPersonasPanel }>();

const cobertura = computed(() =>
    props.resumen.rolesExigibles === 0
        ? 0
        : Math.round((props.resumen.rolesDesignados / props.resumen.rolesExigibles) * 100),
);

const filas = computed<FilaRegistro[]>(() => [
    {
        clave: 'baja_sin_cerrar',
        etiqueta: props.resumen.bajaSinCerrar === 1 ? 'Salida sin cerrar' : 'Salidas sin cerrar',
        valor: props.resumen.bajaSinCerrar,
        href: '/personas?filter[baja_sin_cerrar]=1',
        alerta: true,
    },
    { clave: 'sin_formacion', etiqueta: 'Sin formación reciente', valor: props.resumen.sinFormacion, href: '/personas?filter[sin_formacion]=1' },
    { clave: 'sin_acuerdo', etiqueta: 'Sin acuerdo de confidencialidad', valor: props.resumen.sinAcuerdo, href: '/personas?filter[sin_acuerdo]=1' },
]);
</script>

<template>
    <TarjetaRegistro
        titulo="Personas"
        href="/personas"
        :cifra="resumen.activas"
        unidad="en plantilla"
        :de="resumen.total"
        :filas="filas"
        vacio="Todas formadas, con su acuerdo y sin salidas a medias."
    >
        <div v-if="resumen.rolesExigibles > 0" class="rounded-md bg-superficie px-3 py-2.5">
            <p class="flex items-baseline justify-between gap-3 text-sm">
                <span class="font-medium">Roles del ENS designados</span>
                <span class="cifra font-semibold">
                    <Cifra :valor="resumen.rolesDesignados" /> / {{ resumen.rolesExigibles }}
                </span>
            </p>
            <div
                class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                role="img"
                :aria-label="`${resumen.rolesDesignados} de ${resumen.rolesExigibles} roles designados`"
            >
                <div class="h-full rounded-full bg-primary" :style="{ width: `${cobertura}%` }" />
            </div>
        </div>
    </TarjetaRegistro>
</template>
