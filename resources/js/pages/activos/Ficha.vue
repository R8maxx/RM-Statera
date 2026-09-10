<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ComparativaValoracion, {
    type ValoracionSerializada,
} from '@/components/activo/ComparativaValoracion.vue';
import EtiquetaQr from '@/components/activo/EtiquetaQr.vue';
import GrafoDependencias, { type ActivoDelGrafo } from '@/components/activo/GrafoDependencias.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
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
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import type { Opcion } from '@/lib/formularios';
import { Link, router, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import { computed, ref } from 'vue';

interface Motivo {
    activo: ActivoDelGrafo;
    dimensiones: { codigo: string; nombre: string }[];
}

const props = defineProps<{
    activo: {
        id: number;
        codigo: string;
        nombre: string;
        descripcion: string | null;
        tipo: string;
        tipoEtiqueta: string;
        tipoIcono: string;
        subtipo: string | null;
        marca_modelo: string | null;
        especificaciones: string | null;
        sistema_operativo: string | null;
        fin_soporte_so: string | null;
        identificador: string | null;
        propietario: string | null;
        custodio: string | null;
        departamento: string | null;
        ubicacion: string | null;
        fin_garantia: string | null;
        estado_ciclo_vida: string;
        estadoEtiqueta: string;
        estadoTono: string;
        clasificacion: string;
        clasificacionEtiqueta: string;
        clasificacionTono: string;
        cifrado: string;
        cifradoEtiqueta: string;
        cifradoTono: string;
        copia_seguridad: string;
        copiaEtiqueta: string;
        copiaTono: string;
        ultima_revision: string | null;
        sinRevisar: boolean;
        llevaEtiqueta: boolean;
        observaciones: string | null;
        esperaBorradoSeguro: boolean;
        fecha_alta: string | null;
        fecha_baja: string | null;
        borrado_seguro_en: string | null;
        nota_baja: string | null;
        sistemas: { id: number; codigo: string; nombre: string }[];
    };
    etiqueta: { svg: string; url: string } | null;
    avisoSoporte: string | null;
    valoracionPropia: ValoracionSerializada;
    valoracionEfectiva: ValoracionSerializada;
    motivos: Motivo[];
    dependeDe: ActivoDelGrafo[];
    dependientes: ActivoDelGrafo[];
    candidatos: Opcion[];
}>();

const { variantesEntrada } = useMovimientoReducido();

/**
 * Une una lista en prosa: «a, b y c».
 *
 * Con `join(', ')` la frase salía «Sube en disponibilidad, trazabilidad», que se
 * lee como una lista truncada. La copia de la aplicación se escribe entera
 * (DESIGN.md §13), y eso incluye la conjunción.
 */
function enumerar(elementos: string[]): string {
    if (elementos.length <= 1) {
        return elementos[0] ?? '';
    }

    return `${elementos.slice(0, -1).join(', ')} y ${elementos[elementos.length - 1]}`;
}

const fecha = (valor: string | null): string => (valor ? formatoFecha.format(new Date(valor)) : '—');

const abierto = ref(false);
const vincular = useForm({ depende_de_id: undefined as string | undefined, nota: '' });

/**
 * El texto que explica la diferencia entre lo valorado y lo efectivo.
 *
 * Se escribe con nombres de activos y no con un «heredado» a secas: quien mira
 * esta ficha necesita saber a quién preguntarle, no que la cifra la puso el
 * sistema.
 */
const explicacionHerencia = computed<string | null>(() => {
    if (props.motivos.length === 0) {
        return null;
    }

    const nombres = enumerar(props.motivos.map((motivo) => motivo.activo.codigo));
    const dimensiones = enumerar([
        ...new Set(props.motivos.flatMap((motivo) => motivo.dimensiones.map((una) => una.nombre.toLowerCase()))),
    ]);

    return `Sube en ${dimensiones} porque ${nombres} se apoya${props.motivos.length === 1 ? '' : 'n'} en este activo.`;
});

function confirmar(): void {
    vincular.post(`/activos/${props.activo.id}/dependencias`, {
        preserveScroll: true,
        onSuccess: () => {
            vincular.reset();
            abierto.value = false;
        },
    });
}

function retirar(dependenciaId: number): void {
    router.delete(`/activos/${props.activo.id}/dependencias/${dependenciaId}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :titulo="`${activo.codigo} · ${activo.nombre}`">
        <CabeceraPagina :titulo="activo.nombre" :descripcion="activo.descripcion">
            <template #acciones>
                <Link :href="`/activos/${activo.id}/editar`">
                    <Button variant="outline">Editar</Button>
                </Link>
            </template>
        </CabeceraPagina>

        <motion.div :variants="variantesEntrada" initial="oculto" animate="visible" class="space-y-6">
            <div class="flex flex-wrap items-center gap-2">
                <span class="cifra text-sm text-muted-foreground">{{ activo.codigo }}</span>
                <CeldaBadge
                    :valor="{
                        valor: activo.tipo,
                        etiqueta: activo.tipoEtiqueta,
                        tono: `tipo:${activo.tipo}`,
                        icono: activo.tipoIcono,
                    }"
                />
                <CeldaBadge
                    v-if="activo.subtipo"
                    :valor="{ valor: activo.subtipo, etiqueta: activo.subtipo, tono: 'marco' }"
                />
                <CeldaBadge
                    :valor="{
                        valor: activo.estado_ciclo_vida,
                        etiqueta: activo.estadoEtiqueta,
                        tono: activo.estadoTono,
                    }"
                />
                <CeldaBadge
                    :valor="{
                        valor: activo.clasificacion,
                        etiqueta: activo.clasificacionEtiqueta,
                        tono: activo.clasificacionTono,
                    }"
                />
                <CeldaBadge
                    v-for="sistema in activo.sistemas"
                    :key="sistema.id"
                    :valor="{ valor: sistema.codigo, etiqueta: sistema.codigo, tono: 'marco' }"
                />
            </div>

            <Aviso v-if="avisoSoporte" tono="error" titulo="Fuera de soporte">
                {{ avisoSoporte }} Un sistema que ya no recibe parches es op.exp.4 de la misma manera el día antes
                y el día después de que salga el primer CVE sin arreglo.
            </Aviso>

            <Aviso v-if="activo.esperaBorradoSeguro" tono="error" titulo="Sin constancia del borrado seguro">
                El activo ya no presta servicio, pero nadie ha registrado qué se hizo con lo que contenía. Hasta que
                esa constancia exista, mp.si.5 sigue sin cumplirse y esto es un hallazgo a la vista de cualquier
                auditor.
            </Aviso>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Valoración</CardTitle>
                            <CardDescription>
                                {{
                                    explicacionHerencia ??
                                    'Lo que la organización valoró y lo que el activo vale contando lo que se apoya en él. Aquí coinciden.'
                                }}
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <ComparativaValoracion
                                :propia="valoracionPropia"
                                :efectiva="valoracionEfectiva"
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle>Depende de</CardTitle>
                                <CardDescription>
                                    Lo que este activo necesita para funcionar. Su valoración sube hasta aquí.
                                </CardDescription>
                            </div>

                            <Button variant="outline" size="sm" @click="abierto = true">
                                Declarar dependencia
                            </Button>
                        </CardHeader>

                        <CardContent>
                            <GrafoDependencias
                                :activos="dependeDe"
                                :activo-id="activo.id"
                                retirable
                                vacio="No depende de nada declarado. Si en realidad se apoya en un servidor, una red o una base de datos, decláralo: sin el grafo, la valoración no se propaga y el análisis de impacto se queda sin respuesta."
                                @retirar="retirar"
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Lo sostiene</CardTitle>
                            <CardDescription>
                                Lo que se cae si este activo cae. Es de aquí de donde hereda su valoración efectiva.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <GrafoDependencias
                                :activos="dependientes"
                                :activo-id="activo.id"
                                vacio="Ningún activo declarado se apoya en éste."
                            />
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card v-if="etiqueta" class="h-fit">
                        <CardHeader>
                            <CardTitle>Etiqueta QR</CardTitle>
                            <CardDescription>
                                La que va pegada en la carcasa. El código no cambia aunque el activo cambie de manos.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <EtiquetaQr :activo-id="activo.id" :svg="etiqueta.svg" :url="etiqueta.url" />
                        </CardContent>
                    </Card>

                    <Card class="h-fit">
                        <CardHeader>
                            <CardTitle>Seguridad</CardTitle>
                            <CardDescription>
                                «Por confirmar» no es «no»: significa que nadie lo ha comprobado todavía.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <dl class="grid gap-3 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-muted-foreground">Cifrado en reposo</dt>
                                    <dd>
                                        <CeldaBadge
                                            :valor="{
                                                valor: activo.cifrado,
                                                etiqueta: activo.cifradoEtiqueta,
                                                tono: activo.cifradoTono,
                                            }"
                                        />
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-muted-foreground">Copia de seguridad</dt>
                                    <dd>
                                        <CeldaBadge
                                            :valor="{
                                                valor: activo.copia_seguridad,
                                                etiqueta: activo.copiaEtiqueta,
                                                tono: activo.copiaTono,
                                            }"
                                        />
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-muted-foreground">Última revisión</dt>
                                    <dd>
                                        <CeldaBadge
                                            :valor="{
                                                valor: activo.ultima_revision,
                                                etiqueta: activo.ultima_revision
                                                    ? fecha(activo.ultima_revision)
                                                    : 'Nunca',
                                                tono: activo.sinRevisar ? 'caducada' : 'implantado',
                                            }"
                                        />
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card class="h-fit">
                        <CardHeader>
                            <CardTitle>Ficha</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <dl class="grid gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">Nº de serie / identificador</dt>
                                <dd class="cifra break-all">{{ activo.identificador ?? '—' }}</dd>
                            </div>
                            <div v-if="activo.marca_modelo">
                                <dt class="text-xs text-muted-foreground">Marca y modelo</dt>
                                <dd>{{ activo.marca_modelo }}</dd>
                            </div>
                            <div v-if="activo.especificaciones">
                                <dt class="text-xs text-muted-foreground">Especificaciones</dt>
                                <dd>{{ activo.especificaciones }}</dd>
                            </div>
                            <div v-if="activo.sistema_operativo">
                                <dt class="text-xs text-muted-foreground">Sistema operativo</dt>
                                <dd>
                                    {{ activo.sistema_operativo }}
                                    <span v-if="activo.fin_soporte_so" class="text-muted-foreground">
                                        · soporte hasta {{ fecha(activo.fin_soporte_so) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Propietario</dt>
                                <dd>{{ activo.propietario ?? 'Sin asignar' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Custodio</dt>
                                <dd>{{ activo.custodio ?? 'Sin asignar' }}</dd>
                            </div>
                            <div v-if="activo.departamento">
                                <dt class="text-xs text-muted-foreground">Departamento</dt>
                                <dd>{{ activo.departamento }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Ubicación</dt>
                                <dd>{{ activo.ubicacion ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Alta</dt>
                                <dd>{{ fecha(activo.fecha_alta) }}</dd>
                            </div>
                            <div v-if="activo.fin_garantia">
                                <dt class="text-xs text-muted-foreground">Fin de garantía</dt>
                                <dd>{{ fecha(activo.fin_garantia) }}</dd>
                            </div>
                            <div v-if="activo.fecha_baja">
                                <dt class="text-xs text-muted-foreground">Baja</dt>
                                <dd>{{ fecha(activo.fecha_baja) }}</dd>
                            </div>
                            <div v-if="activo.borrado_seguro_en">
                                <dt class="text-xs text-muted-foreground">Borrado seguro</dt>
                                <dd>{{ fecha(activo.borrado_seguro_en) }}</dd>
                                <dd v-if="activo.nota_baja" class="mt-1 text-muted-foreground">
                                    {{ activo.nota_baja }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Alcance</dt>
                                <dd v-if="activo.sistemas.length > 0">
                                    <ul class="grid gap-0.5">
                                        <li v-for="sistema in activo.sistemas" :key="sistema.id">
                                            {{ sistema.codigo }} — {{ sistema.nombre }}
                                        </li>
                                    </ul>
                                </dd>
                                <dd v-else class="text-muted-foreground">
                                    No está declarado en el alcance de ningún sistema.
                                </dd>
                            </div>
                            <div v-if="activo.observaciones">
                                <dt class="text-xs text-muted-foreground">Observaciones</dt>
                                <dd>{{ activo.observaciones }}</dd>
                            </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </motion.div>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Declarar de qué depende {{ activo.codigo }}</DialogTitle>
                    <DialogDescription>
                        La dirección importa: aquí se elige lo que este activo NECESITA. Su valoración bajará hasta
                        ello, no al revés.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-5">
                    <CampoSelect
                        v-model="vincular.depende_de_id"
                        nombre="depende_de_id"
                        etiqueta="Depende de"
                        :opciones="candidatos"
                        :error="vincular.errors.depende_de_id"
                        placeholder="Elige un activo"
                        requerido
                    />

                    <CampoTextarea
                        v-model="vincular.nota"
                        nombre="nota"
                        etiqueta="Qué clase de dependencia"
                        :filas="2"
                        :error="vincular.errors.nota"
                        ayuda="«Se ejecuta sobre», «almacena en», «se comunica por». El BIA necesita saber cuál es, no sólo que la hay."
                    />
                </div>

                <DialogFooter>
                    <Button variant="ghost" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="vincular.processing || !vincular.depende_de_id" @click="confirmar">
                        {{ vincular.processing ? 'Guardando…' : 'Declarar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
