<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
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

const barras = computed<Barra[]>(() =>
    props.resumen.porSeveridad.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        de: props.resumen.vivas,
        tono: tramo.tono,
    })),
);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Vulnerabilidades</CardTitle>
            <CardDescription>
                Los fallos técnicos que siguen sin arreglo y cuánto pesan. Es <span class="cifra">op.exp.4</span>,
                exigible desde categoría básica.
            </CardDescription>
            <CardAction>
                <Link
                    href="/vulnerabilidades"
                    class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver las vulnerabilidades
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-6 pt-0">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-10">
                <div class="shrink-0">
                    <p class="flex items-baseline gap-1.5">
                        <Cifra class="text-2xl font-semibold" :valor="resumen.vivas" />
                        <span class="text-sm text-muted-foreground">de {{ resumen.total }}</span>
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ resumen.vivas === 1 ? 'sigue viva' : 'siguen vivas' }}
                    </p>
                </div>

                <div v-if="resumen.vivas > 0" class="min-w-0 flex-1">
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">Cuánto pesan</p>
                    <GraficaBarras :barras="barras" />
                </div>
            </div>

            <!-- Lo que pide acción, en una línea y nunca a cero. -->
            <p
                v-if="resumen.fueraDePlazo > 0 || resumen.criticasAbiertas > 0 || resumen.sinVerificar > 0 || resumen.aceptadas > 0"
                class="flex flex-wrap gap-x-4 gap-y-1 border-t pt-4 text-sm"
            >
                <Link
                    v-if="resumen.fueraDePlazo > 0"
                    href="/vulnerabilidades?filter[fuera_de_plazo]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="resumen.fueraDePlazo" />
                    fuera de plazo
                </Link>

                <Link
                    v-if="resumen.criticasAbiertas > 0"
                    href="/vulnerabilidades?filter[criticas_abiertas]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.criticasAbiertas" />
                    {{ resumen.criticasAbiertas === 1 ? 'crítica abierta' : 'críticas abiertas' }}
                </Link>

                <Link
                    v-if="resumen.sinVerificar > 0"
                    href="/vulnerabilidades?filter[sin_verificar]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.sinVerificar" />
                    mitigadas sin verificar
                </Link>

                <Link
                    v-if="resumen.aceptadas > 0"
                    href="/vulnerabilidades?filter[aceptadas]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.aceptadas" />
                    {{ resumen.aceptadas === 1 ? 'aceptada' : 'aceptadas' }} como riesgo asumido
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
