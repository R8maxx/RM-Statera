<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
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
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Proveedores</CardTitle>
            <CardDescription>
                De quién depende lo que hay dentro del alcance, y si se ha comprobado que cumple.
            </CardDescription>
            <CardAction>
                <Link
                    href="/proveedores"
                    class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver los proveedores
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-6 pt-0">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-10">
                <div class="shrink-0">
                    <p><Cifra class="text-2xl font-semibold" :valor="resumen.total" /></p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ resumen.total === 1 ? 'proveedor en activo' : 'proveedores en activo' }}
                    </p>
                </div>

                <div v-if="resumen.total > 0" class="min-w-0 flex-1">
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">Por criticidad</p>
                    <GraficaBarras :barras="barras" />
                </div>
            </div>

            <!-- Lo que pide acción, en una línea y nunca a cero. -->
            <p
                v-if="resumen.reevaluacionVencida > 0 || resumen.certificacionCaducada > 0 || resumen.sinEvaluar > 0 || resumen.condicionados > 0"
                class="flex flex-wrap gap-x-4 gap-y-1 border-t pt-4 text-sm"
            >
                <Link
                    v-if="resumen.reevaluacionVencida > 0"
                    href="/proveedores?filter[reevaluacion_vencida]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="resumen.reevaluacionVencida" />
                    con la reevaluación vencida
                </Link>

                <Link
                    v-if="resumen.certificacionCaducada > 0"
                    href="/proveedores?filter[certificacion_caducada]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="resumen.certificacionCaducada" />
                    con una certificación caducada
                </Link>

                <Link
                    v-if="resumen.sinEvaluar > 0"
                    href="/proveedores?filter[sin_evaluar]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.sinEvaluar" />
                    sin evaluar
                </Link>

                <Link
                    v-if="resumen.condicionados > 0"
                    href="/proveedores?filter[condicionados]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.condicionados" />
                    {{ resumen.condicionados === 1 ? 'condicionado' : 'condicionados' }}
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
