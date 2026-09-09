<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoSwitch from '@/components/formulario/CampoSwitch.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import MensajeError from '@/components/formulario/MensajeError.vue';
import BloqueEvidencias, { type EvidenciaVinculada } from '@/components/implantacion/BloqueEvidencias.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/implantacion/HistoricoTransiciones.vue';
import MapeoCruzado from '@/components/implantacion/MapeoCruzado.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { Link, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import { computed } from 'vue';

type Correspondencia = App.Http.Resources.Implantacion.Correspondencia;

const props = defineProps<{
    implantacion: {
        id: number;
        aplica: boolean;
        justificacion: string | null;
        estado: string;
        estadoEtiqueta: string;
        nivel_madurez: string | null;
        responsable_id: number | null;
        fecha_objetivo: string | null;
        notas: string | null;
    };
    requisito: {
        codigo: string;
        titulo: string;
        descripcion: string | null;
        marco: string | null;
        atributos: Record<string, unknown> | null;
        ruta: { codigo: string; titulo: string }[];
    };
    sistema: { id: number; codigo: string; nombre: string; categoria: string | null };
    exigencia: {
        valor: string | null;
        etiqueta: string | null;
        origen: string | null;
        dimension: string | null;
        excluibleAMano: boolean;
    };
    transicionesPermitidas: Opcion[];
    historico: Transicion[];
    evidencias: EvidenciaVinculada[];
    /** Prop opcional: sólo llega cuando el diálogo de adjuntar la pide. */
    evidenciasDisponibles?: Opcion[];
    correspondencias: Correspondencia[];
    responsables: Opcion[];
    niveles: Opcion[];
}>();

const { variantesEntrada } = useMovimientoReducido();

const responsables = computed<Opcion[]>(() => conOpcionVacia(props.responsables, 'Sin responsable'));
const niveles = computed<Opcion[]>(() => conOpcionVacia(props.niveles, 'Sin evaluar'));

const gestion = useForm({
    aplica: props.implantacion.aplica,
    justificacion: props.implantacion.justificacion ?? '',
    responsable_id: props.implantacion.responsable_id
        ? String(props.implantacion.responsable_id)
        : SIN_VALOR,
    fecha_objetivo: props.implantacion.fecha_objetivo ?? '',
    nivel_madurez: props.implantacion.nivel_madurez ?? SIN_VALOR,
    notas: props.implantacion.notas ?? '',
});

const transicion = useForm({ estado: undefined as string | undefined, nota: '' });

function guardar(): void {
    // Los centinelas de «ninguno» los traduce el `FormRequest`
    // (`NormalizaSeleccionVacia`), que es el único sitio donde ese valor se
    // interpreta. Aquí sólo quedan las cadenas vacías de los campos de texto.
    gestion
        .transform((datos) => ({
            ...datos,
            fecha_objetivo: datos.fecha_objetivo === '' ? null : datos.fecha_objetivo,
            justificacion: datos.justificacion === '' ? null : datos.justificacion,
        }))
        .put(`/implantaciones/${props.implantacion.id}`, { preserveScroll: true });
}

function cambiarEstado(): void {
    transicion.post(`/implantaciones/${props.implantacion.id}/estado`, {
        preserveScroll: true,
        onSuccess: () => transicion.reset(),
    });
}
</script>

<template>
    <AppLayout :titulo="requisito.codigo">
        <CabeceraPagina :titulo="requisito.codigo" :descripcion="requisito.titulo">
            <template #acciones>
                <Link href="/implantaciones">
                    <Button variant="ghost">Volver a la tabla</Button>
                </Link>
            </template>
        </CabeceraPagina>

        <motion.div
            :variants="variantesEntrada"
            initial="oculto"
            animate="visible"
            class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
        >
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                        <CardDescription>
                            Cada cambio queda registrado con su fecha y su autor. El estado «no aplica» no se
                            elige aquí: lo deriva el motor, o lo pone una exclusión motivada.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-muted-foreground">Ahora:</span>
                            <CeldaBadge
                                :valor="{
                                    valor: implantacion.estado,
                                    etiqueta: implantacion.estadoEtiqueta,
                                    tono: implantacion.estado,
                                }"
                            />
                        </div>

                        <template v-if="transicionesPermitidas.length > 0">
                            <CampoSelect
                                v-model="transicion.estado"
                                nombre="estado"
                                etiqueta="Pasar a"
                                :opciones="transicionesPermitidas"
                                :error="transicion.errors.estado"
                                placeholder="Elige el estado nuevo"
                            />

                            <CampoTextarea
                                v-model="transicion.nota"
                                nombre="nota"
                                etiqueta="Nota"
                                :filas="2"
                                :error="transicion.errors.nota"
                                ayuda="Qué ha cambiado. Se guarda en el histórico."
                            />
                        </template>

                        <p v-else class="text-sm text-muted-foreground">
                            Esta medida no se le exige al sistema. Vuelve a exigirse recalculando tras cambiar la
                            valoración de sus dimensiones, o volviendo a incluirla aquí abajo.
                        </p>
                    </CardContent>

                    <CardFooter v-if="transicionesPermitidas.length > 0" class="justify-end">
                        <Button
                            :disabled="transicion.processing || !transicion.estado"
                            @click="cambiarEstado"
                        >
                            {{ transicion.processing ? 'Guardando…' : 'Cambiar estado' }}
                        </Button>
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Gestión</CardTitle>
                        <CardDescription>Quién responde, para cuándo y con qué madurez.</CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-5">
                        <div v-if="exigencia.excluibleAMano" class="space-y-3">
                            <CampoSwitch
                                v-model="gestion.aplica"
                                nombre="aplica"
                                etiqueta="Este requisito aplica al sistema"
                                :error="gestion.errors.aplica"
                                ayuda="La Declaración de Aplicabilidad es precisamente la lista de exclusiones con su motivo."
                            />

                            <CampoTextarea
                                v-if="!gestion.aplica"
                                v-model="gestion.justificacion"
                                nombre="justificacion"
                                etiqueta="Justificación de la exclusión"
                                :filas="3"
                                :error="gestion.errors.justificacion"
                                requerido
                                ayuda="Es lo primero que un auditor pide cuando ve una exclusión."
                            />
                        </div>

                        <div v-else class="rounded-xl border bg-superficie px-4 py-3">
                            <p class="text-sm font-medium">La exigencia de esta medida se deriva</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ exigencia.origen }}. Deja de exigirse cambiando la valoración de las dimensiones
                                del sistema, no marcándola aquí: el siguiente recálculo la devolvería.
                            </p>
                            <Link
                                :href="`/sistemas/${sistema.id}/valoracion`"
                                class="mt-2 inline-block text-sm underline underline-offset-4"
                            >
                                Ir a la valoración de {{ sistema.codigo }}
                            </Link>

                            <MensajeError class="mt-2" :mensaje="gestion.errors.aplica" />
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <CampoSelect
                                v-model="gestion.responsable_id"
                                nombre="responsable_id"
                                etiqueta="Responsable"
                                :opciones="responsables"
                                :error="gestion.errors.responsable_id"
                            />

                            <CampoTexto
                                v-model="gestion.fecha_objetivo"
                                nombre="fecha_objetivo"
                                etiqueta="Fecha objetivo"
                                tipo="date"
                                :error="gestion.errors.fecha_objetivo"
                            />
                        </div>

                        <CampoSelect
                            v-model="gestion.nivel_madurez"
                            nombre="nivel_madurez"
                            etiqueta="Nivel de madurez"
                            :opciones="niveles"
                            :error="gestion.errors.nivel_madurez"
                            ayuda="Escala L0–L5 del CCN, la que pide el informe INES. No es lo mismo que el estado."
                        />

                        <CampoTextarea
                            v-model="gestion.notas"
                            nombre="notas"
                            etiqueta="Notas"
                            :filas="4"
                            :error="gestion.errors.notas"
                            ayuda="Cómo se cumple: configuración, procedimiento aplicable, salvedades."
                        />
                    </CardContent>

                    <CardFooter class="justify-end">
                        <Button :disabled="gestion.processing" @click="guardar">
                            {{ gestion.processing ? 'Guardando…' : 'Guardar' }}
                        </Button>
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Evidencias</CardTitle>
                        <CardDescription>
                            La tercera pregunta de una auditoría: dónde está la prueba. Una evidencia se registra
                            una vez y se adjunta a todos los requisitos que demuestra.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <BloqueEvidencias
                            :implantacion-id="implantacion.id"
                            :evidencias="evidencias"
                            :disponibles="evidenciasDisponibles"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Mapeo cruzado</CardTitle>
                        <CardDescription>
                            Lo que se haga aquí cuenta también en estos requisitos de otros marcos. Es la razón de
                            registrar una evidencia una sola vez.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <MapeoCruzado :correspondencias="correspondencias" />
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Por qué se exige</CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Sistema</span>
                            <Link :href="`/sistemas/${sistema.id}/valoracion`" class="cifra underline-offset-4 hover:underline">
                                {{ sistema.codigo }}
                            </Link>
                        </div>

                        <div v-if="sistema.categoria" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Categoría</span>
                            <span>{{ sistema.categoria }}</span>
                        </div>

                        <div v-if="requisito.marco" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Marco</span>
                            <span class="text-right">{{ requisito.marco }}</span>
                        </div>

                        <div v-if="exigencia.etiqueta" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Exigencia</span>
                            <span>{{ exigencia.etiqueta }}</span>
                        </div>

                        <div v-if="exigencia.origen" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Origen</span>
                            <span class="text-right">{{ exigencia.origen }}</span>
                        </div>

                        <div v-if="exigencia.dimension" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Dimensión</span>
                            <span>{{ exigencia.dimension }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="requisito.ruta.length > 1">
                    <CardHeader>
                        <CardTitle>Dónde encaja</CardTitle>
                        <CardDescription>La rama del catálogo hasta este requisito.</CardDescription>
                    </CardHeader>

                    <CardContent>
                        <ol class="space-y-1.5 text-sm">
                            <li
                                v-for="(paso, indice) in requisito.ruta"
                                :key="paso.codigo"
                                :style="{ paddingLeft: `${indice * 0.75}rem` }"
                                :class="indice === requisito.ruta.length - 1 ? 'font-medium' : 'text-muted-foreground'"
                            >
                                <span class="cifra">{{ paso.codigo }}</span>
                                <span class="ml-2">{{ paso.titulo }}</span>
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>
                            El auditor no pregunta si está implantado: pregunta desde cuándo.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <HistoricoTransiciones :transiciones="historico" />
                    </CardContent>
                </Card>
            </div>
        </motion.div>
    </AppLayout>
</template>
