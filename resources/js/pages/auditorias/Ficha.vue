<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
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
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Destino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface TipoHallazgo extends Destino {}

interface Hallazgo {
    id: number;
    tipo: string;
    tipoEtiqueta: string;
    tipoTono: string;
    tipoIcono: string;
    exigeNoConformidad: boolean;
    // Desde la cláusula 10.1 hay dos registros de destino, y el tipo del
    // hallazgo decide cuál: una oportunidad de mejora no incumple nada.
    abreMejora: boolean;
    descripcion: string;
    medida: string | null;
    noConformidadId: number | null;
    noConformidadCodigo: string | null;
    mejoraId: number | null;
    mejoraCodigo: string | null;
}

interface Auditoria {
    id: number;
    codigo: string;
    sistema: string | null;
    sistemaNombre: string | null;
    tipo: string;
    tipoEtiqueta: string;
    tipoTono: string;
    tipoIcono: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    alcance: string | null;
    criterios: string | null;
    metodo: string | null;
    fecha: string;
    auditor: string | null;
    equipo: string | null;
    entidad_certificadora: string | null;
    conclusiones: string | null;
    fechaCierre: string | null;
    cerradaPor: string | null;
    admiteCambios: boolean;
}

const props = defineProps<{
    auditoria: Auditoria;
    avance: { revisados: number; total: number };
    hallazgos: Hallazgo[];
    tipos: TipoHallazgo[];
    transiciones: Destino[];
    puedeGestionar: boolean;
    puedeTratar: boolean;
    puedeTratarMejoras: boolean;
    informe: { id: number; codigo: string } | null;
    admiteInforme: boolean;
    puedeGenerar: boolean;
}>();

const pagina = usePage();
const errores = computed(() => (pagina.props.errors ?? {}) as Record<string, string>);

/*
 * El informe (§ 4.18, 9.2.2) sólo se prepara con la auditoría cerrada: es el
 * cierre lo que congela lo que el informe imprime. Una externa no lo tiene aquí,
 * porque lo emite la entidad certificadora.
 */
const cerrada = computed(() => props.auditoria.estado === 'cerrada');
const preparandoInforme = ref(false);

function prepararInforme(): void {
    preparandoInforme.value = true;
    router.post(`/auditorias/${props.auditoria.id}/informe`, {}, {
        onFinish: () => (preparandoInforme.value = false),
    });
}

/*
 * Cerrar pide conclusiones y el resto no, así que el primer clic en «Cerrada» no
 * envía: abre el bloque. Mismo gesto que descartar una tarea, y por el mismo
 * motivo — pedirlas siempre convierte en trámite el único sitio donde se dice qué
 * salió de la auditoría.
 */
const destino = ref<string | null>(null);
const conclusiones = ref('');
const enviando = ref(false);

const exigeConclusiones = computed(() => destino.value === 'cerrada');

function mover(estado: string): void {
    if (estado === 'cerrada' && destino.value !== 'cerrada') {
        destino.value = 'cerrada';
        conclusiones.value = props.auditoria.conclusiones ?? '';

        return;
    }

    enviando.value = true;

    router.post(
        `/auditorias/${props.auditoria.id}/estado`,
        { estado, conclusiones: conclusiones.value },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
                destino.value = null;
            },
        },
    );
}

const precargando = ref(false);

function precargar(): void {
    precargando.value = true;

    router.post(
        `/auditorias/${props.auditoria.id}/checklist`,
        {},
        { preserveScroll: true, onFinish: () => (precargando.value = false) },
    );
}

/* --- Hallazgos --- */

const abierto = ref(false);

const hallazgo = useForm({
    tipo: '',
    descripcion: '',
    auditoria_punto_id: null as number | null,
});

function abrirHallazgo(): void {
    hallazgo.reset();
    hallazgo.clearErrors();
    abierto.value = true;
}

function registrar(): void {
    hallazgo.post(`/auditorias/${props.auditoria.id}/hallazgos`, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            hallazgo.reset();
        },
    });
}

function retirar(id: number): void {
    router.delete(`/auditorias/${props.auditoria.id}/hallazgos/${id}`, { preserveScroll: true });
}

const opcionesTipo = computed(() =>
    props.tipos.map((tipo) => ({ valor: tipo.valor, etiqueta: tipo.etiqueta })),
);

const noConformidades = computed(() => props.hallazgos.filter((h) => h.exigeNoConformidad).length);

/*
 * Las que obligan a tratamiento y no lo tienen. Es la costura entre las dos
 * mitades del módulo: una no conformidad mayor sin nada detrás es un hallazgo de
 * la auditoría siguiente, y aquí es donde se ve.
 */
const sinTratar = computed(
    () => props.hallazgos.filter((h) => h.exigeNoConformidad && h.noConformidadId === null).length,
);
</script>

<template>
    <AppLayout :titulo="auditoria.codigo">
        <CabeceraPagina
            :titulo="auditoria.codigo"
            :descripcion="auditoria.sistemaNombre ?? 'Auditoría'"
        >
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/auditorias/${auditoria.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: auditoria.tipo,
                    etiqueta: auditoria.tipoEtiqueta,
                    tono: auditoria.tipoTono,
                    icono: auditoria.tipoIcono,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: auditoria.estado,
                    etiqueta: auditoria.estadoEtiqueta,
                    tono: auditoria.estadoTono,
                    icono: auditoria.estadoIcono,
                }"
            />
            <span class="text-sm text-muted-foreground">{{ auditoria.fecha }}</span>
        </div>

        <Aviso v-if="errores.informe" tono="error" titulo="No se ha podido preparar el informe">
            {{ errores.informe }}
        </Aviso>

        <Aviso v-if="errores.auditoria" tono="error" titulo="No se ha podido eliminar">
            {{ errores.auditoria }}
        </Aviso>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <!-- La checklist vive en su propia pantalla: aquí sólo su estado. -->
                <Card>
                    <CardHeader>
                        <CardTitle>Checklist</CardTitle>
                        <CardDescription>
                            Una línea por medida exigible. Lo que no se revisa se queda
                            «sin revisar», que no es lo mismo que «conforme».
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p v-if="avance.total > 0" class="text-sm">
                            <span class="cifra text-2xl font-semibold">{{ avance.revisados }}</span>
                            <span class="text-muted-foreground"> de {{ avance.total }} medidas revisadas</span>
                        </p>

                        <EstadoVacio
                            v-else
                            titulo="La checklist está vacía"
                            descripcion="Se genera desde el conjunto exigible del sistema: una línea por medida que el motor le exige."
                        />

                        <div class="flex flex-wrap gap-2">
                            <Button as-child>
                                <Link :href="`/auditorias/${auditoria.id}/checklist`">
                                    {{ avance.total > 0 ? 'Recorrer la checklist' : 'Ver la checklist' }}
                                </Link>
                            </Button>
                            <Button
                                v-if="puedeGestionar && auditoria.admiteCambios"
                                variant="outline"
                                :disabled="precargando"
                                @click="precargar"
                            >
                                {{ avance.total > 0 ? 'Actualizar desde el catálogo' : 'Generar desde el catálogo' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Hallazgos</CardTitle>
                        <CardDescription>
                            {{
                                sinTratar > 0
                                    ? `${sinTratar} de ${noConformidades} no conformidades siguen sin tratamiento.`
                                    : noConformidades > 0
                                      ? `${noConformidades} de ${hallazgos.length} son no conformidades y todas tienen tratamiento.`
                                      : 'Lo que el auditor encontró. Una no conformidad obliga a abrir su tratamiento.'
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="hallazgos.length === 0"
                            titulo="Sin hallazgos registrados"
                            descripcion="Un hallazgo puede colgar de una medida de la checklist o del sistema de gestión entero."
                        />

                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="item in hallazgos"
                                :key="item.id"
                                class="flex items-start justify-between gap-4 py-3"
                            >
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <CeldaBadge
                                            :valor="{
                                                valor: item.tipo,
                                                etiqueta: item.tipoEtiqueta,
                                                tono: item.tipoTono,
                                                icono: item.tipoIcono,
                                            }"
                                        />
                                        <span v-if="item.medida" class="cifra text-xs text-muted-foreground">
                                            {{ item.medida }}
                                        </span>
                                    </div>
                                    <p class="text-sm">{{ item.descripcion }}</p>

                                    <!--
                                        El tratamiento, si lo tiene. Y si no lo
                                        tiene y lo exige, el camino para abrirlo
                                        con la descripción ya puesta: reescribirla
                                        a mano acaba con dos versiones del mismo
                                        hecho.
                                    -->
                                    <Link
                                        v-if="item.noConformidadId"
                                        :href="`/no-conformidades/${item.noConformidadId}`"
                                        class="cifra text-xs underline-offset-4 hover:underline"
                                    >
                                        {{ item.noConformidadCodigo }}
                                    </Link>
                                    <Link
                                        v-else-if="item.exigeNoConformidad && puedeTratar"
                                        :href="`/no-conformidades/crear?hallazgo=${item.id}`"
                                        class="text-xs underline-offset-4 hover:underline"
                                    >
                                        Abrir no conformidad
                                    </Link>

                                    <!--
                                        El otro destino, desde la cláusula 10.1: una
                                        oportunidad de mejora no incumple nada, así que
                                        no se trata como no conformidad. Tiene su
                                        registro y su propio camino desde aquí.
                                    -->
                                    <Link
                                        v-if="item.mejoraId"
                                        :href="`/mejoras/${item.mejoraId}`"
                                        class="cifra text-xs underline-offset-4 hover:underline"
                                    >
                                        {{ item.mejoraCodigo }}
                                    </Link>
                                    <Link
                                        v-else-if="item.abreMejora && puedeTratarMejoras"
                                        :href="`/mejoras/crear?hallazgo=${item.id}`"
                                        class="text-xs underline-offset-4 hover:underline"
                                    >
                                        Abrir oportunidad de mejora
                                    </Link>
                                </div>
                                <Button
                                    v-if="puedeGestionar && auditoria.admiteCambios"
                                    variant="ghost"
                                    size="sm"
                                    @click="retirar(item.id)"
                                >
                                    Retirar
                                </Button>
                            </li>
                        </ul>

                        <Button
                            v-if="puedeGestionar && auditoria.admiteCambios"
                            variant="outline"
                            @click="abrirHallazgo"
                        >
                            Registrar hallazgo
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <div class="h-fit space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div v-if="auditoria.sistema">
                            <dt class="text-muted-foreground">Sistema</dt>
                            <dd class="cifra">{{ auditoria.sistema }}</dd>
                        </div>
                        <div v-if="auditoria.auditor">
                            <dt class="text-muted-foreground">Auditor</dt>
                            <dd>{{ auditoria.auditor }}</dd>
                        </div>
                        <div v-if="auditoria.entidad_certificadora">
                            <dt class="text-muted-foreground">Entidad certificadora</dt>
                            <dd>{{ auditoria.entidad_certificadora }}</dd>
                        </div>
                        <div v-if="auditoria.equipo">
                            <dt class="text-muted-foreground">Equipo auditor</dt>
                            <dd>{{ auditoria.equipo }}</dd>
                        </div>
                        <div v-if="auditoria.alcance">
                            <dt class="text-muted-foreground">Alcance</dt>
                            <dd>{{ auditoria.alcance }}</dd>
                        </div>
                        <div v-if="auditoria.criterios">
                            <dt class="text-muted-foreground">Criterios</dt>
                            <dd>{{ auditoria.criterios }}</dd>
                        </div>
                        <div v-if="auditoria.metodo">
                            <dt class="text-muted-foreground">Método</dt>
                            <dd>{{ auditoria.metodo }}</dd>
                        </div>
                        <div v-if="auditoria.fechaCierre">
                            <dt class="text-muted-foreground">Cerrada</dt>
                            <dd>{{ auditoria.fechaCierre }}<span v-if="auditoria.cerradaPor"> · {{ auditoria.cerradaPor }}</span></dd>
                        </div>
                        <div v-if="auditoria.conclusiones">
                            <dt class="text-muted-foreground">Conclusiones</dt>
                            <dd>{{ auditoria.conclusiones }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="admiteInforme">
                    <CardHeader>
                        <CardTitle>Informe</CardTitle>
                        <CardDescription>
                            El informe que pide la cláusula 9.2.2, en PDF y en Word. Recoge la
                            checklist y los hallazgos tal como quedaron al cerrar la auditoría.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <Button v-if="informe" as-child variant="outline">
                            <Link :href="`/documentos/${informe.id}`">Abrir {{ informe.codigo }}</Link>
                        </Button>
                        <template v-else-if="cerrada">
                            <Button
                                v-if="puedeGestionar && puedeGenerar"
                                variant="outline"
                                :disabled="preparandoInforme"
                                @click="prepararInforme"
                            >
                                Preparar el informe
                            </Button>
                            <p v-else class="text-muted-foreground">Todavía no se ha preparado.</p>
                        </template>
                        <p v-else class="text-muted-foreground">
                            Se prepara al cerrar la auditoría.
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="puedeGestionar && transiciones.length > 0">
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                        <CardDescription>
                            Cerrarla congela su checklist y sus hallazgos: a partir de ahí
                            ya no se pueden cambiar, y reabrirla queda registrado.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <BotonEstado
                                v-for="paso in transiciones"
                                :key="paso.valor"
                                :destino="paso"
                                :deshabilitado="enviando"
                                @click="mover(paso.valor)"
                            />
                        </div>

                        <div v-if="exigeConclusiones" class="space-y-2">
                            <CampoTextarea
                                nombre="conclusiones"
                                etiqueta="Conclusiones"
                                :filas="4"
                                :valor-inicial="conclusiones"
                                ayuda="Qué salió de la auditoría. Se congela con ella."
                                @input="conclusiones = ($event.target as HTMLTextAreaElement).value"
                            />
                            <Button :disabled="enviando" @click="mover('cerrada')">
                                Cerrar la auditoría
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar hallazgo</DialogTitle>
                    <DialogDescription>
                        Una no conformidad —mayor o menor— obliga a abrir su tratamiento.
                        Una observación o una mejora, no.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoSelect
                        nombre="tipo"
                        etiqueta="Tipo"
                        :opciones="opcionesTipo"
                        :error="hallazgo.errors.tipo"
                        requerido
                        @update:model-value="(valor?: string) => (hallazgo.tipo = valor ?? '')"
                    />
                    <CampoTextarea
                        nombre="descripcion"
                        etiqueta="Descripción"
                        :filas="4"
                        :error="hallazgo.errors.descripcion"
                        requerido
                        @input="hallazgo.descripcion = ($event.target as HTMLTextAreaElement).value"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="hallazgo.processing" @click="registrar">Registrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
