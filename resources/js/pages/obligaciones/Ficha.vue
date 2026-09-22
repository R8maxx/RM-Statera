<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
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
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Referencia = App.Domain.Obligacion.Referencia;

interface OpcionNumerica {
    valor: number;
    etiqueta: string;
}

interface Compromiso {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    notas: string | null;
    cadencia: string;
    periodicidadMeses: number;
    computaDesde: string;
    responsable: string | null;
    sistema: string | null;
    activo: boolean;
    proximaFecha: string;
    proximaEscrita: string;
    dias: number;
    vencido: boolean;
    origen: { codigo: string; nombre: string; baseLegal: string | null; marco: string | null } | null;
}

interface Cumplimiento {
    id: number;
    fecha: string;
    fechaEscrita: string;
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
 * «¿desde cuándo?». Por eso la columna ancha es la lista de cumplimientos y no la
 * descripción.
 *
 * **Cada asiento enseña sus dos fechas**, y no es redundancia: `fecha` es cuándo
 * se cumplió y `registradoEn` cuándo se apuntó. La del auditor es la primera y la
 * de la traza es la segunda, y enseñar sólo una las confunde — mismo reparto que
 * `medidaEn` frente a `registradaPor` en una medición.
 *
 * Un solo elemento fuerte (DESIGN.md § 14): «Registrar cumplimiento». Y **en teal
 * y no en la variante de acento**, aunque una de las obligaciones habituales sea
 * una auditoría: aquí no se abre ningún flujo de revisión, se sella un hecho.
 */
const props = defineProps<{
    compromiso: Compromiso;
    cumplimientos: Cumplimiento[];
    puedeGestionar: boolean;
    auditorias: OpcionNumerica[];
    revisiones: OpcionNumerica[];
    documentos: OpcionNumerica[];
    evidencias: OpcionNumerica[];
}>();

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
    fecha: new Date().toISOString().slice(0, 10),
    cubre_hasta: '',
    auditoria_id: '',
    revision_direccion_id: '',
    documento_id: '',
    evidencia_id: '',
    nota: '',
});

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

const cuando = computed(() => {
    const dias = props.compromiso.dias;

    if (dias < 0) {
        return `venció hace ${Math.abs(dias)} ${dias === -1 ? 'día' : 'días'}`;
    }

    return dias === 0 ? 'vence hoy' : `vence en ${dias} ${dias === 1 ? 'día' : 'días'}`;
});

function registrar(): void {
    formulario.post(`/obligaciones/${props.compromiso.id}/cumplimientos`, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            formulario.reset();
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
    retirada.post(`/obligaciones/${props.compromiso.id}/retirada`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :titulo="compromiso.titulo">
        <CabeceraPagina :titulo="compromiso.titulo" :descripcion="compromiso.descripcion ?? undefined">
            <template #acciones>
                <Button v-if="puedeGestionar && compromiso.activo" @click="abierto = true">
                    Registrar cumplimiento
                </Button>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/obligaciones/${compromiso.id}/editar`">Editar</Link>
                </Button>
                <Button v-if="puedeGestionar && compromiso.activo" variant="ghost" @click="retirar">
                    Retirar
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge :valor="estado" />
            <span class="text-sm text-muted-foreground">
                {{ compromiso.proximaEscrita }} · {{ cuando }}
            </span>
            <span v-if="!compromiso.activo" class="text-sm text-muted-foreground">
                · Retirada. Su histórico se conserva.
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <Card>
                <CardHeader>
                    <CardTitle>Histórico de cumplimiento</CardTitle>
                    <CardDescription>
                        Cada vez que se cumplió, con qué se demuestra y hasta cuándo cubría.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul v-if="cumplimientos.length > 0" class="divide-y">
                        <li v-for="cumplimiento in cumplimientos" :key="cumplimiento.id" class="py-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="cifra text-sm font-medium">{{ cumplimiento.fechaEscrita }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        Cubre hasta {{ cumplimiento.cubreHasta }} ·
                                        apuntado el {{ cumplimiento.registradoEn }}
                                        <template v-if="cumplimiento.registradoPor">
                                            por {{ cumplimiento.registradoPor }}
                                        </template>
                                    </p>

                                    <p v-if="cumplimiento.nota" class="mt-1 max-w-prose text-sm">
                                        {{ cumplimiento.nota }}
                                    </p>

                                    <p class="mt-1.5 flex flex-wrap items-center gap-3 text-xs">
                                        <Link
                                            v-if="cumplimiento.referencia"
                                            :href="cumplimiento.referencia.url"
                                            class="inline-flex items-center gap-1 underline underline-offset-2"
                                        >
                                            <IconoTipo :nombre="cumplimiento.referencia.icono" />
                                            {{ cumplimiento.referencia.etiqueta }}
                                        </Link>
                                        <Link
                                            v-if="cumplimiento.evidencia"
                                            :href="`/evidencias/${cumplimiento.evidencia.id}`"
                                            class="inline-flex items-center gap-1 underline underline-offset-2"
                                        >
                                            <IconoTipo nombre="Paperclip" />
                                            {{ cumplimiento.evidencia.titulo }}
                                        </Link>
                                    </p>
                                </div>

                                <Button
                                    v-if="puedeGestionar"
                                    variant="ghost"
                                    size="sm"
                                    @click="borrando = cumplimiento.id"
                                >
                                    Borrar
                                </Button>
                            </div>
                        </li>
                    </ul>

                    <EstadoVacio
                        v-else
                        titulo="Nunca se ha registrado el cumplimiento"
                        descripcion="Una obligación declarada y nunca cumplida es una promesa, no un control — y es lo primero que se comprueba."
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Qué es y qué la exige</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">Código</dt>
                            <dd class="cifra">{{ compromiso.codigo }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Cadencia</dt>
                            <dd>{{ compromiso.cadencia }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Se cuenta desde</dt>
                            <dd class="cifra">{{ compromiso.computaDesde }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Responsable</dt>
                            <dd>{{ compromiso.responsable ?? 'Sin asignar' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Sistema</dt>
                            <dd>{{ compromiso.sistema ?? 'La organización entera' }}</dd>
                        </div>
                        <!--
                            De dónde sale, citado. Es lo que separa esta ficha de
                            una lista de buenas intenciones, y lo primero que se
                            comprueba.
                        -->
                        <div v-if="compromiso.origen">
                            <dt class="text-xs text-muted-foreground">Del catálogo</dt>
                            <dd>
                                <span class="cifra">{{ compromiso.origen.codigo }}</span>
                                — {{ compromiso.origen.nombre }}
                            </dd>
                        </div>
                        <div v-if="compromiso.origen?.baseLegal">
                            <dt class="text-xs text-muted-foreground">Base</dt>
                            <dd>{{ compromiso.origen.baseLegal }}</dd>
                        </div>
                        <div v-else-if="!compromiso.origen">
                            <dt class="text-xs text-muted-foreground">Del catálogo</dt>
                            <dd class="text-muted-foreground">
                                Obligación propia: no la exige ningún marco cargado.
                            </dd>
                        </div>
                        <div v-if="compromiso.notas">
                            <dt class="text-xs text-muted-foreground">Notas</dt>
                            <dd>{{ compromiso.notas }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>

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
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar cumplimiento</DialogTitle>
                    <DialogDescription>
                        Se sella un hecho, así que la fecha no puede estar en el futuro. La cobertura
                        la calcula la cadencia salvo que la ventana real caiga en otro sitio.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <CampoTexto
                        v-model="formulario.fecha"
                        nombre="fecha"
                        etiqueta="Cuándo se cumplió"
                        tipo="date"
                        :error="formulario.errors.fecha"
                        requerido
                        ayuda="No cuándo se apunta: eso lo guarda la traza por su cuenta."
                    />

                    <CampoTexto
                        v-model="formulario.cubre_hasta"
                        nombre="cubre_hasta"
                        etiqueta="Cubre hasta"
                        tipo="date"
                        :error="formulario.errors.cubre_hasta"
                        ayuda="En blanco lo calcula la cadencia. Se ajusta cuando la ventana real no cae ahí."
                    />

                    <!--
                        Una sola referencia, que es lo que la base impone. La
                        evidencia va aparte porque es otra cosa: es la prueba, y
                        convive con el registro que la originó.
                    -->
                    <CampoSelect
                        v-model="formulario.auditoria_id"
                        nombre="auditoria_id"
                        etiqueta="Auditoría que lo demuestra"
                        :opciones="comoOpciones(auditorias)"
                        :error="formulario.errors.auditoria_id"
                    />

                    <CampoSelect
                        v-model="formulario.revision_direccion_id"
                        nombre="revision_direccion_id"
                        etiqueta="Acta de revisión"
                        :opciones="comoOpciones(revisiones)"
                        :error="formulario.errors.revision_direccion_id"
                    />

                    <CampoSelect
                        v-model="formulario.documento_id"
                        nombre="documento_id"
                        etiqueta="Documento"
                        :opciones="comoOpciones(documentos)"
                        :error="formulario.errors.documento_id"
                    />

                    <CampoSelect
                        v-model="formulario.evidencia_id"
                        nombre="evidencia_id"
                        etiqueta="Evidencia"
                        :opciones="comoOpciones(evidencias)"
                        :error="formulario.errors.evidencia_id"
                        ayuda="Qué lo prueba. Sin prueba, un cumplimiento es una afirmación."
                    />

                    <CampoTextarea
                        v-model="formulario.nota"
                        nombre="nota"
                        etiqueta="Nota"
                        :filas="3"
                        :error="formulario.errors.nota"
                        ayuda="El número de registro del INES, quién auditó, el número de acta."
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
