<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';

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
defineProps<{ resumen: App.Http.Resources.Panel.ResumenObligacionesPanel }>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Obligaciones periódicas</CardTitle>
            <CardDescription>
                Lo que hay que hacer cada tanto y no sale de ningún otro registro: el informe
                INES, la renovación de conformidad, las auditorías de seguimiento.
            </CardDescription>
            <CardAction>
                <Link
                    href="/obligaciones"
                    class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    Ver las obligaciones
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent>
            <!--
                La próxima, con nombre y fecha. Enlaza a su ficha: una cifra que
                no lleva a su lista no se acciona, se mira.
            -->
            <Link
                v-if="resumen.proxima"
                :href="`/obligaciones/${resumen.proxima.id}`"
                class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 rounded-md py-1 hover:underline"
            >
                <span class="text-sm font-medium">{{ resumen.proxima.titulo }}</span>
                <!--
                    `text-destructive` es el token de rol que `lib/tonos.ts` usa
                    para `caducada`, que es el único rojo del vocabulario. No se
                    inventa aquí un color: se usa el mismo que pinta el badge.
                -->
                <span
                    class="text-sm"
                    :class="resumen.proxima.dias < 0 ? 'text-destructive' : 'text-muted-foreground'"
                >
                    {{ resumen.proxima.cuando }} · {{ resumen.proxima.fecha }}
                </span>
            </Link>

            <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-muted-foreground">Vigentes</dt>
                    <dd><Cifra :valor="resumen.total" /></dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Fuera de plazo</dt>
                    <dd><Cifra :valor="resumen.vencidas" /></dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">En 90 días</dt>
                    <dd><Cifra :valor="resumen.porVencer" /></dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Nunca cumplidas</dt>
                    <dd><Cifra :valor="resumen.nuncaCumplidas" /></dd>
                </div>
            </dl>
        </CardContent>
    </Card>
</template>
