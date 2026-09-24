<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * El contexto de la organización, de un vistazo (§ 4.14).
 *
 * Contesta tres preguntas y en este orden: **desde cuándo** es lo que hay firmado,
 * **qué forma tiene** el DAFO y **qué se ha quedado sin atar**. La primera es la
 * que de verdad importa: un análisis del contexto de hace tres años es un análisis
 * que ya no describe a nadie, y es lo primero que un auditor mira.
 *
 * **Ninguna cifra en rojo.** El rojo tiene tres dueños declarados y los tres son
 * «va mal de verdad»; aquí lo peor que hay es trabajo por hacer sobre algo que la
 * organización ha sabido ver y escribir. Pintarlo de alarma sería castigar por
 * haber hecho el análisis.
 *
 * **El reparto va con los cuadrantes vacíos dentro**, a diferencia de casi todos
 * los del producto, que filtran los tramos a cero. Aquí el cero dice algo: un DAFO
 * sin ninguna oportunidad es una organización que sólo ha mirado lo que le puede
 * salir mal, y esconder la barra vacía esconde justo eso.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenContextoPanel }>();

const barras = computed<Barra[]>(() =>
    props.resumen.porTipo.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        de: props.resumen.cuestiones,
        tono: tramo.tono,
    })),
);

/** Un análisis viejo no es un fallo, es una pregunta. De ahí el «hace N meses». */
const antiguedad = computed(() => {
    const meses = props.resumen.mesesDesdeElAnalisis;

    if (meses === null) {
        return null;
    }

    if (meses < 1) {
        return 'este mes';
    }

    return meses === 1 ? 'hace un mes' : `hace ${meses} meses`;
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Contexto de la organización</CardTitle>
            <CardDescription>
                Lo que hay a favor y en contra, y quién exige qué. Las cláusulas 4.1 y 4.2 son de
                donde salen el alcance del SGSI y la apreciación de riesgos.
            </CardDescription>
            <CardAction>
                <Link
                    href="/contexto"
                    class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver el contexto
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-6 pt-0">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-10">
                <div class="shrink-0">
                    <p class="flex items-baseline gap-1.5">
                        <Cifra class="text-3xl font-bold" :valor="resumen.cuestiones" />
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ resumen.cuestiones === 1 ? 'cuestión vigente' : 'cuestiones vigentes' }}
                    </p>
                    <p v-if="resumen.analisisVigente" class="mt-2 text-xs text-muted-foreground">
                        {{ resumen.analisisVigente }}, {{ antiguedad }}
                    </p>
                    <p v-else class="mt-2 text-xs text-muted-foreground">Sin análisis aprobado</p>
                </div>

                <div v-if="resumen.cuestiones > 0" class="min-w-0 flex-1">
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">Cómo se reparte</p>
                    <GraficaBarras :barras="barras" />
                </div>
            </div>

            <!--
                Lo que queda por atar, en una línea y nunca a cero: una línea que
                dice «0 sin riesgo» enseña a no leer la línea.
            -->
            <p
                v-if="resumen.sinRiesgo > 0 || resumen.obligacionesSinCubrir > 0 || resumen.climaPertinente === null"
                class="flex flex-wrap gap-x-4 gap-y-1 border-t pt-4 text-sm"
            >
                <Link
                    v-if="resumen.sinRiesgo > 0"
                    href="/contexto/cuestiones?filter[sin_riesgo]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.sinRiesgo" />
                    sin riesgo vinculado
                </Link>

                <Link
                    v-if="resumen.obligacionesSinCubrir > 0"
                    href="/partes-interesadas?filter[obligacion_sin_cubrir]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.obligacionesSinCubrir" />
                    de {{ resumen.requisitosQueObligan }} obligaciones sin cubrir
                </Link>

                <!--
                    La enmienda 1:2024 obliga a **determinar si** el cambio climático
                    es pertinente. Sin contestar no es lo mismo que «no aplica», y es
                    lo que impide aprobar el análisis.
                -->
                <Link
                    v-if="resumen.climaPertinente === null"
                    href="/contexto"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    cambio climático sin declarar
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
