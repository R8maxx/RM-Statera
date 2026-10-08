<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
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
import { fechaLegible, formatoFechaHora } from '@/lib/celdas';
import { SIN_VALOR, conOpcionVacia } from '@/lib/formularios';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * La ficha comercial de una organización cliente (punto 41).
 *
 * **Lo que se ve es lo que la plataforma necesita para atender al cliente**:
 * quién es, quién entra y qué ha hecho la plataforma con él. Nada de lo que
 * guarda dentro —sistemas, riesgos, evidencias—, que no se lee sin que el
 * cliente abra la puerta.
 */
interface Cuenta {
    id: number;
    nombre: string;
    email: string;
    rol: string | null;
    plataforma: boolean;
    estado: { valor: string; etiqueta: string; tono: string; icono: string };
}

interface EventoTraza {
    id: number;
    accion: string;
    autor: string | null;
    fecha: string;
}

interface Suscripcion {
    planId: number | null;
    plan: string | null;
    limiteCuentas: number | null;
    limiteSistemas: number | null;
    cuentasOcupadas: number;
    iniciaEn: string | null;
    venceEn: string | null;
    graciaHasta: string | null;
    estado: { valor: string; etiqueta: string; tono: string; icono: string };
    historico: {
        id: number;
        de: string | null;
        a: string | null;
        venceEn: string | null;
        motivo: string | null;
        autor: string | null;
        fecha: string;
    }[];
}

const props = defineProps<{
    cliente: {
        id: number;
        nombre: string;
        razonSocial: string | null;
        cif: string | null;
        sector: string | null;
        altaEn: string | null;
        soporteHasta: string | null;
        bajaEn: string | null;
        motivoBaja: string | null;
    };
    cuentas: Cuenta[];
    invitadas: number;
    traza: EventoTraza[];
    contrato: Suscripcion;
    planes: { valor: string; etiqueta: string }[];
}>();

/* Entrar por la ventana que abrió el cliente (punto 44). */
const pagina = usePage();
const errorSoporte = computed(() => (pagina.props.errors as Record<string, string | undefined>).soporte);
const entrando = ref(false);

const errorCuentas = computed(() => (pagina.props.errors as Record<string, string | undefined>).cuentas);
const reenviando = ref<number | null>(null);

/* Si el enlace del primer responsable caduca, dentro no hay nadie que se lo reenvíe. */
function reenviar(cuenta: Cuenta): void {
    router.post(`/plataforma/organizaciones/${props.cliente.id}/cuentas/${cuenta.id}/reenviar`, {}, {
        preserveScroll: true,
        onStart: () => (reenviando.value = cuenta.id),
        onFinish: () => (reenviando.value = null),
    });
}

function entrarComoSoporte(): void {
    router.post(`/plataforma/organizaciones/${props.cliente.id}/soporte`, {}, {
        onStart: () => (entrando.value = true),
        onFinish: () => (entrando.value = false),
    });
}

/* La baja (punto 46). Con diálogo, porque deja a todo un cliente sin acceso. */
const dandoDeBaja = ref(false);
const baja = useForm({ motivo: '' });
const cambiandoBaja = ref(false);

function darDeBaja(): void {
    baja.post(`/plataforma/organizaciones/${props.cliente.id}/baja`, {
        preserveScroll: true,
        onSuccess: () => {
            dandoDeBaja.value = false;
            baja.reset();
        },
    });
}

function reactivar(): void {
    router.post(`/plataforma/organizaciones/${props.cliente.id}/reactivar`, {}, {
        preserveScroll: true,
        onStart: () => (cambiandoBaja.value = true),
        onFinish: () => (cambiandoBaja.value = false),
    });
}

const opcionesPlan = computed(() => conOpcionVacia(props.planes, 'Sin plan: sin límites y sin vencimiento'));

/*
 * El formulario de la suscripción. La fecha viaja como día y el servidor la
 * lleva al final de ese día: vence al acabar el día elegido, no al empezar.
 */
const formulario = useForm({
    plan_id: props.contrato.planId === null ? SIN_VALOR : String(props.contrato.planId),
    vence_en: props.contrato.venceEn?.slice(0, 10) ?? '',
    motivo: '',
});

function guardarSuscripcion(): void {
    formulario
        .transform((datos) => ({ ...datos, vence_en: datos.vence_en === '' ? null : datos.vence_en }))
        .put(`/plataforma/organizaciones/${props.cliente.id}/suscripcion`, {
            preserveScroll: true,
            onSuccess: () => formulario.reset('motivo'),
        });
}

const cuando = (fecha: string | null): string => (fecha ? formatoFechaHora.format(new Date(fecha)) : '—');
</script>

<template>
    <AppLayout :titulo="cliente.nombre">
        <CabeceraPagina :titulo="cliente.nombre" :descripcion="cliente.razonSocial ?? undefined">
            <template v-if="cliente.soporteHasta" #acciones>
                <Button :disabled="entrando" @click="entrarComoSoporte">Entrar como soporte</Button>
            </template>
        </CabeceraPagina>

        <!-- La puerta la abre el cliente: sin ventana, no hay botón, y se dice por qué. -->
        <p class="text-sm text-muted-foreground">
            <template v-if="cliente.soporteHasta">
                El cliente ha abierto el acceso de soporte hasta el
                <span class="cifra">{{ cuando(cliente.soporteHasta) }}</span>. Dentro sólo se lee, y su responsable
                de seguridad recibe un correo al entrar.
            </template>
            <template v-else>
                Sin acceso de soporte. Lo abre el responsable de seguridad del cliente desde la ficha de su
                organización.
            </template>
        </p>
        <p v-if="errorSoporte" class="text-sm text-destructive">{{ errorSoporte }}</p>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Cuentas</CardTitle>
                        <CardDescription>
                            Quién entra y con qué rol. Las da de alta el responsable de seguridad del cliente, no la
                            plataforma.
                            <template v-if="invitadas > 0">
                                {{ invitadas === 1 ? 'Una invitación sigue' : `${invitadas} invitaciones siguen` }} sin
                                aceptar.
                            </template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p v-if="errorCuentas" class="mb-2 text-sm text-destructive">{{ errorCuentas }}</p>
                        <ul class="divide-y text-sm">
                            <li
                                v-for="cuenta in cuentas"
                                :key="cuenta.id"
                                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2.5"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ cuenta.nombre }}</span>
                                    <span class="block truncate text-muted-foreground">{{ cuenta.email }}</span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <span v-if="cuenta.plataforma" class="text-xs text-muted-foreground">De la plataforma</span>
                                    <span v-if="cuenta.rol" class="text-muted-foreground">{{ cuenta.rol }}</span>
                                    <CeldaBadge :valor="{ ...cuenta.estado }" />
                                    <Button
                                        v-if="cuenta.estado.valor === 'invitada'"
                                        variant="outline"
                                        size="sm"
                                        :disabled="reenviando === cuenta.id"
                                        @click="reenviar(cuenta)"
                                    >
                                        Reenviar la invitación
                                    </Button>
                                </span>
                            </li>
                            <li v-if="cuentas.length === 0" class="py-2.5 text-muted-foreground">Sin cuentas.</li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lo que ha hecho la plataforma</CardTitle>
                        <CardDescription>De la traza de la plataforma. Los veinte eventos más recientes.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y text-sm">
                            <li v-for="evento in traza" :key="evento.id" class="flex flex-wrap justify-between gap-x-4 py-2.5">
                                <span>
                                    {{ evento.accion }}
                                    <span v-if="evento.autor" class="text-muted-foreground">· {{ evento.autor }}</span>
                                </span>
                                <span class="cifra text-muted-foreground">{{ cuando(evento.fecha) }}</span>
                            </li>
                            <li v-if="traza.length === 0" class="py-2.5 text-muted-foreground">Sin eventos.</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Suscripción</CardTitle>
                        <CardDescription>
                            <template v-if="contrato.plan">
                                {{ contrato.plan }}.
                                <template v-if="contrato.limiteCuentas">
                                    {{ contrato.cuentasOcupadas }} de {{ contrato.limiteCuentas }} cuentas ocupadas.
                                </template>
                                <!-- Bajar de plan no desactiva a nadie: se dice, y no
                                     se pueden añadir más hasta volver al límite. -->
                                <span
                                    v-if="contrato.limiteCuentas && contrato.cuentasOcupadas > contrato.limiteCuentas"
                                    class="block text-destructive"
                                >
                                    Tiene {{ contrato.cuentasOcupadas - contrato.limiteCuentas }} más de las que admite el
                                    plan. Nadie se ha desactivado, pero no podrá añadir otra hasta bajar del límite.
                                </span>
                                <template v-if="contrato.venceEn">
                                    Vence el {{ fechaLegible(contrato.venceEn) }}<template v-if="contrato.graciaHasta">
                                        y escribe hasta el {{ fechaLegible(contrato.graciaHasta) }}</template>.
                                </template>
                                <template v-else>No vence.</template>
                            </template>
                            <template v-else>Sin plan: sin límites y sin vencimiento.</template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <CeldaBadge :valor="{ ...contrato.estado }" />

                        <form class="grid gap-4" @submit.prevent="guardarSuscripcion">
                            <CampoSelect
                                v-model="formulario.plan_id"
                                nombre="plan_id"
                                etiqueta="Plan"
                                :opciones="opcionesPlan"
                                :error="formulario.errors.plan_id"
                            />
                            <CampoTexto
                                v-model="formulario.vence_en"
                                nombre="vence_en"
                                etiqueta="Vence el"
                                tipo="date"
                                :error="formulario.errors.vence_en"
                                ayuda="El último día incluido. En blanco, no vence."
                            />
                            <CampoTextarea
                                v-model="formulario.motivo"
                                nombre="motivo"
                                etiqueta="Motivo"
                                :filas="2"
                                :error="formulario.errors.motivo"
                                ayuda="Opcional. «Renovación anual», «ampliación a 20 cuentas»."
                            />
                            <Button type="submit" size="sm" class="justify-self-start" :disabled="formulario.processing">
                                Guardar la suscripción
                            </Button>
                        </form>

                        <ul v-if="contrato.historico.length > 0" class="divide-y border-t pt-2 text-sm">
                            <li v-for="cambio in contrato.historico" :key="cambio.id" class="grid gap-0.5 py-2">
                                <span>
                                    {{ cambio.de ?? 'Sin plan' }} → {{ cambio.a ?? 'Sin plan' }}
                                    <span v-if="cambio.venceEn" class="text-muted-foreground">
                                        · vence el {{ fechaLegible(cambio.venceEn) }}
                                    </span>
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    <span class="cifra">{{ cuando(cambio.fecha) }}</span>
                                    <template v-if="cambio.autor"> · {{ cambio.autor }}</template>
                                    <template v-if="cambio.motivo"> · {{ cambio.motivo }}</template>
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- La baja (punto 46): un estado que se deshace, nunca un borrado. -->
                <Card>
                    <CardHeader>
                        <CardTitle>Baja</CardTitle>
                        <CardDescription v-if="cliente.bajaEn">
                            De baja desde el {{ cuando(cliente.bajaEn) }}. Nadie suyo entra y todo lo que tiene se
                            conserva.
                            <template v-if="cliente.motivoBaja"> Motivo: {{ cliente.motivoBaja }}</template>
                        </CardDescription>
                        <CardDescription v-else>
                            Dar de baja deja a la organización sin acceso, sin soporte y fuera de los avisos diarios.
                            No borra nada, y se puede deshacer.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button v-if="cliente.bajaEn" variant="outline" size="sm" :disabled="cambiandoBaja" @click="reactivar">
                            Reactivar la organización
                        </Button>
                        <Button v-else variant="outline" size="sm" @click="dandoDeBaja = true">Dar de baja</Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid gap-2">
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">CIF</dt>
                                <dd>{{ cliente.cif ?? '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Sector</dt>
                                <dd>{{ cliente.sector ?? '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Alta</dt>
                                <dd>{{ cuando(cliente.altaEn) }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
        <Dialog v-model:open="dandoDeBaja">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Dar de baja {{ cliente.nombre }}</DialogTitle>
                    <DialogDescription>
                        Nadie de la organización podrá entrar, se cierra el acceso de soporte y deja de recibir avisos.
                        No se borra ningún dato, y se puede reactivar cuando haga falta.
                    </DialogDescription>
                </DialogHeader>

                <CampoTextarea
                    v-model="baja.motivo"
                    nombre="motivo"
                    etiqueta="Motivo"
                    :filas="2"
                    :error="baja.errors.motivo"
                    requerido
                    ayuda="«Fin del contrato», «impago tras la sólo lectura». Es lo primero que se pregunta si vuelve."
                />

                <DialogFooter>
                    <Button variant="outline" @click="dandoDeBaja = false">Cancelar</Button>
                    <Button :disabled="baja.processing" @click="darDeBaja">Dar de baja</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
