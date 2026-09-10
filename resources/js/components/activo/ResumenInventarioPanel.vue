<script setup lang="ts">
import AnilloProgreso from '@/components/AnilloProgreso.vue';
import Cifra from '@/components/Cifra.vue';
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
import { computed } from 'vue';

type Reparto = App.Http.Resources.Panel.RepartoInventario;

/**
 * El inventario, de un vistazo.
 *
 * Contesta «qué hay y cómo está», que es la pregunta de un panel. Lo que pide
 * acción hoy no vive aquí sino en la tabla de activos: una pantalla para saber,
 * otra para trabajar.
 *
 * La cifra que abre es **controles resueltos**: activos cuyo cifrado y copia
 * están decididos, ni «no» ni «por confirmar». Viaja siempre con su denominador
 * —el 25 % sobre cuatro activos no dice lo mismo que sobre trescientos— igual
 * que la madurez del panel de cumplimiento.
 */
const props = defineProps<{ inventario: App.Http.Resources.Panel.ResumenInventarioPanel }>();

const porcentaje = computed(() =>
    props.inventario.vigentes === 0
        ? 0
        : Math.round((props.inventario.resueltos / props.inventario.vigentes) * 100),
);

/** Un reparto del servidor, tal y como lo espera cada componente. */
const segmentos = (reparto: Reparto[]): Segmento[] =>
    reparto.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        tono: tramo.tono,
    }));

const barras = (reparto: Reparto[], total: number): Barra[] =>
    reparto.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        de: total,
        tono: tramo.tono,
    }));

const totalTipos = computed(() => props.inventario.porTipo.reduce((suma, t) => suma + t.valor, 0));
const totalCiclo = computed(() => props.inventario.porCicloDeVida.reduce((suma, t) => suma + t.valor, 0));
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Inventario</CardTitle>
            <CardDescription>
                Controles resueltos sobre los activos vigentes: cifrado y copia decididos, ni «no» ni «por
                confirmar».
            </CardDescription>
            <CardAction>
                <Link href="/activos" class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    Ver el inventario
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-8 pt-0">
            <div class="flex flex-col gap-8 sm:flex-row sm:items-center sm:gap-10">
                <AnilloProgreso :valor="porcentaje" :tamano="128" :grosor="9" />

                <div class="min-w-0 flex-1 space-y-5">
                    <p class="text-sm text-muted-foreground">
                        <span class="cifra font-medium text-foreground">
                            <Cifra :valor="inventario.resueltos" />/<Cifra :valor="inventario.vigentes" />
                        </span>
                        activos con los dos controles decididos.
                        <!-- «No aplica» cuenta como resuelto: un router no cifra
                             en reposo porque no almacena nada, y contarlo como
                             pendiente pondría un techo inalcanzable. -->
                    </p>

                    <div>
                        <p class="mb-1.5 text-xs font-medium text-muted-foreground">Cifrado en reposo</p>
                        <BarraSegmentada :segmentos="segmentos(inventario.cifrado)" leyenda />
                    </div>

                    <div>
                        <p class="mb-1.5 text-xs font-medium text-muted-foreground">Copia de seguridad</p>
                        <BarraSegmentada :segmentos="segmentos(inventario.copia)" leyenda />
                    </div>
                </div>
            </div>

            <div class="grid gap-8 border-t pt-6 lg:grid-cols-2">
                <section>
                    <h3 class="mb-4 text-sm font-medium">Por tipo</h3>
                    <GraficaBarras :barras="barras(inventario.porTipo, totalTipos)" />
                </section>

                <section>
                    <h3 class="mb-4 text-sm font-medium">Por ciclo de vida</h3>
                    <GraficaBarras :barras="barras(inventario.porCicloDeVida, totalCiclo)" />
                </section>
            </div>

            <!-- Perfil, no alarma: saber cuánta información sensible se custodia
                 es útil; gastar una tarjeta en decir «cero» no lo es. -->
            <p v-if="inventario.restringidos > 0" class="border-t pt-4 text-sm text-muted-foreground">
                <Link href="/activos?filter[restringida]=1" class="underline-offset-4 hover:underline">
                    <Cifra class="font-medium text-foreground" :valor="inventario.restringidos" />
                    {{ inventario.restringidos === 1 ? 'activo custodia' : 'activos custodian' }}
                    información restringida
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
