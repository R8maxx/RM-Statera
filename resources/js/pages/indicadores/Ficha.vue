<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import GraficaSerie from '@/components/grafica/GraficaSerie.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import SituacionIndicador from '@/components/metrica/SituacionIndicador.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

interface Indicador {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    origen: string;
    origenEtiqueta: string;
    origenIcono: string;
    metodo: string | null;
    calculo: string | null;
    calculoEtiqueta: string | null;
    marco: string | null;
    unidad: string;
    unidadEtiqueta: string;
    periodicidad: string;
    periodicidadEtiqueta: string;
    sentidoEtiqueta: string;
    objetivo: number | null;
    objetivoEscrito: string | null;
    responsable: string | null;
    activo: boolean;
    cumplimiento: string;
    cumplimientoEtiqueta: string;
    cumplimientoTono: string;
    cumplimientoIcono: string;
    periodoSinMedir: boolean;
    periodoACerrar: string;
    esCalculado: boolean;
}

interface Medicion {
    id: number;
    cumplimientoEtiqueta: string;
    cumplimientoTono: string;
    cumplimientoIcono: string;
    periodo: string;
    valor: string;
    fraccion: string | null;
    objetivo: string | null;
    origen: string;
    medidaEn: string;
    nota: string | null;
    registradaPor: string | null;
}

const props = defineProps<{
    indicador: Indicador;
    serie: App.Http.Resources.Metrica.PuntoSerie[];
    situacion: App.Http.Resources.Metrica.SituacionIndicador | null;
    mediciones: Medicion[];
    puedeGestionar: boolean;
}>();

const abierto = ref(false);

/*
 * El periodo se pide como **una fecha cualquiera**, no como dos extremos: la
 * cadencia del indicador decide en qué cubo cae. Dejar escribir los dos extremos
 * permitiría sellar «del 3 de marzo al 7 de abril», que no es ningún trimestre,
 * y la serie tendría puntos que no encajan con ninguno de los demás.
 */
const formulario = useForm({
    fecha: '',
    valor: '',
    numerador: '',
    denominador: '',
    nota: '',
});

const registrar = (): void => {
    formulario.post(`/indicadores/${props.indicador.id}/mediciones`, {
        preserveScroll: true,
        onSuccess: () => {
            formulario.reset();
            abierto.value = false;
        },
    });
};

const medirAhora = (): void => {
    router.post(`/indicadores/${props.indicador.id}/medicion`, {}, { preserveScroll: true });
};

const borrar = (medicion: Medicion): void => {
    router.delete(`/indicadores/${props.indicador.id}/mediciones/${medicion.id}`, { preserveScroll: true });
};

/**
 * El periodo que la gráfica y la lista señalan a la vez: pasar por un punto
 * resalta su fila, y pasar por una fila enciende su punto.
 */
const resaltado = ref<string | null>(null);

/**
 * La fila que acaba de entrar, resaltada un momento y luego en calma.
 *
 * La marca la gráfica, que es quien sabe que un periodo es nuevo y no una
 * corrección. Es el acuse de que la cifra se guardó: sin él, la fila aparece
 * arriba y nada dice que sea la que acabas de sellar.
 */
const recienSellado = ref<string | null>(null);
let temporizador: ReturnType<typeof setTimeout> | undefined;

const alSellar = (etiqueta: string): void => {
    recienSellado.value = etiqueta;
    clearTimeout(temporizador);
    temporizador = setTimeout(() => (recienSellado.value = null), 1400);
};

onBeforeUnmount(() => clearTimeout(temporizador));
</script>

<template>
    <AppLayout :titulo="indicador.codigo">
        <CabeceraPagina :titulo="indicador.nombre" :codigo="indicador.codigo">
            <template #acciones>
                <!--
                    Un solo elemento fuerte por pantalla (DESIGN.md § 14), y en
                    una ficha de indicador ése es medir: los dos botones son
                    excluyentes —uno por origen—, así que nunca hay dos primarios
                    a la vez. «Editar» se queda en `outline` detrás.
                -->
                <Button v-if="puedeGestionar && indicador.esCalculado" @click="medirAhora">
                    Medir {{ indicador.periodoACerrar }}
                </Button>
                <Button v-if="puedeGestionar && !indicador.esCalculado" @click="abierto = true">
                    Registrar medición
                </Button>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/indicadores/${indicador.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge anunciar
                :valor="{
                    valor: indicador.cumplimiento,
                    etiqueta: indicador.cumplimientoEtiqueta,
                    tono: indicador.cumplimientoTono,
                    icono: indicador.cumplimientoIcono,
                }"
            />
            <!-- El único rojo del módulo. Quedarse por debajo del objetivo es la
                 distancia que queda; no medir habiéndose comprometido a medir es
                 la cláusula 9.1 sin hacer. -->
            <CeldaBadge
                v-if="indicador.periodoSinMedir"
                :valor="{
                    valor: 'periodo',
                    etiqueta: `${indicador.periodoACerrar} sin medir`,
                    tono: 'caducada',
                    icono: null,
                }"
            />
            <span v-if="!indicador.activo" class="text-sm text-muted-foreground">
                Retirado del seguimiento. La serie se conserva.
            </span>
        </div>

        <SituacionIndicador v-if="situacion" :situacion="situacion" :unidad="indicador.unidad" />

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Serie</CardTitle>
                        <CardDescription>
                            Cada punto se juzga contra el objetivo que estaba puesto al cerrar
                            ese periodo, no contra el de hoy.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <GraficaSerie
                            v-if="serie.length > 0"
                            :puntos="serie"
                            :nombre="indicador.nombre"
                            :techo="indicador.unidad === 'porcentaje' ? 100 : null"
                            :pendiente="indicador.periodoSinMedir ? indicador.periodoACerrar : null"
                            :resaltado="resaltado"
                            @enfocar="resaltado = $event"
                            @sellado="alSellar"
                        />
                        <EstadoVacio
                            v-else
                            titulo="Todavía no hay mediciones"
                            descripcion="Un indicador declarado y nunca medido es una promesa, no un seguimiento: es lo primero que se comprueba en una auditoría de la cláusula 9.1."
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Mediciones</CardTitle>
                        <CardDescription>
                            Un periodo se mide una vez. Volver a medirlo corrige la cifra y no
                            mueve el objetivo con el que se juzgó.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <!--
                            Entra por arriba, que es de donde viene: la lista va en
                            orden inverso y la que se acaba de sellar es la primera.
                            Y lleva salida, porque puede menguar: borrar una fila sin
                            ella arrastra a las de abajo de un fotograma al siguiente.
                        -->
                        <TransitionGroup
                            v-if="mediciones.length > 0"
                            tag="ol"
                            name="version"
                            class="relative text-sm"
                            leave-active-class="absolute inset-x-0 transition-opacity duration-(--duracion-salida)"
                            leave-to-class="opacity-0"
                        >
                            <li
                                v-for="medicion in mediciones"
                                :key="medicion.id"
                                class="grid grid-cols-[5.5rem_4.5rem_minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 rounded-md border-b border-border px-3 py-3 transition-colors duration-(--duracion-lenta) last:border-b-0"
                                :class="{
                                    'bg-superficie': resaltado === medicion.periodo,
                                    'bg-accent': recienSellado === medicion.periodo,
                                }"
                                @mouseenter="resaltado = medicion.periodo"
                                @mouseleave="resaltado = null"
                            >
                                <span class="font-semibold">{{ medicion.periodo }}</span>
                                <span class="cifra font-semibold">{{ medicion.valor }}</span>
                                <span class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                                    <span v-if="medicion.fraccion" class="text-xs text-muted-foreground tabular-nums">
                                        {{ medicion.fraccion }}
                                    </span>
                                    <CeldaBadge
                                        :valor="{
                                            valor: medicion.cumplimientoTono,
                                            etiqueta: medicion.cumplimientoEtiqueta,
                                            tono: medicion.cumplimientoTono,
                                            icono: medicion.cumplimientoIcono,
                                        }"
                                    />
                                </span>
                                <Button
                                    v-if="puedeGestionar"
                                    variant="ghost"
                                    size="sm"
                                    @click="borrar(medicion)"
                                >
                                    Borrar
                                </Button>
                                <span v-else />
                                <p class="col-span-3 col-start-2 text-xs text-muted-foreground">
                                    <template v-if="medicion.objetivo">
                                        Objetivo aplicado <span class="cifra">{{ medicion.objetivo }}</span> ·
                                    </template>
                                    {{ medicion.origen }} · {{ medicion.medidaEn }}
                                    <template v-if="medicion.registradaPor"> · {{ medicion.registradaPor }}</template>
                                </p>
                                <p v-if="medicion.nota" class="col-span-3 col-start-2 text-xs text-muted-foreground">
                                    {{ medicion.nota }}
                                </p>
                            </li>
                        </TransitionGroup>
                        <EstadoVacio
                            v-else
                            titulo="Sin mediciones"
                            :descripcion="
                                indicador.esCalculado
                                    ? 'Statera cierra el periodo solo cada madrugada; «Medir» lo hace ahora.'
                                    : 'La cifra de este indicador no sale de esta base de datos: se registra a mano.'
                            "
                        />
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <!-- Sólo cuando falta algo: un periodo medido no necesita tarjeta
                     que lo diga, ya lo dicen la franja y la gráfica. -->
                <Card v-if="indicador.periodoSinMedir">
                    <CardHeader>
                        <CardTitle class="text-destructive">{{ indicador.periodoACerrar }} cerró sin medición</CardTitle>
                        <CardDescription>
                            {{
                                indicador.esCalculado
                                    ? `Statera sella el periodo cada madrugada. «Medir ${indicador.periodoACerrar}» lo hace ahora, con el objetivo de hoy dentro.`
                                    : 'La cifra de este indicador no sale de esta base de datos: se registra a mano, con una fecha cualquiera dentro del periodo.'
                            }}
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                        <CardDescription>
                            La cláusula 9.1 b) pregunta por el método, y «¿de dónde sale ese
                            número?» es la primera pregunta de cualquier auditor.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <p v-if="indicador.descripcion" class="text-muted-foreground">
                            {{ indicador.descripcion }}
                        </p>

                        <div class="flex items-start gap-2">
                            <IconoTipo :nombre="indicador.origenIcono" clase="mt-0.5 size-4 shrink-0" />
                            <div>
                                <p class="font-medium">{{ indicador.origenEtiqueta }}</p>
                                <p v-if="indicador.metodo" class="text-muted-foreground">{{ indicador.metodo }}</p>
                            </div>
                        </div>

                        <dl class="grid gap-2">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Cadencia</dt>
                                <dd>{{ indicador.periodicidadEtiqueta }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Unidad</dt>
                                <dd>{{ indicador.unidadEtiqueta }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Objetivo</dt>
                                <dd>
                                    <span v-if="indicador.objetivoEscrito" class="cifra">
                                        {{ indicador.objetivoEscrito }}
                                    </span>
                                    <span v-else class="text-muted-foreground">Sin objetivo declarado</span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Sentido</dt>
                                <dd>{{ indicador.sentidoEtiqueta }}</dd>
                            </div>
                            <div v-if="indicador.marco" class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Marco</dt>
                                <dd>{{ indicador.marco }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Responsable</dt>
                                <dd>{{ indicador.responsable ?? '—' }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar medición</DialogTitle>
                    <DialogDescription>
                        Basta una fecha dentro del periodo: la cadencia decide a cuál
                        corresponde. El periodo en curso no se puede medir todavía.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <CampoTexto
                        v-model="formulario.fecha"
                        nombre="fecha"
                        etiqueta="Fecha del periodo"
                        tipo="date"
                        :error="formulario.errors.fecha"
                        requerido
                    />
                    <CampoTexto
                        v-model="formulario.valor"
                        nombre="valor"
                        etiqueta="Valor"
                        tipo="number"
                        step="0.01"
                        :error="formulario.errors.valor"
                        requerido
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CampoTexto
                            v-model="formulario.numerador"
                            nombre="numerador"
                            etiqueta="Numerador"
                            tipo="number"
                            :error="formulario.errors.numerador"
                            ayuda="Opcional."
                        />
                        <CampoTexto
                            v-model="formulario.denominador"
                            nombre="denominador"
                            etiqueta="Denominador"
                            tipo="number"
                            :error="formulario.errors.denominador"
                            ayuda="«43» no dice lo mismo sobre 4 que sobre 307."
                        />
                    </div>
                    <CampoTextarea
                        v-model="formulario.nota"
                        nombre="nota"
                        etiqueta="Nota"
                        :filas="2"
                        :error="formulario.errors.nota"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="formulario.processing" @click="registrar">Registrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
