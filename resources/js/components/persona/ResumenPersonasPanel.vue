<script setup lang="ts">
import AnilloProgreso from '@/components/AnilloProgreso.vue';
import Cifra from '@/components/Cifra.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Las personas, de un vistazo (§ 4.8 y cláusula 5.3).
 *
 * **El anillo es de los roles ENS designados, no del personal formado.** Es la
 * decisión que da forma a la tarjeta: la cobertura del 5.3 tiene un denominador
 * estable —los sistemas por los cinco roles— y mide cuánto está decidido, que es
 * el caso del anillo del inventario. El porcentaje de formados sube al impartir
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
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Personas</CardTitle>
            <CardDescription>
                La plantilla, no las cuentas de Statera. De aquí salen los nombramientos del 5.3,
                la formación y los deberes por escrito.
            </CardDescription>
            <CardAction>
                <Link
                    href="/personas"
                    class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver las personas
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-6 pt-0">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:gap-10">
                <div class="shrink-0">
                    <p class="flex items-baseline gap-1.5">
                        <Cifra class="text-2xl font-semibold" :valor="resumen.activas" />
                        <span class="text-sm text-muted-foreground">de {{ resumen.total }}</span>
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ resumen.activas === 1 ? 'persona en plantilla' : 'personas en plantilla' }}
                    </p>
                </div>

                <div v-if="resumen.rolesExigibles > 0" class="flex items-center gap-3">
                    <AnilloProgreso :valor="cobertura" etiqueta="designados" :tamano="96" />
                    <div class="text-sm">
                        <p class="font-medium">Roles ENS</p>
                        <p class="text-muted-foreground">
                            <Cifra class="font-medium text-foreground" :valor="resumen.rolesDesignados" />
                            de {{ resumen.rolesExigibles }} designados
                        </p>
                    </div>
                </div>
            </div>

            <!--
                Lo que pide acción, en una línea y nunca a cero: una línea que
                dice «0 salidas sin cerrar» enseña a no leer la línea.
            -->
            <p
                v-if="resumen.bajaSinCerrar > 0 || resumen.sinFormacion > 0 || resumen.sinAcuerdo > 0"
                class="flex flex-wrap gap-x-4 gap-y-1 border-t pt-4 text-sm"
            >
                <Link
                    v-if="resumen.bajaSinCerrar > 0"
                    href="/personas?filter[baja_sin_cerrar]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="resumen.bajaSinCerrar" />
                    {{ resumen.bajaSinCerrar === 1 ? 'salida sin cerrar' : 'salidas sin cerrar' }}
                </Link>

                <Link
                    v-if="resumen.sinFormacion > 0"
                    href="/personas?filter[sin_formacion]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.sinFormacion" />
                    sin formación en doce meses
                </Link>

                <Link
                    v-if="resumen.sinAcuerdo > 0"
                    href="/personas?filter[sin_acuerdo]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.sinAcuerdo" />
                    sin acuerdo vigente
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
