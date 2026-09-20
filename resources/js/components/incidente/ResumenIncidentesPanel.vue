<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
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

const barras = computed<Barra[]>(() =>
    props.resumen.porEstado.map((tramo) => ({
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
            <CardTitle>Incidentes</CardTitle>
            <CardDescription>
                Qué ha pasado, qué se hizo y qué se aprendió. Es <span class="cifra">op.exp.7</span>,
                exigible desde categoría básica.
            </CardDescription>
            <CardAction>
                <Link
                    href="/incidentes"
                    class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    Ver los incidentes
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-6 pt-0">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-10">
                <div class="shrink-0">
                    <p class="flex items-baseline gap-1.5">
                        <Cifra class="text-3xl font-bold" :valor="resumen.abiertos" />
                        <span class="text-sm text-muted-foreground">de {{ resumen.total }}</span>
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ resumen.abiertos === 1 ? 'incidente sin cerrar' : 'incidentes sin cerrar' }}
                    </p>
                </div>

                <div v-if="resumen.total > 0" class="min-w-0 flex-1">
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">En qué punto están</p>
                    <GraficaBarras :barras="barras" />
                </div>
            </div>

            <!--
                Lo que pide acción, en una línea y nunca a cero: una línea que
                dice «0 fuera de plazo» enseña a no leer la línea.
            -->
            <p
                v-if="resumen.fueraDePlazoAepd > 0 || resumen.enPlazoAepd > 0 || resumen.sinLeccion > 0"
                class="flex flex-wrap gap-x-4 gap-y-1 border-t pt-4 text-sm"
            >
                <Link
                    v-if="resumen.fueraDePlazoAepd > 0"
                    href="/incidentes?filter[fuera_de_plazo_aepd]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="resumen.fueraDePlazoAepd" />
                    fuera de plazo con la AEPD
                </Link>

                <Link
                    v-if="resumen.enPlazoAepd > 0"
                    href="/incidentes?filter[en_plazo_aepd]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.enPlazoAepd" />
                    pendientes de notificar a la AEPD
                </Link>

                <Link
                    v-if="resumen.sinLeccion > 0"
                    href="/incidentes?filter[sin_leccion]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.sinLeccion" />
                    resueltos sin lección aprendida
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
