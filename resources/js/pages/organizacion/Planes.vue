<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import { Button } from '@/components/ui/button';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible } from '@/lib/celdas';
import { aEuros, euros } from '@/lib/dinero';
import { curva, duracion } from '@/lib/motion';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    ArrowRightIcon,
    CheckIcon,
    ChevronDownIcon,
    LockIcon,
    Repeat2Icon,
    ShieldCheckIcon,
    TriangleAlertIcon,
    UsersIcon,
} from '@lucide/vue';
import { AnimatePresence, motion } from 'motion-v';
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { computed, nextTick, ref } from 'vue';

/**
 * Elegir y cambiar de plan (punto 51).
 *
 * Una página de precios, como la de cualquier suscripción: el periodo arriba,
 * una tarjeta por plan, el resumen de lo que supone el cambio y el botón. **El
 * cambio es inmediato** y el importe se calcula como el día que haya pasarela,
 * pero hoy no se cobra; la pantalla lo dice en vez de fingir un pago.
 *
 * Los planes que asigna sólo la plataforma —«Ilimitado»— se enseñan en una
 * tarjeta sin botón: que se sepa que existen, y que no se contratan aquí.
 *
 * **Ninguna cuenta se hace aquí.** Cada plan llega con su presupuesto ya
 * calculado en los dos periodos por `PresupuestarCambioPlan`, la misma clase
 * que guarda el cambio: lo que se enseña es lo que queda en el histórico.
 *
 * Es el sexto momento de DESIGN.md §10: las tarjetas entran escalonadas, el
 * precio cuenta al cambiar de periodo, la tarjeta elegida se adelanta y el
 * resumen entra debajo. Se visita casi nunca.
 */
type Periodo = App.Domain.Plataforma.Enums.PeriodoFacturacion;

interface Presupuesto {
    bloqueo: 'es_el_actual' | 'no_cabe' | null;
    motivo: string | null;
    periodoNuevo: boolean;
    diasRestantes: number;
    ajusteCentimos: number;
    importeHoyCentimos: number;
    saldoAFavorCentimos: number;
    venceEn: string;
    siguienteCobroCentimos: number;
}

interface PlanContratable {
    id: number;
    nombre: string;
    descripcion: string | null;
    limiteCuentas: number | null;
    limiteSistemas: number | null;
    diasGracia: number;
    descuentoAnual: number;
    precioMensualCentimos: number;
    presupuestos: Record<Periodo, Presupuesto>;
}

interface PlanReservado {
    id: number;
    nombre: string;
    descripcion: string | null;
    limiteCuentas: number | null;
    limiteSistemas: number | null;
    esElActual: boolean;
}

const props = defineProps<{
    actual: {
        planId: number | null;
        plan: string | null;
        precioMensualCentimos: number | null;
        periodo: Periodo | null;
        venceEn: string | null;
        estado: App.Http.Resources.Definicion.ValorEtiquetado;
    };
    uso: { cuentas: number; sistemas: number };
    planes: PlanContratable[];
    reservados: PlanReservado[];
}>();

const { reducido, variantesEntrada, variantesSalida, variantesEscalonado } = useMovimientoReducido();
const escalonado = variantesEscalonado(0.06);

const periodo = ref<Periodo>(props.actual.periodo ?? 'anual');
const elegido = ref<number | null>(null);
const enviando = ref(false);
const error = ref<string | null>(null);
const resumen = ref<HTMLElement | null>(null);

const descuentoMaximo = computed(() => Math.max(0, ...props.planes.map((p) => p.descuentoAnual)));
const vencida = computed(() => props.actual.estado.valor !== 'vigente');

const tarjetas = computed(() =>
    props.planes.map((plan) => {
        const presupuesto = plan.presupuestos[periodo.value];
        const esActual = plan.id === props.actual.planId;
        const anual = periodo.value === 'anual';
        const precioActual = props.actual.precioMensualCentimos;
        const verbo =
            props.actual.planId === null || vencida.value
                ? esActual
                    ? 'Renovar'
                    : 'Elegir'
                : esActual
                  ? 'Pasar a'
                  : precioActual !== null && plan.precioMensualCentimos < precioActual
                    ? 'Bajar a'
                    : 'Subir a';

        return {
            plan,
            presupuesto,
            esActual,
            anual,
            /** Al mes, que es la cifra que se compara entre periodos. */
            alMes: anual ? Math.round(presupuesto.siguienteCobroCentimos / 12) : presupuesto.siguienteCobroCentimos,
            boton:
                presupuesto.bloqueo === 'es_el_actual'
                    ? 'Tu plan actual'
                    : presupuesto.bloqueo === 'no_cabe'
                      ? 'No cabe lo que usáis'
                      : esActual && !vencida.value
                        ? `Pasar a ${anual ? 'anual' : 'mensual'}`
                        : `${verbo} ${plan.nombre}`,
            sube: precioActual !== null && plan.precioMensualCentimos > precioActual,
        };
    }),
);

/** El siguiente escalón por encima del plan que se tiene, si cabe. */
const recomendado = computed(() => {
    if (props.actual.planId === null || vencida.value) {
        return null;
    }

    return tarjetas.value.find((t) => t.sube && t.presupuesto.bloqueo === null)?.plan.id ?? null;
});

const seleccion = computed(() => tarjetas.value.find((t) => t.plan.id === elegido.value) ?? null);

async function elegir(id: number): Promise<void> {
    elegido.value = id;
    error.value = null;
    await nextTick();
    resumen.value?.scrollIntoView({ behavior: reducido.value ? 'auto' : 'smooth', block: 'nearest' });
}

function confirmar(): void {
    if (seleccion.value === null) {
        return;
    }

    router.post(
        '/organizacion/plan',
        { plan_id: seleccion.value.plan.id, periodo: periodo.value },
        {
            preserveScroll: true,
            onStart: () => (enviando.value = true),
            onFinish: () => (enviando.value = false),
            onError: (errores) => (error.value = errores.plan_id ?? errores.periodo ?? 'No se ha podido cambiar el plan.'),
        },
    );
}

function limites(plan: { limiteCuentas: number | null; limiteSistemas: number | null }): string {
    const cuentas = plan.limiteCuentas === null ? 'cuentas sin límite' : `${plan.limiteCuentas} cuentas`;
    const sistemas =
        plan.limiteSistemas === null ? 'sistemas sin límite' : `${plan.limiteSistemas} ${plan.limiteSistemas === 1 ? 'sistema' : 'sistemas'}`;

    return `${cuentas} · ${sistemas}`;
}

const PREGUNTAS = [
    {
        pregunta: '¿Qué pago si subo de plan a mitad de periodo?',
        respuesta: 'Sólo la diferencia por los días que quedan hasta la renovación. La fecha de renovación no cambia.',
    },
    {
        pregunta: '¿Y si bajo de plan?',
        respuesta:
            'Lo que sobra del periodo queda como saldo a favor. Antes, lo que usáis tiene que caber: si tenéis más cuentas o sistemas que el plan nuevo, primero hay que darlos de baja.',
    },
    {
        pregunta: '¿Qué pasa si la suscripción vence?',
        respuesta:
            'Seguís trabajando los días de gracia del plan, con un aviso arriba. Después, sólo lectura: veis y descargáis todo, pero no guardáis. Desde esta página se renueva también en sólo lectura.',
    },
    {
        pregunta: '¿Quién puede cambiar el plan?',
        respuesta: 'Quien es responsable de seguridad de la organización. Un plan sin límites como Ilimitado no se contrata: lo asigna el equipo de Statera.',
    },
];

const abierta = ref<number | null>(null);
</script>

<template>
    <AppLayout titulo="Elige tu plan">
        <div class="mx-auto grid w-full max-w-6xl gap-10">
            <div>
                <!-- La banda de marca: título, lo que se usa hoy y el periodo. -->
                <section class="rounded-xl bg-marca-900 px-6 pt-6 pb-32 text-center text-marca-50 sm:px-10">
                    <div class="flex">
                        <Link
                            href="/organizacion"
                            class="inline-flex min-h-8 items-center gap-1.5 rounded text-sm font-medium text-marca-200 hover:text-marca-50"
                        >
                            <ArrowLeftIcon class="size-4" aria-hidden="true" />
                            Organización
                        </Link>
                    </div>
                    <motion.div :variants="escalonado" initial="oculto" animate="visible" class="mx-auto mt-4 grid max-w-2xl justify-items-center gap-4">
                        <motion.h1 :variants="variantesEntrada" class="text-3xl leading-[2.125rem] font-semibold tracking-[-0.02em] text-balance sm:text-4xl sm:leading-[2.75rem]">
                            Elige el plan que necesitáis
                        </motion.h1>
                        <motion.p :variants="variantesEntrada" class="text-base leading-relaxed text-pretty text-marca-100">
                            Todo Statera en cualquier plan: cambia cuántas cuentas y sistemas caben. El cambio es inmediato y se paga
                            sólo la parte que queda del periodo.
                        </motion.p>
                        <motion.p :variants="variantesEntrada" class="flex flex-wrap justify-center gap-x-5 gap-y-1 text-sm text-marca-200">
                            <span>
                                Hoy usáis <strong class="cifra text-marca-50">{{ uso.cuentas }}</strong>
                                {{ uso.cuentas === 1 ? 'cuenta' : 'cuentas' }} y
                                <strong class="cifra text-marca-50">{{ uso.sistemas }}</strong>
                                {{ uso.sistemas === 1 ? 'sistema' : 'sistemas' }}
                            </span>
                            <span v-if="actual.plan">
                                Plan actual: <strong class="text-marca-50">{{ actual.plan }}</strong>
                                <template v-if="actual.venceEn"> · {{ actual.estado.etiqueta.toLowerCase() }} hasta el {{ fechaLegible(actual.venceEn) }}</template>
                            </span>
                            <span v-else>Sin plan: uso interno, sin límites</span>
                        </motion.p>
                        <motion.div :variants="variantesEntrada">
                            <RadioGroupRoot
                                v-model="periodo"
                                aria-label="Periodo de facturación"
                                class="inline-flex gap-1 rounded-full bg-marca-950/60 p-1 ring-1 ring-marca-700"
                            >
                                <RadioGroupItem
                                    value="anual"
                                    class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full px-5 text-sm font-medium text-marca-100 transition-colors hover:text-marca-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca-200 data-[state=checked]:bg-marca-50 data-[state=checked]:text-marca-900"
                                >
                                    Anual
                                    <span v-if="descuentoMaximo > 0" class="cifra rounded-full bg-marca-400 px-2 text-xs font-semibold text-marca-950">
                                        −{{ descuentoMaximo }} %
                                    </span>
                                </RadioGroupItem>
                                <RadioGroupItem
                                    value="mensual"
                                    class="inline-flex min-h-11 cursor-pointer items-center rounded-full px-5 text-sm font-medium text-marca-100 transition-colors hover:text-marca-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca-200 data-[state=checked]:bg-marca-50 data-[state=checked]:text-marca-900"
                                >
                                    Mensual
                                </RadioGroupItem>
                            </RadioGroupRoot>
                        </motion.div>
                    </motion.div>
                </section>

                <!-- Las tarjetas montan sobre la banda. -->
                <motion.div
                    :variants="escalonado"
                    initial="oculto"
                    animate="visible"
                    class="-mt-24 grid items-stretch gap-5 px-3 sm:px-6 md:grid-cols-2 xl:grid-cols-3"
                >
                    <motion.div v-for="t in tarjetas" :key="t.plan.id" :variants="variantesEntrada" class="flex">
                        <motion.div
                            class="relative flex w-full flex-col gap-5 rounded-xl bg-card p-6 shadow-sombra-2 ring-1"
                            :class="elegido === t.plan.id || recomendado === t.plan.id ? 'ring-2 ring-primary' : 'ring-foreground/10'"
                            :animate="{ y: elegido === t.plan.id && !reducido ? -6 : 0 }"
                            :transition="{ duration: duracion.normal, ease: curva }"
                        >
                            <motion.span
                                v-if="recomendado === t.plan.id"
                                class="absolute -top-3 left-6 rounded-full bg-primary px-3 py-0.5 text-xs font-semibold text-primary-foreground"
                                :initial="reducido ? false : { opacity: 0, scale: 0.9 }"
                                :animate="{ opacity: 1, scale: 1 }"
                                :transition="{ duration: duracion.lenta, ease: curva, delay: 0.45 }"
                            >
                                Recomendado para vosotros
                            </motion.span>

                            <div class="flex items-start justify-between gap-3">
                                <div class="grid gap-1">
                                    <h2 class="text-2xl leading-8 font-semibold tracking-[-0.02em]">{{ t.plan.nombre }}</h2>
                                    <p v-if="t.plan.descripcion" class="text-sm text-muted-foreground">{{ t.plan.descripcion }}</p>
                                </div>
                                <span v-if="t.esActual" class="shrink-0 rounded-full bg-accent px-2.5 py-0.5 text-xs font-medium text-accent-foreground">
                                    Tu plan
                                </span>
                            </div>

                            <div class="grid gap-1">
                                <p class="flex flex-wrap items-baseline gap-x-1.5">
                                    <Cifra
                                        :valor="aEuros(t.alMes)"
                                        :decimales="t.alMes % 100 === 0 ? 0 : 2"
                                        sufijo=" €"
                                        class="text-5xl leading-none font-bold tracking-[-0.04em]"
                                    />
                                    <span class="text-sm text-muted-foreground">al mes, sin IVA</span>
                                </p>
                                <p class="min-h-5 text-sm text-muted-foreground">
                                    <template v-if="t.anual">
                                        <s v-if="t.plan.descuentoAnual > 0" class="cifra">{{ euros(t.plan.precioMensualCentimos * 12) }}</s>
                                        {{ euros(t.presupuesto.siguienteCobroCentimos) }} al año, en un pago
                                    </template>
                                    <template v-else>Se paga cada mes</template>
                                </p>
                            </div>

                            <Button
                                size="lg"
                                class="h-12 text-[0.9375rem]"
                                :variant="t.presupuesto.bloqueo === null ? 'default' : 'secondary'"
                                :disabled="t.presupuesto.bloqueo !== null"
                                :aria-pressed="elegido === t.plan.id"
                                @click="elegir(t.plan.id)"
                            >
                                {{ t.boton }}
                                <ArrowRightIcon v-if="t.presupuesto.bloqueo === null" data-icon="inline-end" />
                            </Button>
                            <p v-if="t.presupuesto.bloqueo === 'no_cabe'" class="-mt-2 flex gap-2 text-sm text-estado-en-progreso">
                                <TriangleAlertIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                {{ t.presupuesto.motivo }}
                            </p>

                            <ul class="grid gap-3 border-t pt-5 text-sm">
                                <li class="flex items-center gap-2.5">
                                    <CheckIcon class="size-4 text-primary" aria-hidden="true" />
                                    <span v-if="t.plan.limiteCuentas === null">Cuentas sin límite</span>
                                    <span v-else><strong class="cifra font-semibold">{{ t.plan.limiteCuentas }}</strong> cuentas</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <CheckIcon class="size-4 text-primary" aria-hidden="true" />
                                    <span v-if="t.plan.limiteSistemas === null">Sistemas sin límite</span>
                                    <span v-else>
                                        <strong class="cifra font-semibold">{{ t.plan.limiteSistemas }}</strong>
                                        {{ t.plan.limiteSistemas === 1 ? 'sistema' : 'sistemas' }} de información
                                    </span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <CheckIcon class="size-4 text-primary" aria-hidden="true" />
                                    <span><strong class="cifra font-semibold">{{ t.plan.diasGracia }}</strong> días de gracia si un pago falla</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <CheckIcon class="size-4 text-primary" aria-hidden="true" />
                                    <span>ISO 27001 y ENS con todos los módulos</span>
                                </li>
                            </ul>
                        </motion.div>
                    </motion.div>

                    <!-- Lo que asigna sólo la plataforma: se ve, no se contrata. -->
                    <motion.div v-for="r in reservados" :key="`r-${r.id}`" :variants="variantesEntrada" class="flex">
                        <div class="flex w-full flex-col gap-5 rounded-xl bg-marca-900 p-6 text-marca-50 shadow-sombra-2">
                            <div class="flex items-start justify-between gap-3">
                                <div class="grid gap-1">
                                    <h2 class="text-2xl leading-8 font-semibold tracking-[-0.02em]">{{ r.nombre }}</h2>
                                    <p v-if="r.descripcion" class="text-sm text-marca-200">{{ r.descripcion }}</p>
                                </div>
                                <span v-if="r.esElActual" class="shrink-0 rounded-full bg-marca-50 px-2.5 py-0.5 text-xs font-medium text-marca-900">Tu plan</span>
                            </div>
                            <p class="flex gap-3 rounded-xl bg-marca-950/60 p-4 text-sm text-marca-100 ring-1 ring-marca-700">
                                <LockIcon class="mt-0.5 size-4 shrink-0 text-marca-300" aria-hidden="true" />
                                No se contrata desde aquí: lo asigna el equipo de Statera.
                            </p>
                            <p class="border-t border-marca-800 pt-5 text-sm text-marca-100">{{ limites(r) }}</p>
                        </div>
                    </motion.div>
                </motion.div>
            </div>

            <!-- Lo que supone el cambio. El `ref` va en un `div`: el de un
                 componente de motion-v no es el elemento. -->
            <div ref="resumen" class="scroll-mt-24 empty:hidden">
            <AnimatePresence>
                <motion.section
                    v-if="seleccion"
                    key="resumen"
                    aria-labelledby="titulo-resumen"
                    :variants="variantesEntrada"
                    initial="oculto"
                    animate="visible"
                    :exit="variantesSalida.oculto"
                    class="grid overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10 md:grid-cols-[minmax(0,1fr)_20rem]"
                >
                    <div class="grid content-start gap-4 p-6">
                        <h2 id="titulo-resumen" class="text-xl font-semibold tracking-[-0.015em]">
                            <template v-if="actual.plan && actual.planId !== seleccion.plan.id">De {{ actual.plan }} a {{ seleccion.plan.nombre }}</template>
                            <template v-else>{{ seleccion.plan.nombre }}, {{ periodo === 'anual' ? 'anual' : 'mensual' }}</template>
                        </h2>
                        <dl class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-6 gap-y-3 text-sm">
                            <dt class="text-muted-foreground">Nuevos límites</dt>
                            <dd class="text-right font-medium">{{ limites(seleccion.plan) }}</dd>
                            <dt class="text-muted-foreground">Se aplica</dt>
                            <dd class="text-right font-medium">Ahora mismo</dd>
                            <template v-if="!seleccion.presupuesto.periodoNuevo">
                                <dt class="text-muted-foreground">
                                    {{ seleccion.presupuesto.ajusteCentimos >= 0 ? 'Diferencia' : 'Saldo a favor' }} por los
                                    {{ seleccion.presupuesto.diasRestantes }} días que quedan
                                </dt>
                                <dd class="cifra text-right">{{ euros(Math.abs(seleccion.presupuesto.ajusteCentimos)) }}</dd>
                            </template>
                            <template v-else-if="seleccion.presupuesto.diasRestantes > 0">
                                <dt class="text-muted-foreground">Se descuenta lo que quedaba del plan actual</dt>
                                <dd class="cifra text-right">
                                    −{{ euros(seleccion.presupuesto.siguienteCobroCentimos - seleccion.presupuesto.ajusteCentimos) }}
                                </dd>
                            </template>
                            <dt class="text-muted-foreground">Renovación</dt>
                            <dd class="text-right">
                                <span class="cifra">{{ euros(seleccion.presupuesto.siguienteCobroCentimos) }}</span>
                                el {{ fechaLegible(seleccion.presupuesto.venceEn) }}
                            </dd>
                        </dl>
                        <p class="flex gap-3 rounded-xl border border-dashed bg-superficie p-3.5 text-sm text-muted-foreground">
                            <ShieldCheckIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            El pago llegará con la pasarela. Hoy el cambio se aplica sin cobrar, y el importe queda anotado en el histórico.
                        </p>
                        <p v-if="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                    </div>
                    <div class="grid content-center gap-3 bg-marca-900 p-6 text-marca-50">
                        <span class="text-sm font-medium text-marca-200">Pagaríais hoy</span>
                        <Cifra
                            :valor="aEuros(seleccion.presupuesto.importeHoyCentimos)"
                            :decimales="seleccion.presupuesto.importeHoyCentimos % 100 === 0 ? 0 : 2"
                            sufijo=" €"
                            class="text-4xl leading-none font-bold tracking-[-0.03em]"
                        />
                        <span class="text-sm text-marca-200">
                            <template v-if="seleccion.presupuesto.saldoAFavorCentimos > 0">
                                Los {{ euros(seleccion.presupuesto.saldoAFavorCentimos) }} a favor se descuentan del siguiente cobro.
                            </template>
                            <template v-else>Sin IVA.</template>
                        </span>
                        <Button
                            size="lg"
                            class="h-12 bg-marca-50 text-[0.9375rem] text-marca-900 hover:bg-marca-100 dark:hover:bg-marca-200"
                            :disabled="enviando"
                            @click="confirmar"
                        >
                            {{ enviando ? 'Cambiando…' : 'Confirmar el cambio' }}
                            <ArrowRightIcon v-if="!enviando" data-icon="inline-end" />
                        </Button>
                    </div>
                </motion.section>
            </AnimatePresence>
            </div>

            <!-- Lo que da tranquilidad. -->
            <ul class="grid gap-6 sm:grid-cols-3">
                <li class="flex gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground">
                        <ShieldCheckIcon class="size-5" aria-hidden="true" />
                    </span>
                    <span class="grid gap-0.5 text-sm">
                        <strong class="font-semibold">Nunca perdéis datos</strong>
                        <span class="text-muted-foreground">Si vence, hay gracia y luego sólo lectura. Lo vuestro sigue ahí.</span>
                    </span>
                </li>
                <li class="flex gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground">
                        <Repeat2Icon class="size-5" aria-hidden="true" />
                    </span>
                    <span class="grid gap-0.5 text-sm">
                        <strong class="font-semibold">Subid o bajad cuando queráis</strong>
                        <span class="text-muted-foreground">Bajar sólo pide que lo que usáis quepa en el plan nuevo.</span>
                    </span>
                </li>
                <li class="flex gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground">
                        <UsersIcon class="size-5" aria-hidden="true" />
                    </span>
                    <span class="grid gap-0.5 text-sm">
                        <strong class="font-semibold">El auditor no cuenta</strong>
                        <span class="text-muted-foreground">Las cuentas de auditor externo no ocupan asiento en ningún plan.</span>
                    </span>
                </li>
            </ul>

            <!-- Preguntas, centradas. -->
            <section aria-labelledby="titulo-preguntas" class="mx-auto grid w-full max-w-3xl gap-2.5 pb-6">
                <h2 id="titulo-preguntas" class="mb-2 text-center text-2xl font-semibold tracking-[-0.02em]">Preguntas frecuentes</h2>
                <div v-for="(p, indice) in PREGUNTAS" :key="p.pregunta" class="rounded-xl bg-card ring-1 ring-foreground/10">
                    <h3>
                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-between gap-3 rounded-xl px-4 text-left text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            :aria-expanded="abierta === indice"
                            :aria-controls="`respuesta-${indice}`"
                            @click="abierta = abierta === indice ? null : indice"
                        >
                            {{ p.pregunta }}
                            <ChevronDownIcon
                                class="size-4 shrink-0 text-muted-foreground transition-transform duration-(--duracion)"
                                :class="abierta === indice && 'rotate-180'"
                                aria-hidden="true"
                            />
                        </button>
                    </h3>
                    <div
                        :id="`respuesta-${indice}`"
                        class="desplegable [&:not([data-abierto])]:duration-(--duracion-salida)"
                        :data-abierto="abierta === indice ? '' : undefined"
                        :inert="abierta !== indice"
                    >
                        <div>
                            <p class="px-4 pb-4 text-sm text-pretty text-secondary-foreground">{{ p.respuesta }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
