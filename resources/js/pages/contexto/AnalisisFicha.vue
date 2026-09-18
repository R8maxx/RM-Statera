<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { tono } from '@/lib/tonos';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Una revisión del contexto: qué se firmó y qué cambió respecto a la anterior.
 *
 * **La instantánea no es decoración.** Las cuestiones y las partes viven y se
 * editan —tienen que hacerlo, o los vínculos a riesgos e implantaciones se
 * quedarían apuntando a filas muertas—, así que sin ella retocar una debilidad en
 * 2027 cambiaría lo que dijo el análisis de 2026. Lo que se enseña aquí es lo que
 * se congeló, no lo que hay hoy en las tablas.
 *
 * **Lo que esta pantalla no contesta**: qué cambió por dentro de una cuestión que
 * sigue en las dos revisiones. Está en las dos instantáneas y se podría comparar,
 * pero eso es una pantalla de diff y no existe todavía.
 */

interface Analisis {
    id: number;
    numero: number | null;
    etiqueta: string;
    fechaAnalisis: string;
    estado: { valor: string; etiqueta: string; tono: string; icono: string };
    climaPertinente: boolean | null;
    climaJustificacion: string | null;
    nota: string | null;
    creadoPor: string | null;
    aprobadoPor: string | null;
    aprobadoEn: string | null;
    tieneInstantanea: boolean;
}

interface CuestionResumen {
    id: number;
    codigo: string;
    titulo: string;
    tipoEtiqueta: string;
    tono: string;
    icono: string;
    motivoBaja: string | null;
}

interface ParteResumen {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    ambito: string;
    motivoBaja: string | null;
}

const props = defineProps<{
    analisis: Analisis;
    instantanea: Record<string, unknown> | null;
    cambios: {
        cuestionesAltas: CuestionResumen[];
        cuestionesBajas: CuestionResumen[];
        partesAltas: ParteResumen[];
        partesBajas: ParteResumen[];
    };
    puedeAprobar: boolean;
}>();

const enviando = ref(false);

const sinCambios = computed(
    () =>
        props.cambios.cuestionesAltas.length === 0 &&
        props.cambios.cuestionesBajas.length === 0 &&
        props.cambios.partesAltas.length === 0 &&
        props.cambios.partesBajas.length === 0,
);

const climaTexto = computed(() => {
    if (props.analisis.climaPertinente === null) {
        return 'Sin contestar.';
    }

    return props.analisis.climaPertinente
        ? 'Sí, es una cuestión pertinente.'
        : 'No es una cuestión pertinente.';
});

/* Alcance congelado: lo que decía cada sistema el día de la firma. */
const alcance = computed(
    () => (props.instantanea?.alcance ?? []) as {
        codigo: string;
        nombre: string;
        marco: string | null;
        alcanceDeclarado: string | null;
        exclusiones: string | null;
    }[],
);

function aprobar(): void {
    enviando.value = true;
    router.post(
        `/contexto/analisis/${props.analisis.id}/aprobacion`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :titulo="analisis.etiqueta">
        <CabeceraPagina
            :titulo="analisis.etiqueta"
            :descripcion="`Análisis del contexto de la organización, con fecha ${analisis.fechaAnalisis}.`"
        >
            <template #acciones>
                <Button variant="outline" size="sm" as-child>
                    <Link href="/contexto/analisis">Volver al historial</Link>
                </Button>
                <Button
                    v-if="puedeAprobar && analisis.numero === null"
                    variant="acento"
                    size="sm"
                    :disabled="enviando"
                    @click="aprobar"
                >
                    Aprobar y congelar
                </Button>
            </template>
        </CabeceraPagina>

        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="tono(analisis.estado.tono).badge"
                        >
                            <IconoTipo :nombre="analisis.estado.icono" />
                            {{ analisis.estado.etiqueta }}
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">Fecha del análisis</dt>
                            <dd>{{ analisis.fechaAnalisis }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Aprobado</dt>
                            <dd>
                                <template v-if="analisis.aprobadoPor">
                                    {{ analisis.aprobadoPor }} · {{ analisis.aprobadoEn }}
                                </template>
                                <template v-else>Todavía no</template>
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">Cambio climático</dt>
                            <dd>{{ climaTexto }}</dd>
                            <dd v-if="analisis.climaJustificacion" class="mt-1 text-muted-foreground">
                                {{ analisis.climaJustificacion }}
                            </dd>
                        </div>
                        <div v-if="analisis.nota" class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">Cómo se hizo</dt>
                            <dd>{{ analisis.nota }}</dd>
                        </div>
                    </dl>

                    <p v-if="!analisis.tieneInstantanea" class="text-sm text-muted-foreground">
                        Todavía no hay nada congelado: la instantánea se escribe al aprobar, y es lo
                        que hace que este análisis siga diciendo lo mismo dentro de tres años.
                    </p>
                </CardContent>
            </Card>

            <!-- Qué cambió. -->
            <Card>
                <CardHeader>
                    <CardTitle>Qué cambió en esta revisión</CardTitle>
                </CardHeader>
                <CardContent class="space-y-5">
                    <p v-if="sinCambios" class="text-sm text-muted-foreground">
                        Esta revisión no dio de alta ni retiró nada. Es una respuesta legítima —el
                        contexto puede no haber cambiado— y es la que hay que poder dar por escrito.
                    </p>

                    <section v-if="cambios.cuestionesAltas.length > 0">
                        <h3 class="text-sm font-semibold">Cuestiones nuevas</h3>
                        <ul class="mt-2 space-y-1">
                            <li v-for="cuestion in cambios.cuestionesAltas" :key="cuestion.id" class="text-sm">
                                <Link :href="`/contexto/cuestiones/${cuestion.id}`" class="hover:underline">
                                    <span class="cifra text-xs text-muted-foreground">{{ cuestion.codigo }}</span>
                                    {{ cuestion.titulo }}
                                </Link>
                                <span
                                    class="ml-1 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs"
                                    :class="tono(cuestion.tono).badge"
                                >
                                    <IconoTipo :nombre="cuestion.icono" />
                                    {{ cuestion.tipoEtiqueta }}
                                </span>
                            </li>
                        </ul>
                    </section>

                    <section v-if="cambios.cuestionesBajas.length > 0">
                        <h3 class="text-sm font-semibold">Cuestiones retiradas</h3>
                        <ul class="mt-2 space-y-1">
                            <li v-for="cuestion in cambios.cuestionesBajas" :key="cuestion.id" class="text-sm">
                                <Link :href="`/contexto/cuestiones/${cuestion.id}`" class="hover:underline">
                                    <span class="cifra text-xs text-muted-foreground">{{ cuestion.codigo }}</span>
                                    {{ cuestion.titulo }}
                                </Link>
                                <p v-if="cuestion.motivoBaja" class="text-xs text-muted-foreground">
                                    {{ cuestion.motivoBaja }}
                                </p>
                            </li>
                        </ul>
                    </section>

                    <section v-if="cambios.partesAltas.length > 0">
                        <h3 class="text-sm font-semibold">Partes interesadas nuevas</h3>
                        <ul class="mt-2 space-y-1">
                            <li v-for="parte in cambios.partesAltas" :key="parte.id" class="text-sm">
                                <Link :href="`/partes-interesadas/${parte.id}`" class="hover:underline">
                                    <span class="cifra text-xs text-muted-foreground">{{ parte.codigo }}</span>
                                    {{ parte.nombre }}
                                </Link>
                                <span class="text-xs text-muted-foreground">· {{ parte.tipo }}</span>
                            </li>
                        </ul>
                    </section>

                    <section v-if="cambios.partesBajas.length > 0">
                        <h3 class="text-sm font-semibold">Partes interesadas retiradas</h3>
                        <ul class="mt-2 space-y-1">
                            <li v-for="parte in cambios.partesBajas" :key="parte.id" class="text-sm">
                                <Link :href="`/partes-interesadas/${parte.id}`" class="hover:underline">
                                    <span class="cifra text-xs text-muted-foreground">{{ parte.codigo }}</span>
                                    {{ parte.nombre }}
                                </Link>
                                <p v-if="parte.motivoBaja" class="text-xs text-muted-foreground">
                                    {{ parte.motivoBaja }}
                                </p>
                            </li>
                        </ul>
                    </section>
                </CardContent>
            </Card>

            <!-- El alcance congelado. -->
            <Card v-if="alcance.length > 0">
                <CardHeader>
                    <CardTitle>Alcance declarado el día de la firma</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Lo que decía cada sistema entonces, no lo que dice hoy. Es lo que le da
                        histórico a la cláusula 4.3 sin duplicar el campo.
                    </p>
                </CardHeader>
                <CardContent class="space-y-3">
                    <article v-for="sistema in alcance" :key="sistema.codigo" class="text-sm">
                        <h3 class="font-medium">
                            <span class="cifra text-xs text-muted-foreground">{{ sistema.codigo }}</span>
                            {{ sistema.nombre }}
                        </h3>
                        <p v-if="sistema.alcanceDeclarado">{{ sistema.alcanceDeclarado }}</p>
                        <p v-else class="text-muted-foreground">Sin alcance declarado ese día.</p>
                        <p v-if="sistema.exclusiones" class="text-muted-foreground">
                            <strong class="font-medium text-foreground">Exclusiones:</strong>
                            {{ sistema.exclusiones }}
                        </p>
                    </article>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
