<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible, formatoFechaHora, formatoFechaLarga } from '@/lib/celdas';
import { SIN_VALOR, conOpcionVacia } from '@/lib/formularios';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { LogInIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * La ficha comercial de una organización cliente (punto 41).
 *
 * **Lo que se ve es lo que la plataforma necesita para atender al cliente**:
 * quién es, quién entra y qué ha hecho la plataforma con él. Nada de lo que
 * guarda dentro —sistemas, riesgos, evidencias—, que no se lee sin que el
 * cliente abra la puerta.
 *
 * Arriba, una franja con las tres preguntas que se hacen al abrirla —cómo va
 * la suscripción, cuántas cuentas ocupa y si se puede entrar como soporte—, y
 * una sola cifra grande: el tiempo que le queda antes del siguiente cambio de
 * estado. La columna lateral sigue el orden de las fichas (DESIGN.md § 9):
 * el cambio de estado, la ficha y lo demás; la baja, al final y aparte.
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
    /** Lo que añade el detalle: el hito de un aviso y a quién fue. */
    resumen: string | null;
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

interface PlanElegible {
    valor: string;
    etiqueta: string;
    limiteCuentas: number | null;
    limiteSistemas: number | null;
    diasGracia: number;
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
    planes: PlanElegible[];
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

/* El límite va en la etiqueta: elegir plan es elegir cuántas cuentas caben. */
const opcionesPlan = computed(() =>
    conOpcionVacia(
        props.planes.map((plan) => ({
            valor: plan.valor,
            etiqueta: [plan.etiqueta, limites(plan)].filter(Boolean).join(' · '),
        })),
        'Sin plan: sin límites y sin vencimiento',
    ),
);

function limites(plan: { limiteCuentas: number | null; limiteSistemas: number | null }): string {
    const partes = [
        plan.limiteCuentas === null ? null : `${plan.limiteCuentas} ${plan.limiteCuentas === 1 ? 'cuenta' : 'cuentas'}`,
        plan.limiteSistemas === null ? null : `${plan.limiteSistemas} ${plan.limiteSistemas === 1 ? 'sistema' : 'sistemas'}`,
    ].filter(Boolean);

    return partes.join(', ');
}

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

/** Días de calendario de hoy a esa fecha: el aviso de mañana dice «mañana» aunque falten cuarenta horas. */
function diasHasta(fecha: Date): number {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const dia = new Date(fecha);
    dia.setHours(0, 0, 0, 0);

    return Math.round((dia.getTime() - hoy.getTime()) / 86_400_000);
}

const largo = (fecha: string | Date): string => formatoFechaLarga.format(new Date(fecha));

/*
 * La cifra de la franja: lo que queda hasta el siguiente cambio de estado. En
 * días hasta un mes y en meses a partir de ahí, como `distanciaLegible`:
 * «361 días» obliga a hacer la cuenta.
 */
const plazo = computed<{ valor: number; unidad: string; hasta: string } | null>(() => {
    const { estado, venceEn, graciaHasta } = props.contrato;
    const objetivo = estado.valor === 'vigente' ? venceEn : estado.valor === 'en_gracia' ? graciaHasta : null;

    if (objetivo === null) {
        return null;
    }

    const dias = Math.max(0, diasHasta(new Date(objetivo)));
    const hasta = estado.valor === 'vigente' ? 'para vencer' : 'hasta sólo lectura';

    if (dias < 31) {
        return { valor: dias, unidad: dias === 1 ? 'día' : 'días', hasta };
    }

    const meses = Math.round(dias / 30.44);

    return { valor: meses, unidad: meses === 1 ? 'mes' : 'meses', hasta };
});

const detalleSuscripcion = computed(() => {
    const { plan, venceEn, graciaHasta, estado } = props.contrato;

    if (plan === null) {
        return 'Sin plan: sin límites y sin vencimiento.';
    }

    if (venceEn === null) {
        return `${plan} · no vence`;
    }

    if (estado.valor === 'vigente') {
        return `${plan} · vence el ${largo(venceEn)}`;
    }

    return estado.valor === 'en_gracia'
        ? `${plan} · venció el ${fechaLegible(venceEn)}; escribe hasta el ${largo(graciaHasta ?? venceEn)}`
        : `${plan} · en sólo lectura desde el ${largo(graciaHasta ?? venceEn)}`;
});

/* Bajar de plan no desactiva a nadie: se dice, y no se pueden añadir más hasta volver al límite. */
const exceso = computed(() =>
    props.contrato.limiteCuentas === null ? 0 : Math.max(0, props.contrato.cuentasOcupadas - props.contrato.limiteCuentas),
);

const ocupacion = computed(() => {
    const limite = props.contrato.limiteCuentas;

    return limite === null || limite === 0 ? null : Math.min(100, (props.contrato.cuentasOcupadas / limite) * 100);
});

/* La ventana la abre el cliente; aquí sólo se dice si está abierta. */
const estadoSoporte = computed(() =>
    props.cliente.soporteHasta
        ? { valor: 'abierto', etiqueta: 'Abierto por el cliente', tono: 'planificado', icono: 'LogIn' }
        : { valor: 'cerrado', etiqueta: 'Cerrado', tono: 'no_aplica', icono: 'Lock' },
);

/*
 * Lo que saldrá de guardar, antes de guardarlo. Es una previsión con las
 * mismas reglas que `EstadoSuscripcion::finDeGracia()`; la que manda sigue
 * siendo la del servidor, que es la que pinta la franja al volver.
 */
const prevision = computed<string[]>(() => {
    const plan = props.planes.find((candidato) => candidato.valor === formulario.plan_id);

    if (plan === undefined) {
        return ['Sin límites y sin vencimiento.'];
    }

    const lineas: string[] = [];

    if (formulario.vence_en === '') {
        lineas.push('No vence.');
    } else {
        const vence = new Date(`${formulario.vence_en}T00:00:00`);
        const finGracia = new Date(vence);
        finGracia.setDate(finGracia.getDate() + plan.diasGracia);

        if (diasHasta(vence) >= 0) {
            lineas.push(
                plan.diasGracia > 0
                    ? `Escribe hasta el ${largo(vence)}. Después, ${plan.diasGracia} días de gracia hasta el ${largo(finGracia)}, y luego sólo lectura.`
                    : `Escribe hasta el ${largo(vence)}, y luego sólo lectura.`,
            );
        } else if (diasHasta(finGracia) >= 0) {
            lineas.push(`Ya ha vencido: queda en gracia hasta el ${largo(finGracia)}.`);
        } else {
            lineas.push('Ya ha vencido y la gracia también: queda en sólo lectura.');
        }
    }

    if (plan.limiteCuentas !== null && props.contrato.cuentasOcupadas > plan.limiteCuentas) {
        const sobran = props.contrato.cuentasOcupadas - plan.limiteCuentas;
        lineas.push(`Tendrá ${sobran} ${sobran === 1 ? 'cuenta' : 'cuentas'} más de las que admite.`);
    }

    return lineas;
});
</script>

<template>
    <AppLayout :titulo="cliente.nombre">
        <CabeceraPagina
            :titulo="cliente.nombre"
            :codigo="cliente.cif"
            :descripcion="[cliente.razonSocial, cliente.sector].filter(Boolean).join(' · ') || undefined"
        >
            <template v-if="cliente.soporteHasta" #acciones>
                <Button variant="outline" :disabled="entrando" @click="entrarComoSoporte">
                    <LogInIcon aria-hidden="true" />
                    Entrar como soporte
                </Button>
            </template>
        </CabeceraPagina>

        <div class="space-y-4">
            <Aviso v-if="cliente.bajaEn" titulo="De baja">
                Desde el {{ cuando(cliente.bajaEn) }}. Nadie suyo entra y todo lo que tiene se conserva.
                <template v-if="cliente.motivoBaja"> Motivo: {{ cliente.motivoBaja }}</template>
            </Aviso>
            <Aviso v-if="exceso > 0" :titulo="exceso === 1 ? 'Tiene una cuenta más de las que admite el plan' : `Tiene ${exceso} cuentas más de las que admite el plan`">
                {{ contrato.cuentasOcupadas }} cuentas con el plan {{ contrato.plan }}, que admite {{ contrato.limiteCuentas }}.
                Nadie se ha desactivado, pero no podrá añadir otra hasta bajar del límite o cambiar de plan.
            </Aviso>
            <Aviso v-if="errorSoporte" tono="error">{{ errorSoporte }}</Aviso>

            <section
                aria-label="Estado del cliente"
                class="grid divide-y overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10 md:grid-cols-3 md:divide-x md:divide-y-0"
            >
                <div class="flex flex-col gap-2.5 px-6 py-5">
                    <span class="text-[13px] font-medium text-muted-foreground">Suscripción</span>
                    <CeldaBadge class="self-start" :valor="{ ...contrato.estado }" />
                    <p v-if="plazo" class="flex items-baseline gap-2">
                        <span class="cifra text-3xl font-medium tracking-tight">{{ plazo.valor }}</span>
                        <span class="text-sm text-secondary-foreground">{{ plazo.unidad }} {{ plazo.hasta }}</span>
                    </p>
                    <span class="text-[13px] text-muted-foreground">{{ detalleSuscripcion }}</span>
                </div>

                <div class="flex flex-col gap-2.5 px-6 py-5">
                    <span class="text-[13px] font-medium text-muted-foreground">Cuentas</span>
                    <p class="mt-1 flex items-baseline gap-1.5">
                        <span class="cifra text-xl font-medium">{{ contrato.cuentasOcupadas }}</span>
                        <span class="text-sm text-secondary-foreground">
                            <template v-if="contrato.limiteCuentas !== null">de {{ contrato.limiteCuentas }} que admite el plan</template>
                            <template v-else>{{ contrato.cuentasOcupadas === 1 ? 'ocupada' : 'ocupadas' }}, sin límite</template>
                        </span>
                    </p>
                    <div
                        v-if="ocupacion !== null"
                        class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                        role="img"
                        :aria-label="`${contrato.cuentasOcupadas} de ${contrato.limiteCuentas} cuentas ocupadas`"
                    >
                        <div class="h-full rounded-full bg-primary" :style="{ width: `${ocupacion}%` }" />
                    </div>
                    <span class="text-[13px] text-muted-foreground">
                        <template v-if="exceso > 0">
                            <span class="font-medium text-foreground">{{ exceso }} por encima del límite</span>
                        </template>
                        <template v-else-if="invitadas > 0">
                            {{ invitadas === 1 ? 'Una invitación sin aceptar' : `${invitadas} invitaciones sin aceptar` }}
                        </template>
                        <template v-else>Sin invitaciones pendientes</template>
                    </span>
                </div>

                <!-- La puerta la abre el cliente: sin ventana, no hay botón, y se dice por qué. -->
                <div class="flex flex-col gap-2.5 px-6 py-5">
                    <span class="text-[13px] font-medium text-muted-foreground">Acceso de soporte</span>
                    <CeldaBadge class="self-start" :valor="estadoSoporte" />
                    <template v-if="cliente.soporteHasta">
                        <span class="text-sm">Hasta el <span class="cifra">{{ cuando(cliente.soporteHasta) }}</span></span>
                        <span class="max-w-[34ch] text-[13px] text-muted-foreground">
                            Dentro sólo se lee, y su responsable de seguridad recibe un correo al entrar.
                        </span>
                    </template>
                    <span v-else class="max-w-[34ch] text-[13px] text-muted-foreground">
                        Lo abre su responsable de seguridad desde la ficha de su organización. Sin ventana, la plataforma
                        no entra.
                    </span>
                </div>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="min-w-0 space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Cuentas</CardTitle>
                        <CardDescription>Las da de alta su responsable de seguridad, no la plataforma.</CardDescription>
                    </CardHeader>
                    <CardContent class="px-0">
                        <p v-if="errorCuentas" class="mb-2 px-6 text-sm text-destructive">{{ errorCuentas }}</p>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="pl-6">Persona</TableHead>
                                    <TableHead>Rol</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead class="pr-6"><span class="sr-only">Acciones</span></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="cuenta in cuentas" :key="cuenta.id">
                                    <TableCell class="max-w-72 pl-6">
                                        <span class="block truncate font-medium">{{ cuenta.nombre }}</span>
                                        <span class="block truncate text-muted-foreground">{{ cuenta.email }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="flex flex-wrap items-center gap-1.5">
                                            {{ cuenta.rol ?? '—' }}
                                            <span v-if="cuenta.plataforma" class="rounded-full bg-muted px-2 py-px text-xs text-secondary-foreground">
                                                De la plataforma
                                            </span>
                                        </span>
                                    </TableCell>
                                    <TableCell><CeldaBadge :valor="{ ...cuenta.estado }" /></TableCell>
                                    <TableCell class="pr-6 text-right">
                                        <Button
                                            v-if="cuenta.estado.valor === 'invitada'"
                                            variant="outline"
                                            size="sm"
                                            :disabled="reenviando === cuenta.id"
                                            @click="reenviar(cuenta)"
                                        >
                                            Reenviar la invitación
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="cuentas.length === 0">
                                    <TableCell colspan="4" class="pl-6 text-muted-foreground">Sin cuentas.</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lo que ha hecho la plataforma</CardTitle>
                        <CardDescription>De la traza de la plataforma. Los veinte eventos más recientes.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol class="divide-y text-sm">
                            <li
                                v-for="evento in traza"
                                :key="evento.id"
                                class="grid gap-x-4 gap-y-0.5 py-2.5 sm:grid-cols-[9.5rem_minmax(0,1fr)]"
                            >
                                <span class="cifra text-[13px] text-muted-foreground">{{ cuando(evento.fecha) }}</span>
                                <span>
                                    <span class="font-medium">{{ evento.accion }}</span>
                                    <span v-if="evento.resumen" class="text-muted-foreground"> · {{ evento.resumen }}</span>
                                    <span v-if="evento.autor" class="text-muted-foreground"> · {{ evento.autor }}</span>
                                </span>
                            </li>
                            <li v-if="traza.length === 0" class="py-2.5 text-muted-foreground">Sin eventos.</li>
                        </ol>
                    </CardContent>
                </Card>
            </div>

            <div class="min-w-0 space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Cambiar la suscripción</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
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

                            <div class="space-y-1 border-l-2 border-primary py-0.5 pl-3 text-[13px]" aria-live="polite">
                                <p class="font-semibold">Lo que sale de aquí</p>
                                <p v-for="linea in prevision" :key="linea" class="text-secondary-foreground">{{ linea }}</p>
                            </div>

                            <Button type="submit" :disabled="formulario.processing">Guardar la suscripción</Button>
                        </form>

                        <details v-if="contrato.historico.length > 0" class="group border-t pt-3">
                            <summary class="cursor-pointer text-[13px] font-medium text-secondary-foreground">
                                Histórico de la suscripción
                                <span class="cifra text-muted-foreground">({{ contrato.historico.length }})</span>
                            </summary>
                            <ul class="mt-2 divide-y text-sm">
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
                        </details>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid grid-cols-[max-content_minmax(0,1fr)] gap-x-4 gap-y-2.5">
                            <dt class="text-muted-foreground">Razón social</dt>
                            <dd>{{ cliente.razonSocial ?? '—' }}</dd>
                            <dt class="text-muted-foreground">CIF</dt>
                            <dd class="cifra text-[13px]">{{ cliente.cif ?? '—' }}</dd>
                            <dt class="text-muted-foreground">Sector</dt>
                            <dd>{{ cliente.sector ?? '—' }}</dd>
                            <dt class="text-muted-foreground">Alta</dt>
                            <dd class="cifra text-[13px]">{{ cuando(cliente.altaEn) }}</dd>
                        </dl>
                    </CardContent>
                </Card>

                <!-- La baja (punto 46): un estado que se deshace, nunca un borrado. Aparte y al final. -->
                <section class="space-y-3 rounded-xl border border-dashed px-6 py-5">
                    <template v-if="cliente.bajaEn">
                        <h2 class="text-sm font-semibold">Reactivar</h2>
                        <p class="text-[13px] text-muted-foreground">
                            Vuelve a entrar quien tenía cuenta. La ventana de soporte no se abre: la abre el cliente si la
                            quiere.
                        </p>
                        <Button variant="outline" size="sm" :disabled="cambiandoBaja" @click="reactivar">
                            Reactivar la organización
                        </Button>
                    </template>
                    <template v-else>
                        <h2 class="text-sm font-semibold">Dar de baja</h2>
                        <p class="text-[13px] text-muted-foreground">
                            La organización se queda sin acceso, sin soporte y fuera de los avisos diarios. No se borra
                            nada y se puede reactivar.
                        </p>
                        <Button variant="destructive" size="sm" @click="dandoDeBaja = true">Dar de baja…</Button>
                    </template>
                </section>
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
