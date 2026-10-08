<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFechaHora } from '@/lib/celdas';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Las solicitudes de rescate de cuenta (punto 52).
 *
 * Se ejecutan con una segunda mirada: quien la pidió no la ejecuta, salvo que
 * sea la única persona de Administración, y entonces se avisa al cliente. Lo
 * que se lee antes de ejecutar es la verificación: cómo se comprobó quién lo
 * pedía.
 */
interface Solicitud {
    id: number;
    tipo: string;
    organizacion: { id: number; nombre: string };
    cuenta: string;
    verificacion: string;
    solicitante: string | null;
    solicitadaEn: string;
    resolutor: string | null;
    resueltaEn: string | null;
    motivoRechazo: string | null;
    sinSegundaPersona: boolean;
    pedidaPorMi: boolean;
    puedoEjecutar: boolean;
    estado: { valor: string; etiqueta: string; tono: string; icono: string };
}

const props = defineProps<{ solicitudes: Solicitud[] }>();

const pagina = usePage();
const errorSolicitud = computed(() => (pagina.props.errors as Record<string, string | undefined>).solicitud);
const cuando = (fecha: string | null): string => (fecha ? formatoFechaHora.format(new Date(fecha)) : '—');

const pendientes = computed(() => props.solicitudes.filter((solicitud) => solicitud.estado.valor === 'pendiente'));
const resueltas = computed(() => props.solicitudes.filter((solicitud) => solicitud.estado.valor !== 'pendiente'));

const ejecutando = ref<number | null>(null);

function ejecutar(solicitud: Solicitud): void {
    router.post(`/plataforma/solicitudes/${solicitud.id}/ejecutar`, {}, {
        preserveScroll: true,
        onStart: () => (ejecutando.value = solicitud.id),
        onFinish: () => (ejecutando.value = null),
    });
}

const rechazando = ref<Solicitud | null>(null);
const rechazo = useForm({ motivo: '' });

function rechazar(): void {
    if (!rechazando.value) {
        return;
    }

    rechazo.post(`/plataforma/solicitudes/${rechazando.value.id}/rechazar`, {
        preserveScroll: true,
        onSuccess: () => {
            rechazando.value = null;
            rechazo.reset();
        },
    });
}
</script>

<template>
    <AppLayout titulo="Solicitudes">
        <CabeceraPagina
            titulo="Solicitudes de rescate"
            descripcion="Restablecer la verificación en dos pasos de una cuenta o designar un nuevo responsable. Las pide una persona y las ejecuta otra; caducan a las 72 horas."
        />

        <Aviso v-if="errorSolicitud" tono="error">{{ errorSolicitud }}</Aviso>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold">Pendientes</h2>
            <EstadoVacio v-if="pendientes.length === 0" titulo="Nada pendiente" descripcion="Se piden desde la ficha de cada cliente." />
            <Card v-for="solicitud in pendientes" :key="solicitud.id">
                <CardHeader>
                    <CardTitle>{{ solicitud.tipo }}</CardTitle>
                    <CardDescription>
                        <Link :href="`/plataforma/organizaciones/${solicitud.organizacion.id}`" class="underline underline-offset-4">
                            {{ solicitud.organizacion.nombre }}
                        </Link>
                        · {{ solicitud.cuenta }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div>
                        <p class="text-muted-foreground">Cómo se comprobó quién lo pide</p>
                        <p class="whitespace-pre-line">{{ solicitud.verificacion }}</p>
                    </div>
                    <p class="text-muted-foreground">
                        La pidió {{ solicitud.solicitante ?? 'alguien que ya no está' }} el
                        <span class="cifra">{{ cuando(solicitud.solicitadaEn) }}</span>.
                        <template v-if="solicitud.pedidaPorMi && !solicitud.puedoEjecutar">
                            La pediste tú: la ejecuta otra persona de Administración. Puedes retirarla rechazándola.
                        </template>
                        <template v-else-if="solicitud.pedidaPorMi">
                            La pediste tú y eres la única persona de Administración: puedes ejecutarla, y se avisará al cliente de que no hubo segunda mirada.
                        </template>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="solicitud.puedoEjecutar" size="sm" :disabled="ejecutando === solicitud.id" @click="ejecutar(solicitud)">
                            Ejecutar
                        </Button>
                        <Button variant="outline" size="sm" @click="rechazando = solicitud">Rechazar…</Button>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section v-if="resueltas.length > 0" class="space-y-3">
            <h2 class="text-sm font-semibold">Resueltas</h2>
            <Card class="py-0">
                <CardContent class="px-0">
                    <ol class="divide-y text-sm">
                        <li v-for="solicitud in resueltas" :key="solicitud.id" class="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
                            <span class="min-w-0">
                                <span class="block">{{ solicitud.tipo }} · {{ solicitud.organizacion.nombre }}</span>
                                <span class="block truncate text-muted-foreground">
                                    {{ solicitud.cuenta }}
                                    <template v-if="solicitud.resolutor"> · {{ solicitud.resolutor }}, {{ cuando(solicitud.resueltaEn) }}</template>
                                    <template v-if="solicitud.sinSegundaPersona"> · sin segunda persona</template>
                                    <template v-if="solicitud.motivoRechazo"> · {{ solicitud.motivoRechazo }}</template>
                                </span>
                            </span>
                            <CeldaBadge :valor="{ ...solicitud.estado }" />
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </section>

        <Dialog :open="rechazando !== null" @update:open="(abierto: boolean) => !abierto && (rechazando = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rechazar la solicitud</DialogTitle>
                    <DialogDescription>No se cambia nada en la cuenta. El motivo queda en la traza.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="rechazar">
                    <CampoTextarea
                        v-model="rechazo.motivo"
                        nombre="motivo"
                        etiqueta="Motivo"
                        :filas="2"
                        :error="rechazo.errors.motivo"
                        requerido
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="rechazando = null">Cancelar</Button>
                        <Button type="submit" :disabled="rechazo.processing">Rechazar</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
