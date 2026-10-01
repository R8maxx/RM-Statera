<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
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
import { fechaLegible } from '@/lib/celdas';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Prevista {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    canal: string;
    canalEtiqueta: string;
    responsable: string | null;
    cadencia: string;
    computa_desde: string | null;
    destinatarios_otros: string | null;
    partes: string[];
    retirada: boolean;
    retiradaEn: string | null;
    motivoRetirada: string | null;
    proxima: { etiqueta: string; tono: string };
}

interface Hecha {
    id: number;
    fecha: string;
    cubreHasta: string | null;
    asunto: string;
    resumen: string | null;
    canal: string;
    evidencia: string | null;
    evidenciaId: number | null;
    registradaPor: string | null;
    registradaEn: string | null;
}

/**
 * La ficha de una línea del plan de comunicación: la cláusula 7.4.
 *
 * Abre con las cinco respuestas de la norma y sigue con **lo que de verdad se
 * comunicó**, cada vez con hasta cuándo cubre: es lo que contesta «¿se hizo lo
 * que se dijo?», que es la pregunta del auditor.
 *
 * El primario vive en la tarjeta «Estado», como en toda ficha (DESIGN.md § 9):
 * registrar que se comunicó es lo que mueve la próxima fecha.
 */
const props = defineProps<{
    prevista: Prevista;
    comunicaciones: Hecha[];
    evidencias: Opcion[];
    canales: Opcion[];
    puedeGestionar: boolean;
}>();

/* --- Registrar lo comunicado --- */

const registrando = ref(false);

const comunicada = useForm({
    sentido: 'emitida',
    fecha: '',
    asunto: '',
    resumen: '',
    canal: '',
    evidencia_id: SIN_VALOR,
});

function abrirRegistro(): void {
    comunicada.reset();
    comunicada.clearErrors();
    comunicada.fecha = new Date().toISOString().slice(0, 10);
    comunicada.asunto = props.prevista.titulo;
    comunicada.canal = props.prevista.canal;
    registrando.value = true;
}

function registrar(): void {
    comunicada.post(`/plan-comunicacion/${props.prevista.id}/comunicaciones`, {
        preserveScroll: true,
        onSuccess: () => {
            registrando.value = false;
        },
    });
}

/* --- Retirar --- */

const retirando = ref(false);
const retirada = useForm({ motivo_retirada: '' });

function retirar(): void {
    retirada.post(`/plan-comunicacion/${props.prevista.id}/retirar`, {
        preserveScroll: true,
        onSuccess: () => {
            retirando.value = false;
            retirada.reset();
        },
    });
}

function reactivar(): void {
    router.post(`/plan-comunicacion/${props.prevista.id}/reactivar`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :titulo="prevista.codigo">
        <CabeceraPagina :titulo="prevista.codigo" :descripcion="prevista.titulo">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/plan-comunicacion/${prevista.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                anunciar
                :valor="{ valor: 'proxima', etiqueta: prevista.proxima.etiqueta, tono: prevista.proxima.tono, icono: null }"
            />
            <span class="text-sm text-muted-foreground">{{ prevista.cadencia }}</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Qué, a quién, quién, cómo y cuándo</CardTitle>
                        <CardDescription>Las cinco preguntas de la cláusula 7.4.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid gap-4 text-sm sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <dt class="text-muted-foreground">Qué se comunica</dt>
                                <dd>{{ prevista.titulo }}</dd>
                                <dd v-if="prevista.descripcion" class="mt-1 whitespace-pre-line text-muted-foreground">
                                    {{ prevista.descripcion }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-muted-foreground">A quién</dt>
                                <dd v-if="prevista.partes.length > 0 || prevista.destinatarios_otros">
                                    <template v-if="prevista.partes.length > 0">{{ prevista.partes.join(', ') }}</template>
                                    <span v-if="prevista.destinatarios_otros" class="block text-muted-foreground">
                                        {{ prevista.destinatarios_otros }}
                                    </span>
                                </dd>
                                <dd v-else class="text-muted-foreground italic">Sin destinatarios.</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Quién</dt>
                                <dd :class="{ 'text-muted-foreground italic': !prevista.responsable }">
                                    {{ prevista.responsable ?? 'Sin responsable.' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Cómo</dt>
                                <dd>{{ prevista.canalEtiqueta }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Cuándo</dt>
                                <dd>{{ prevista.cadencia }}</dd>
                            </div>
                            <div v-if="prevista.computa_desde">
                                <dt class="text-muted-foreground">Cuenta desde</dt>
                                <dd>{{ fechaLegible(prevista.computa_desde) }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lo que se ha comunicado</CardTitle>
                        <CardDescription>
                            Cada vez, con hasta cuándo cubre. Si se apuntó mal, se borra desde
                            <Link href="/comunicaciones" class="underline-offset-4 hover:underline">Comunicaciones</Link>
                            y se vuelve a registrar.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="comunicaciones.length === 0"
                            titulo="Todavía no se ha comunicado"
                            descripcion="Está en el plan y no consta ninguna vez. Es una previsión, no un control."
                        />
                        <ul v-else class="divide-y border-t">
                            <li v-for="item in comunicaciones" :key="item.id" class="space-y-1 py-3 text-[13px]">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <span class="font-medium">{{ item.asunto }}</span>
                                    <span class="cifra text-muted-foreground">
                                        {{ fechaLegible(item.fecha) }}
                                        <template v-if="item.cubreHasta">· cubre hasta {{ fechaLegible(item.cubreHasta) }}</template>
                                    </span>
                                </div>
                                <p v-if="item.resumen" class="whitespace-pre-line text-muted-foreground">{{ item.resumen }}</p>
                                <p class="text-muted-foreground">
                                    {{ item.canal }}
                                    <template v-if="item.registradaPor"> · apuntada por {{ item.registradaPor }}</template>
                                    ·
                                    <Link
                                        v-if="item.evidenciaId"
                                        :href="`/evidencias/${item.evidenciaId}`"
                                        class="underline-offset-4 hover:underline"
                                    >
                                        {{ item.evidencia }}
                                    </Link>
                                    <span v-else>Sin prueba</span>
                                </p>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <div class="h-fit space-y-6">
                <Card v-if="puedeGestionar">
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <template v-if="!prevista.retirada">
                            <Button class="w-full" @click="abrirRegistro">Registrar que se comunicó</Button>
                            <Button variant="ghost" size="sm" class="text-muted-foreground" @click="retirando = true">
                                Retirar del plan
                            </Button>
                        </template>
                        <template v-else>
                            <p class="text-muted-foreground">
                                Retirada el {{ prevista.retiradaEn }}.
                                <span class="mt-1 block border-l-2 pl-3 text-secondary-foreground">{{ prevista.motivoRetirada }}</span>
                            </p>
                            <Button variant="outline" class="w-full" @click="reactivar">Volver a ponerla en el plan</Button>
                        </template>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="registrando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar que se comunicó</DialogTitle>
                    <DialogDescription>
                        Es un hecho, no una previsión: la fecha no puede ser futura. A partir de ella se cuenta la
                        próxima vez.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        nombre="fecha"
                        etiqueta="Fecha"
                        tipo="date"
                        :valor-inicial="comunicada.fecha"
                        :error="comunicada.errors.fecha"
                        requerido
                        @input="comunicada.fecha = ($event.target as HTMLInputElement).value"
                    />
                    <CampoTexto
                        nombre="asunto"
                        etiqueta="Asunto"
                        :valor-inicial="comunicada.asunto"
                        :error="comunicada.errors.asunto"
                        requerido
                        @input="comunicada.asunto = ($event.target as HTMLInputElement).value"
                    />
                    <CampoTextarea
                        nombre="resumen"
                        etiqueta="Resumen"
                        :filas="3"
                        :error="comunicada.errors.resumen"
                        @input="comunicada.resumen = ($event.target as HTMLTextAreaElement).value"
                    />
                    <CampoSelect
                        nombre="canal"
                        etiqueta="Cómo"
                        :opciones="canales"
                        :valor-inicial="comunicada.canal"
                        :error="comunicada.errors.canal"
                        @update:model-value="(valor?: string) => (comunicada.canal = valor ?? prevista.canal)"
                    />
                    <CampoSelect
                        nombre="evidencia_id"
                        etiqueta="Prueba"
                        :opciones="conOpcionVacia(evidencias, 'Sin prueba')"
                        :valor-inicial="comunicada.evidencia_id"
                        :error="comunicada.errors.evidencia_id"
                        ayuda="Una evidencia ya subida: el correo enviado, el acta de la reunión."
                        @update:model-value="(valor?: string) => (comunicada.evidencia_id = valor ?? SIN_VALOR)"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="registrando = false">Cancelar</Button>
                    <Button :disabled="comunicada.processing" @click="registrar">Registrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="retirando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retirar del plan</DialogTitle>
                    <DialogDescription>
                        No se borra: lo que se comunicó sigue registrado, y deja de avisar cuando toca.
                    </DialogDescription>
                </DialogHeader>

                <CampoTextarea
                    nombre="motivo_retirada"
                    etiqueta="Por qué se retira"
                    :filas="3"
                    :error="retirada.errors.motivo_retirada"
                    requerido
                    @input="retirada.motivo_retirada = ($event.target as HTMLTextAreaElement).value"
                />

                <DialogFooter>
                    <Button variant="outline" @click="retirando = false">Cancelar</Button>
                    <Button :disabled="retirada.processing || retirada.motivo_retirada.trim() === ''" @click="retirar">
                        Retirar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
