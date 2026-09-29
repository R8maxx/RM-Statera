<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import BarraCiclo, { type Tramo } from '@/components/obligacion/BarraCiclo.vue';
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
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaDe, fechaLegible, formatoFechaHora, formatoFechaLarga, formatoNumero } from '@/lib/celdas';
import { conOpcionVacia, SIN_VALOR } from '@/lib/formularios';
import { EllipsisIcon, InfoIcon, PencilIcon, PlusIcon } from '@lucide/vue';
import { Link, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import { computed, ref, watch } from 'vue';

type Referencia = App.Domain.Obligacion.Referencia;

interface OpcionNumerica {
    valor: number;
    etiqueta: string;
}

/** Un tipo de registro con el que se demuestra un cumplimiento, y los que hay de él. */
interface TipoReferencia {
    valor: string;
    etiqueta: string;
    /** La columna del formulario a la que va: `auditoria_id`, `documento_id`… */
    campo: 'auditoria_id' | 'revision_direccion_id' | 'documento_id' | 'prueba_continuidad_id';
    opciones: OpcionNumerica[];
}

interface Compromiso {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    notas: string | null;
    motivoRetirada: string | null;
    cadencia: string;
    periodicidadMeses: number;
    computaDesde: string;
    responsable: string | null;
    sistema: string | null;
    activo: boolean;
    proximaFecha: string;
    dias: number;
    vencido: boolean;
    origen: {
        codigo: string;
        nombre: string;
        baseLegal: string | null;
        marco: string | null;
        referenciaSugerida: { valor: string; etiqueta: string } | null;
    } | null;
}

interface Cumplimiento {
    id: number;
    fecha: string;
    cubreHasta: string;
    registradoEn: string;
    registradoPor: string | null;
    nota: string | null;
    referencia: Referencia | null;
    evidencia: { id: number; titulo: string } | null;
}

/**
 * La ficha de un compromiso periódico.
 *
 * El dato **es el histórico**: la pregunta del auditor no es «¿se hace?», es
 * «¿desde cuándo?». Por eso la columna ancha abre con el ciclo —la vida del
 * compromiso en una barra, con los huecos a la vista— y sigue con la tabla de
 * cumplimientos. Una lista de fechas no dice si el segundo cumplimiento llegó a
 * tiempo; la barra sí, sin hacer la cuenta.
 *
 * **Cada asiento enseña sus dos fechas**, y no es redundancia: `fecha` es cuándo
 * se cumplió y `registradoEn` cuándo se apuntó. La del auditor es la primera y la
 * de la traza es la segunda, y enseñar sólo una las confunde — mismo reparto que
 * `medidaEn` frente a `registradaPor` en una medición.
 *
 * Un solo elemento fuerte (DESIGN.md § 14): «Registrar cumplimiento», **en la
 * tarjeta «Estado»** de la columna lateral, que es donde toda ficha pone su
 * cambio de estado (§ 9) y aquí registrar es exactamente eso. En teal y no en la
 * variante de acento, aunque una de las obligaciones habituales sea una
 * auditoría: aquí no se abre ningún flujo de revisión, se sella un hecho. En la
 * cabecera quedan «Editar» y un menú con «Retirar», que es lo que se usa poco.
 */
const props = defineProps<{
    compromiso: Compromiso;
    cumplimientos: Cumplimiento[];
    ciclo: Tramo[];
    hoy: string;
    puedeGestionar: boolean;
    referencias: TipoReferencia[];
    evidencias: OpcionNumerica[];
}>();

const { variantesEntrada } = useMovimientoReducido();

const abierto = ref(false);

/*
 * El borrado va con su propia confirmación y no con `ConfirmacionAccion`, que es
 * de la tabla y recibe una `Accion` declarada por el `Recurso`. Aquí la fila no
 * viene de ahí.
 */
const borrando = ref<number | null>(null);
const borrado = useForm({});

const comoOpciones = (lista: OpcionNumerica[]) =>
    lista.map((item) => ({ valor: String(item.valor), etiqueta: item.etiqueta }));

const formulario = useForm({
    fecha: props.hoy,
    cubre_hasta: '',
    auditoria_id: '',
    revision_direccion_id: '',
    documento_id: '',
    prueba_continuidad_id: '',
    evidencia_id: '',
    nota: '',
});

/*
 * Qué registro lo demuestra, en dos pasos: primero el tipo y luego cuál.
 *
 * Eran cuatro desplegables que parecían independientes, y la base sólo admite
 * una referencia: la regla no se veía hasta enviar, y el error salía colgado del
 * primero. Con el tipo delante, elegir dos es imposible. Arranca en el que el
 * catálogo sugiere para esta obligación —el acta, en la revisión por la
 * dirección—, que es lo que se va a elegir casi siempre.
 */
const tipoSugerido = props.compromiso.origen?.referenciaSugerida?.valor ?? SIN_VALOR;
const tipoReferencia = ref(tipoSugerido);
const registroReferencia = ref(SIN_VALOR);

watch(tipoReferencia, () => {
    registroReferencia.value = SIN_VALOR;
});

const referenciaElegida = computed(() => props.referencias.find((tipo) => tipo.valor === tipoReferencia.value) ?? null);

const errorReferencia = computed(() =>
    props.referencias.map((tipo) => formulario.errors[tipo.campo]).find((error) => error !== undefined),
);

/*
 * Retirar abre su confirmación y pide el motivo.
 *
 * Se ejecutaba de un clic, mientras que borrar un cumplimiento —que es
 * reversible registrándolo otra vez— sí preguntaba. Y el `motivo` estaba
 * declarado y no lo rellenaba nadie, así que la columna nacía siempre vacía.
 *
 * Retirar saca el compromiso del calendario y del panel de golpe: § 1 de
 * DESIGN.md pide confirmación explícita para eso, y el auditor pregunta por qué
 * se dejó de hacer tanto como por si se hacía.
 */
const retirando = ref(false);
const retirada = useForm({ motivo: '' });

const estado = computed(() => {
    if (!props.compromiso.activo) {
        return { valor: 'retirado', etiqueta: 'Retirada', tono: 'no_aplica', icono: 'Ban' };
    }

    if (props.cumplimientos.length === 0) {
        return { valor: 'nunca', etiqueta: 'Nunca cumplida', tono: 'no_iniciado', icono: 'Circle' };
    }

    return props.compromiso.vencido
        ? { valor: 'vencida', etiqueta: 'Fuera de plazo', tono: 'caducada', icono: 'TriangleAlert' }
        : { valor: 'al_dia', etiqueta: 'Al día', tono: 'implantado', icono: 'CircleCheck' };
});

const dias = (n: number) => `${formatoNumero.format(n)} ${n === 1 ? 'día' : 'días'}`;

/** «en 267 días», «hoy», «hace 3 días»: detrás de la fecha larga, como pide § 13. */
const cuando = computed(() => {
    const restantes = props.compromiso.dias;

    if (restantes === 0) {
        return 'hoy';
    }

    return restantes > 0 ? `en ${dias(restantes)}` : `hace ${dias(Math.abs(restantes))}`;
});

const fechaLarga = (valor: string) => {
    const fecha = fechaDe(valor);

    return fecha ? formatoFechaLarga.format(fecha) : valor;
};

const ultimo = computed(() => props.cumplimientos[0] ?? null);

const sinPrueba = (cumplimiento: Cumplimiento) => cumplimiento.referencia === null && cumplimiento.evidencia === null;

/*
 * Lo que un auditor va a pedir y falta (DESIGN.md § 9, «Lo que falta»). Sólo lo
 * que se arregla editando la ficha: la prueba de un cumplimiento ya apuntado no
 * se edita en el sitio —se borra y se vuelve a registrar—, así que se dice en su
 * fila y no aquí, donde un chip tendría que llevar a alguna parte.
 */
const pendientes = computed(() => {
    const faltan: string[] = [];

    if (props.compromiso.activo && props.compromiso.responsable === null) {
        faltan.push('Responsable');
    }

    return faltan;
});

function registrar(): void {
    formulario
        .transform((datos) => ({
            ...datos,
            ...Object.fromEntries(
                props.referencias.map((tipo) => [
                    tipo.campo,
                    tipo.valor === tipoReferencia.value ? registroReferencia.value : SIN_VALOR,
                ]),
            ),
        }))
        .post(`/obligaciones/${props.compromiso.id}/cumplimientos`, {
            preserveScroll: true,
            onSuccess: () => {
                abierto.value = false;
                formulario.reset();
                tipoReferencia.value = tipoSugerido;
            },
        });
}

function borrarCumplimiento(): void {
    if (borrando.value === null) {
        return;
    }

    borrado.delete(`/obligaciones/${props.compromiso.id}/cumplimientos/${borrando.value}`, {
        preserveScroll: true,
        onFinish: () => {
            borrando.value = null;
        },
    });
}

function retirar(): void {
    retirada.post(`/obligaciones/${props.compromiso.id}/retirada`, {
        preserveScroll: true,
        onSuccess: () => {
            retirando.value = false;
            retirada.reset();
        },
    });
}
</script>

<template>
    <AppLayout :titulo="compromiso.titulo">
        <CabeceraPagina :titulo="compromiso.titulo" :codigo="compromiso.codigo" :descripcion="compromiso.descripcion">
            <template v-if="puedeGestionar" #acciones>
                <Button as-child variant="outline">
                    <Link :href="`/obligaciones/${compromiso.id}/editar`">
                        <PencilIcon aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
                <DropdownMenu v-if="compromiso.activo">
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Más acciones">
                            <EllipsisIcon aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem @select="retirando = true">Retirar la obligación</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </CabeceraPagina>

        <motion.div :variants="variantesEntrada" initial="oculto" animate="visible" class="space-y-6">
            <section
                v-if="pendientes.length > 0"
                aria-labelledby="pendientes"
                class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border bg-card px-5 py-3.5"
            >
                <InfoIcon class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                <h2 id="pendientes" class="text-sm font-semibold">
                    {{ pendientes.length }} {{ pendientes.length === 1 ? 'dato sin completar' : 'datos sin completar' }}
                </h2>
                <ul class="flex flex-1 flex-wrap gap-2">
                    <li v-for="pendiente in pendientes" :key="pendiente">
                        <Link
                            v-if="puedeGestionar"
                            :href="`/obligaciones/${compromiso.id}/editar`"
                            class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            {{ pendiente }}
                        </Link>
                        <span
                            v-else
                            class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground"
                        >
                            {{ pendiente }}
                        </span>
                    </li>
                </ul>
                <p class="text-xs text-muted-foreground">Sin responsable, nadie responde por ella en la auditoría.</p>
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start">
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Ciclo</CardTitle>
                            <CardDescription>
                                Qué periodos quedaron cubiertos y cuáles no, desde que empezó a contar.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BarraCiclo
                                :tramos="ciclo"
                                :hoy="hoy"
                                :vence="compromiso.activo ? compromiso.proximaFecha : null"
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Histórico de cumplimiento</CardTitle>
                            <CardDescription>
                                Cuándo se cumplió, hasta cuándo cubría, con qué se demuestra y cuándo se apuntó.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table v-if="cumplimientos.length > 0">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Cumplida</TableHead>
                                        <TableHead>Cubre hasta</TableHead>
                                        <TableHead>Prueba</TableHead>
                                        <TableHead>Apuntada</TableHead>
                                        <TableHead v-if="puedeGestionar" class="w-12">
                                            <span class="sr-only">Acciones</span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="cumplimiento in cumplimientos" :key="cumplimiento.id">
                                        <TableCell class="cifra align-top font-medium">
                                            {{ fechaLegible(cumplimiento.fecha) }}
                                        </TableCell>
                                        <TableCell class="cifra align-top">
                                            {{ fechaLegible(cumplimiento.cubreHasta) }}
                                        </TableCell>
                                        <TableCell class="align-top whitespace-normal">
                                            <div class="grid gap-1">
                                                <Link
                                                    v-if="cumplimiento.referencia"
                                                    :href="cumplimiento.referencia.url"
                                                    class="inline-flex items-center gap-1.5 text-primary underline-offset-4 hover:underline"
                                                >
                                                    <IconoTipo :nombre="cumplimiento.referencia.icono" />
                                                    {{ cumplimiento.referencia.etiqueta }}
                                                </Link>
                                                <Link
                                                    v-if="cumplimiento.evidencia"
                                                    :href="`/evidencias/${cumplimiento.evidencia.id}`"
                                                    class="inline-flex items-center gap-1.5 text-primary underline-offset-4 hover:underline"
                                                >
                                                    <IconoTipo nombre="Paperclip" />
                                                    {{ cumplimiento.evidencia.titulo }}
                                                </Link>
                                                <span v-if="sinPrueba(cumplimiento)" class="text-muted-foreground">
                                                    Sin prueba
                                                </span>
                                                <p v-if="cumplimiento.nota" class="max-w-prose text-muted-foreground">
                                                    {{ cumplimiento.nota }}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <span class="cifra block">
                                                {{ formatoFechaHora.format(new Date(cumplimiento.registradoEn)) }}
                                            </span>
                                            <span class="block text-xs text-muted-foreground">
                                                {{
                                                    cumplimiento.registradoPor
                                                        ? `por ${cumplimiento.registradoPor}`
                                                        : 'Sin autor en la traza'
                                                }}
                                            </span>
                                        </TableCell>
                                        <TableCell v-if="puedeGestionar" class="text-right align-top">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        :aria-label="`Acciones del cumplimiento del ${fechaLegible(cumplimiento.fecha)}`"
                                                    >
                                                        <EllipsisIcon aria-hidden="true" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="w-48">
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        @select="borrando = cumplimiento.id"
                                                    >
                                                        Borrar el cumplimiento
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow class="hover:bg-transparent">
                                        <TableCell class="cifra align-top text-muted-foreground">
                                            {{ fechaLegible(compromiso.computaDesde) }}
                                        </TableCell>
                                        <TableCell :colspan="puedeGestionar ? 4 : 3" class="whitespace-normal text-muted-foreground">
                                            Empieza a contar. Desde aquí se mide el primer vencimiento.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>

                            <EstadoVacio
                                v-else
                                titulo="Nunca se ha registrado el cumplimiento"
                                descripcion="Una obligación declarada y nunca cumplida es una promesa, no un control — y es lo primero que se comprueba."
                            />
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between gap-3">
                            <CardTitle>Estado</CardTitle>
                            <CeldaBadge :valor="estado" />
                        </CardHeader>
                        <CardContent class="grid gap-4">
                            <dl class="grid gap-3">
                                <div v-if="compromiso.activo">
                                    <dt class="text-xs text-muted-foreground">
                                        {{ compromiso.vencido ? 'Venció' : 'Próximo vencimiento' }}
                                    </dt>
                                    <dd class="text-base font-semibold">{{ fechaLarga(compromiso.proximaFecha) }}</dd>
                                    <dd
                                        class="text-[13px]"
                                        :class="compromiso.vencido ? 'text-destructive' : 'text-muted-foreground'"
                                    >
                                        {{ cuando }}
                                    </dd>
                                </div>
                                <div v-else>
                                    <dt class="text-xs text-muted-foreground">Retirada</dt>
                                    <dd class="text-sm">Ya no vence. Su histórico se conserva.</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Último cumplimiento</dt>
                                    <dd class="text-sm">
                                        <template v-if="ultimo">
                                            {{ fechaLarga(ultimo.fecha) }}{{ sinPrueba(ultimo) ? ' · sin prueba' : '' }}
                                        </template>
                                        <span v-else class="text-muted-foreground">Ninguno todavía</span>
                                    </dd>
                                </div>
                            </dl>

                            <Button v-if="puedeGestionar && compromiso.activo" size="lg" class="w-full" @click="abierto = true">
                                <PlusIcon aria-hidden="true" />
                                Registrar cumplimiento
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ficha</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-3 text-sm">
                                <dt class="text-[13px] text-muted-foreground">Cadencia</dt>
                                <dd>{{ compromiso.cadencia }}</dd>
                                <dt class="text-[13px] text-muted-foreground">Se cuenta desde</dt>
                                <dd class="cifra">{{ fechaLegible(compromiso.computaDesde) }}</dd>
                                <dt class="text-[13px] text-muted-foreground">Responsable</dt>
                                <dd>
                                    <span v-if="compromiso.responsable">{{ compromiso.responsable }}</span>
                                    <span v-else class="flex flex-wrap gap-2">
                                        <span class="text-muted-foreground">Sin asignar</span>
                                        <Link
                                            v-if="puedeGestionar"
                                            :href="`/obligaciones/${compromiso.id}/editar`"
                                            class="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            Asignar
                                        </Link>
                                    </span>
                                </dd>
                                <dt class="text-[13px] text-muted-foreground">Alcance</dt>
                                <dd>{{ compromiso.sistema ?? 'La organización entera' }}</dd>
                                <template v-if="compromiso.notas">
                                    <dt class="text-[13px] text-muted-foreground">Notas</dt>
                                    <dd class="whitespace-pre-line">{{ compromiso.notas }}</dd>
                                </template>
                                <template v-if="compromiso.motivoRetirada">
                                    <dt class="text-[13px] text-muted-foreground">Por qué se retiró</dt>
                                    <dd class="whitespace-pre-line">{{ compromiso.motivoRetirada }}</dd>
                                </template>
                            </dl>
                        </CardContent>
                    </Card>

                    <!--
                        De dónde sale, citado. Es lo que separa esta ficha de una
                        lista de buenas intenciones, y lo primero que se comprueba.
                        Con la regla de 2 px de lo que viene de otra ficha (§ 9),
                        no con una caja dentro de la tarjeta.
                    -->
                    <Card>
                        <CardHeader>
                            <CardTitle>De dónde sale</CardTitle>
                        </CardHeader>
                        <CardContent class="grid gap-4 text-sm">
                            <template v-if="compromiso.origen">
                                <blockquote class="grid gap-1 border-l-2 border-border pl-3.5">
                                    <span v-if="compromiso.origen.baseLegal" class="font-medium">
                                        {{ compromiso.origen.baseLegal }}
                                    </span>
                                    <span class="text-[13px] text-muted-foreground">{{ compromiso.origen.nombre }}</span>
                                    <span class="cifra text-xs text-muted-foreground">{{ compromiso.origen.codigo }}</span>
                                </blockquote>
                                <p v-if="compromiso.origen.referenciaSugerida" class="text-[13px] text-secondary-foreground">
                                    Se demuestra de costumbre con: {{ compromiso.origen.referenciaSugerida.etiqueta.toLowerCase() }}.
                                </p>
                            </template>
                            <p v-else class="text-muted-foreground">
                                Obligación propia: no la exige ningún marco cargado.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </motion.div>

        <Dialog v-model:open="retirando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Retirar la obligación?</DialogTitle>
                    <DialogDescription>
                        Deja de contar en el calendario y en el panel, y su histórico de cumplimiento
                        se conserva entero: es la prueba de que se cumplió mientras aplicaba.
                    </DialogDescription>
                </DialogHeader>

                <CampoTextarea
                    v-model="retirada.motivo"
                    nombre="motivo"
                    etiqueta="Por qué deja de aplicar"
                    :filas="3"
                    :error="retirada.errors.motivo"
                    ayuda="Se guarda aparte de las notas y se enseña en la ficha. «Dejasteis de presentarlo, ¿por qué?» es una pregunta de auditoría."
                />

                <DialogFooter>
                    <Button variant="outline" @click="retirando = false">Cancelar</Button>
                    <Button :disabled="retirada.processing" @click="retirar">Retirar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="borrando !== null" @update:open="(v: boolean) => !v && (borrando = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Borrar el cumplimiento?</DialogTitle>
                    <DialogDescription>
                        La próxima fecha vuelve a la que había antes de registrarlo. Un cumplimiento mal
                        apuntado se borra y se vuelve a registrar: editarlo en el sitio no dejaría rastro
                        de que hubo un cambio.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" @click="borrando = null">Cancelar</Button>
                    <Button variant="destructive" :disabled="borrado.processing" @click="borrarCumplimiento">
                        Borrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="abierto">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Registrar cumplimiento</DialogTitle>
                    <DialogDescription>
                        {{ compromiso.titulo }}. Se sella un hecho: la fecha no puede estar en el futuro.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CampoTexto
                            v-model="formulario.fecha"
                            nombre="fecha"
                            etiqueta="Cuándo se cumplió"
                            tipo="date"
                            :error="formulario.errors.fecha"
                            requerido
                            ayuda="El día del hecho. Cuándo se apunta lo guarda la traza."
                        />

                        <CampoTexto
                            v-model="formulario.cubre_hasta"
                            nombre="cubre_hasta"
                            etiqueta="Cubre hasta"
                            tipo="date"
                            :error="formulario.errors.cubre_hasta"
                            :ayuda="`En blanco, una cadencia después de la fecha (${compromiso.cadencia.toLowerCase()}).`"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <CampoSelect
                            v-model="tipoReferencia"
                            nombre="tipo_referencia"
                            etiqueta="Qué registro lo demuestra"
                            :opciones="conOpcionVacia(referencias.map(({ valor, etiqueta }) => ({ valor, etiqueta })), 'Ninguno')"
                            ayuda="Uno solo. La evidencia va aparte."
                        />

                        <CampoSelect
                            v-if="referenciaElegida"
                            :key="referenciaElegida.valor"
                            v-model="registroReferencia"
                            nombre="registro_referencia"
                            :etiqueta="referenciaElegida.etiqueta"
                            :opciones="conOpcionVacia(comoOpciones(referenciaElegida.opciones), 'Ninguno')"
                            :error="errorReferencia"
                            :ayuda="referenciaElegida.opciones.length === 0 ? 'Todavía no hay ninguno registrado.' : undefined"
                        />
                    </div>

                    <CampoSelect
                        v-model="formulario.evidencia_id"
                        nombre="evidencia_id"
                        etiqueta="Evidencia"
                        :opciones="conOpcionVacia(comoOpciones(evidencias), 'Ninguna')"
                        :error="formulario.errors.evidencia_id"
                        ayuda="El fichero que lo prueba. Sin prueba, un cumplimiento es una afirmación."
                    />

                    <CampoTextarea
                        v-model="formulario.nota"
                        nombre="nota"
                        etiqueta="Nota"
                        :filas="3"
                        :error="formulario.errors.nota"
                        ayuda="El número de acta, quién asistió, el número de registro del INES."
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="formulario.processing" @click="registrar">Registrar cumplimiento</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
