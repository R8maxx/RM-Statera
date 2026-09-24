<script setup lang="ts">
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import Cifra from '@/components/Cifra.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';

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
    reparto.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        tono: tramo.tono,
    }));
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>No conformidades</CardTitle>
            <CardDescription>
                Lo que se encontró y qué se hizo con ello. La cláusula 10.2 no termina al corregir:
                termina al comprobar que la corrección sirvió.
            </CardDescription>
            <CardAction>
                <Link
                    href="/no-conformidades"
                    class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver el registro
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-6 pt-0">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-10">
                <div class="shrink-0">
                    <p class="flex items-baseline gap-1.5">
                        <Cifra class="text-2xl font-semibold" :valor="resumen.abiertas" />
                        <!-- El denominador al lado: dos abiertas sobre tres es una
                             organización que no cierra nada, y sobre ciento veinte
                             es un martes. -->
                        <span class="cifra text-sm text-muted-foreground">de <Cifra :valor="resumen.total" /></span>
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ resumen.abiertas === 1 ? 'no conformidad abierta' : 'no conformidades abiertas' }}
                    </p>
                </div>

                <div v-if="resumen.porEstado.length > 0" class="min-w-0 flex-1">
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">En qué punto están</p>
                    <BarraSegmentada :segmentos="segmentos(resumen.porEstado)" leyenda />
                </div>
            </div>

            <!--
                Lo que arde, en una línea con enlace y nunca a cero: una línea que
                dice «0 sin verificar» enseña a no leer la línea.
            -->
            <p
                v-if="resumen.sinVerificar > 0 || resumen.vencidas > 0 || resumen.sinAccion > 0"
                class="flex flex-wrap gap-x-4 gap-y-1 border-t pt-4 text-sm"
            >
                <Link
                    v-if="resumen.sinVerificar > 0"
                    href="/no-conformidades?filter[pendientes_de_verificar]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="resumen.sinVerificar" />
                    sin verificar
                </Link>

                <Link
                    v-if="resumen.vencidas > 0"
                    href="/no-conformidades?filter[vencidas]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="resumen.vencidas" />
                    fuera de plazo
                </Link>

                <Link
                    v-if="resumen.sinAccion > 0"
                    href="/no-conformidades?filter[sin_accion]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.sinAccion" />
                    sin acción correctiva
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
