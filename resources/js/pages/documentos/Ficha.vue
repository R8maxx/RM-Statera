<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import HistorialVersiones, { type Version } from '@/components/documento/HistorialVersiones.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm, usePoll } from '@inertiajs/vue3';
import { DownloadIcon, FileTextIcon, PencilIcon, RefreshCwIcon, StampIcon, TypeIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface VersionEnCurso extends Version {
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    enCurso: boolean;
    descargable: boolean;
    emisible: boolean;
    error: string | null;
    totalRequisitos: number | null;
    totalExcluidos: number | null;
    totalImplantados: number | null;
}

const props = defineProps<{
    documento: {
        id: number;
        codigo: string;
        titulo: string;
        tipo: string;
        tipoEtiqueta: string;
        clasificacion: string;
        clasificacionEtiqueta: string;
        sistema: string | null;
        sistemaCodigo: string | null;
        marco: string | null;
        responsable: string | null;
        notas: string | null;
    };
    versionEnCurso: VersionEnCurso | null;
    versiones: Version[];
    cuerpoMasNuevoQueElBorrador: boolean;
}>();

const generar = useForm({});
const emision = useForm({ motivo: '' });

const enCurso = computed(() => props.versionEnCurso?.enCurso ?? false);

/*
 * Mientras el trabajo está vivo hay que preguntarle al servidor, porque el
 * worker corre en otro proceso: no puede mandar un flash ni hay broadcasting en
 * el stack. `defer` tampoco sirve —resuelve en UNA petición de seguimiento y no
 * reintenta—, así que es un poll corto y acotado.
 *
 * `keepAlive: false` lo para con la pestaña en segundo plano, la misma lógica
 * que `Cifra` y la balanza del acceso.
 */
const { start, stop } = usePoll(
    3000,
    { only: ['versionEnCurso', 'versiones'] },
    { keepAlive: false, autoStart: false },
);

/**
 * Y se rinde a los dos minutos.
 *
 * Un poll infinito contra una cola atascada es un bucle caliente contra el
 * servidor que nadie mira: pasado ese rato se dice que está tardando y se deja
 * un botón para comprobar a mano.
 */
const LIMITE_MS = 120_000;
const rendido = ref(false);
let desde: number | null = null;
let temporizador: number | null = null;

function pararTodo(): void {
    stop();
    if (temporizador !== null) {
        window.clearTimeout(temporizador);
        temporizador = null;
    }
}

watch(
    enCurso,
    (vivo, anterior) => {
        if (vivo) {
            rendido.value = false;
            desde = Date.now();
            start();
            temporizador = window.setTimeout(() => {
                rendido.value = true;
                stop();
            }, LIMITE_MS);

            return;
        }

        pararTodo();

        // El servidor anuncia lo que hizo —«generación encolada»— y el cliente
        // anuncia lo que vio. El aviso de fin sólo puede salir de aquí.
        if (anterior === true && desde !== null) {
            desde = null;
            if (props.versionEnCurso?.estado === 'generada') {
                toast.success('El borrador está listo.');
            } else if (props.versionEnCurso?.estado === 'fallida') {
                toast.error('La generación falló.');
            }
        }
    },
    { immediate: true },
);

onBeforeUnmount(pararTodo);

function comprobar(): void {
    rendido.value = false;
    router.reload({ only: ['versionEnCurso', 'versiones'] });
}

/*
 * Un solo botón de color lleno por vista (DESIGN.md §9): con un borrador listo,
 * la acción que manda es emitirlo, así que regenerar baja a secundaria. Dos
 * llenos a la vez y no manda ninguno.
 */
const varianteGenerar = computed(() => (props.versionEnCurso?.emisible ? 'outline' : 'default'));

const kb = (bytes: number | null | undefined): string =>
    bytes === null || bytes === undefined ? '—' : `${Math.round(bytes / 1024)} kB`;
</script>

<template>
    <AppLayout :titulo="documento.codigo">
        <CabeceraPagina :titulo="documento.titulo" :descripcion="documento.tipoEtiqueta">
            <template #acciones>
                <Button as-child variant="outline">
                    <Link :href="`/documentos/${documento.id}/cuerpo`">
                        <TypeIcon class="size-4" />
                        Editar documento
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="`/documentos/${documento.id}/editar`">
                        <PencilIcon class="size-4" />
                        Editar
                    </Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Borrador</CardTitle>
                    <CardDescription>
                        Se genera a partir de lo que hay registrado ahora mismo y se puede regenerar
                        cuantas veces haga falta. No es una entrega hasta que se emite.
                    </CardDescription>
                </CardHeader>

                <CardContent class="flex flex-col gap-4">
                    <div v-if="versionEnCurso" class="flex flex-wrap items-center gap-3">
                        <CeldaBadge
                            :valor="{
                                valor: versionEnCurso.estado,
                                etiqueta: versionEnCurso.estadoEtiqueta,
                                tono: versionEnCurso.estadoTono,
                            }"
                        />
                        <span v-if="versionEnCurso.descargable" class="text-sm text-muted-foreground">
                            {{ kb(versionEnCurso.tamano) }}
                            · {{ versionEnCurso.totalRequisitos }} requisitos
                            · {{ versionEnCurso.totalExcluidos }} excluidos
                            · {{ versionEnCurso.totalImplantados }} implantados
                        </span>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        No hay ningún borrador pendiente.
                    </p>

                    <p v-if="versionEnCurso?.error" class="text-sm text-destructive">
                        {{ versionEnCurso.error }}
                    </p>

                    <p v-if="rendido" class="text-sm text-muted-foreground">
                        Está tardando más de lo normal. Puede que la cola no esté procesando trabajos.
                    </p>

                    <!--
                        El PDF en disco es anterior a la última edición del
                        documento. Sin decirlo, alguien edita, descarga, no ve su
                        texto y concluye que el módulo no funciona.
                    -->
                    <p v-if="cuerpoMasNuevoQueElBorrador" class="text-sm text-estado-en-progreso">
                        El borrador es anterior a la última edición del documento. Regenéralo para verla.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            :variant="varianteGenerar"
                            :disabled="enCurso || generar.processing"
                            @click="generar.post(`/documentos/${documento.id}/generar`, { preserveScroll: true })"
                        >
                            <RefreshCwIcon class="size-4" :class="{ 'animate-spin': enCurso }" />
                            {{ versionEnCurso ? 'Regenerar borrador' : 'Generar borrador' }}
                        </Button>

                        <Button v-if="rendido" variant="outline" @click="comprobar">Comprobar</Button>

                        <Button
                            v-if="versionEnCurso?.descargable"
                            as-child
                            variant="outline"
                        >
                            <a :href="`/documentos/${documento.id}/versiones/${versionEnCurso.id}/descargar`">
                                <DownloadIcon class="size-4" />
                                Descargar borrador
                            </a>
                        </Button>

                        <Button v-if="versionEnCurso?.descargable" as-child variant="ghost">
                            <a :href="`/documentos/${documento.id}/versiones/${versionEnCurso.id}/word`">
                                <FileTextIcon class="size-4" />
                                Word
                            </a>
                        </Button>
                    </div>

                    <form
                        v-if="versionEnCurso?.emisible"
                        class="flex flex-col gap-2 border-t border-border pt-4"
                        @submit.prevent="emision.post(`/documentos/${documento.id}/emitir`, { preserveScroll: true })"
                    >
                        <Label for="motivo">
                            Motivo de la entrega
                            <span v-if="versiones.length > 0" class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="motivo"
                            v-model="emision.motivo"
                            placeholder="Entrega a la auditoría de seguimiento"
                        />
                        <p class="text-sm text-muted-foreground">
                            «¿Por qué hay una v{{ versiones.length + 1 }}?» es la primera pregunta del
                            auditor, y contestarla dentro de seis meses no lo hace nadie.
                        </p>
                        <p v-if="emision.errors.motivo" class="text-sm text-destructive">
                            {{ emision.errors.motivo }}
                        </p>
                        <Button type="submit" variant="acento" :disabled="emision.processing" class="self-start">
                            <StampIcon class="size-4" />
                            Emitir versión
                        </Button>
                    </form>

                    <p class="text-sm text-muted-foreground">
                        Las versiones emitidas no se regeneran. El PDF que se descarga es exactamente
                        el que se generó ese día, y su SHA-256 lo demuestra.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Ficha</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3 text-sm">
                    <div>
                        <div class="text-muted-foreground">Código</div>
                        <div class="cifra">{{ documento.codigo }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Sistema</div>
                        <div>{{ documento.sistemaCodigo }} — {{ documento.sistema }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Marco</div>
                        <div>{{ documento.marco ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Clasificación</div>
                        <div>{{ documento.clasificacionEtiqueta }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Responsable</div>
                        <div>{{ documento.responsable ?? 'Sin asignar' }}</div>
                    </div>
                    <div v-if="documento.notas">
                        <div class="text-muted-foreground">Notas</div>
                        <div>{{ documento.notas }}</div>
                    </div>
                </CardContent>
            </Card>

            <Card class="lg:col-span-3">
                <CardHeader>
                    <CardTitle>Versiones emitidas</CardTitle>
                    <CardDescription>
                        Cada entrega con su huella SHA-256. Es lo que permite demostrar que el PDF
                        que se enseña es el que se emitió aquel día.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <HistorialVersiones :versiones="versiones" :documento-id="documento.id" />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
