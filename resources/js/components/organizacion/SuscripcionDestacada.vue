<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { fechaLegible } from '@/lib/celdas';
import { euros } from '@/lib/dinero';
import { curva, curvaEnPantalla, duracion } from '@/lib/motion';
import { Link } from '@inertiajs/vue3';
import { ArrowRightIcon, LockIcon, ServerIcon, UsersIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed } from 'vue';

/**
 * La suscripción, lo primero de `/organizacion` (punto 51).
 *
 * Antes eran cinco datos sueltos al pie de la ficha, y la pregunta que trae a
 * alguien a esta pantalla suele ser ésa: qué plan tenemos, hasta cuándo y si
 * nos cabe lo que queremos hacer. Por eso va arriba, y en un bloque de marca
 * que no se confunde con el formulario de debajo.
 *
 * **Es el sexto momento de DESIGN.md §10.** La línea del contrato se traza de
 * izquierda a derecha, la marca de «Hoy» cae encima cuando la línea llega, los
 * días cuentan hasta su número y las barras de consumo se llenan. Una vez por
 * visita: guardar la ficha no lo repite, porque Vue parchea la página en vez de
 * montarla otra vez. Con movimiento reducido no se traza nada: el bloque se
 * funde y las barras están llenas desde el principio.
 *
 * Todo lo que es número o fecha lo calcula `ResumenSuscripcion`; aquí sólo se
 * coloca la línea, que es geometría de pantalla.
 */
interface Contrato {
    plan: string | null;
    descripcion: string | null;
    estado: App.Http.Resources.Definicion.ValorEtiquetado & { valor: 'vigente' | 'en_gracia' | 'solo_lectura' };
    periodo: string | null;
    precioPeriodoCentimos: number | null;
    iniciaEn: string | null;
    venceEn: string | null;
    finGracia: string | null;
    diasGracia: number | null;
    dias: number | null;
    limiteCuentas: number | null;
    limiteSistemas: number | null;
    uso: { cuentas: number; sistemas: number };
    puedeContratar: boolean;
    historico: { id: number; fecha: string; que: string; quien: string; importeCentimos: number | null }[];
}

const props = defineProps<{ contrato: Contrato }>();

const { reducido } = useMovimientoReducido();

const estado = computed(() => props.contrato.estado.valor);

/** Faltan treinta días o menos: el primer aviso por correo sale ahí. */
const porVencer = computed(
    () => estado.value === 'vigente' && props.contrato.dias !== null && props.contrato.dias <= 30,
);

const resumen = computed(() => {
    const c = props.contrato;

    if (c.plan === null) {
        return 'Sin límite de cuentas ni de sistemas, y sin vencimiento.';
    }

    const cuentas = c.limiteCuentas === null ? 'cuentas sin límite' : `hasta ${c.limiteCuentas} cuentas`;
    const sistemas =
        c.limiteSistemas === null
            ? 'sistemas sin límite'
            : `${c.limiteSistemas} ${c.limiteSistemas === 1 ? 'sistema' : 'sistemas'} de información`;

    return `${cuentas.charAt(0).toUpperCase()}${cuentas.slice(1)} y ${sistemas}.`;
});

const facturacion = computed(() => {
    const c = props.contrato;

    if (c.periodo === null || c.precioPeriodoCentimos === null) {
        return null;
    }

    return `${c.periodo} · ${euros(c.precioPeriodoCentimos)} ${c.periodo === 'Anual' ? 'al año' : 'al mes'}, sin IVA`;
});

/** Lo que dice la cifra grande, según dónde está la suscripción. */
const cuenta = computed(() => {
    const c = props.contrato;
    const dias = c.dias ?? 0;
    const unidad = dias === 1 ? 'día' : 'días';

    switch (estado.value) {
        case 'en_gracia':
            return { etiqueta: 'Para pasar a sólo lectura', unidad, fecha: `Hasta el ${fechaLegible(c.finGracia)}` };
        case 'solo_lectura':
            return { etiqueta: 'En sólo lectura desde hace', unidad, fecha: `Desde el ${fechaLegible(c.finGracia)}` };
        default:
            return { etiqueta: 'Quedan', unidad, fecha: `Vence el ${fechaLegible(c.venceEn)}` };
    }
});

const textoBoton = computed(() => {
    switch (estado.value) {
        case 'solo_lectura':
            return 'Renovar ahora';
        case 'en_gracia':
            return 'Renovar o cambiar de plan';
        default:
            return porVencer.value ? 'Renovar o cambiar de plan' : 'Ver planes';
    }
});

const DIA = 86_400_000;

/**
 * Dónde cae cada tramo, en porcentaje del ancho. La línea va del inicio al fin
 * de la gracia, con un margen detrás para que el tramo de sólo lectura se vea;
 * si ya se está en sólo lectura, se alarga hasta hoy.
 */
const linea = computed(() => {
    const c = props.contrato;

    if (c.venceEn === null || c.finGracia === null) {
        return null;
    }

    const vence = Date.parse(c.venceEn);
    const fin = Date.parse(c.finGracia);
    const inicio = c.iniciaEn === null ? vence - 365 * DIA : Date.parse(c.iniciaEn);
    const hoy = Date.now();
    const tramo = Math.max(fin - inicio, DIA);
    const final = Math.max(fin + tramo * 0.06, hoy + tramo * 0.03);
    const pct = (instante: number): number => Math.min(100, Math.max(0, ((instante - inicio) / (final - inicio)) * 100));

    return { vence: pct(vence), fin: pct(fin), hoy: pct(hoy) };
});

const medidores = computed(() => {
    const c = props.contrato;

    return [
        { clave: 'cuentas', titulo: 'Cuentas', icono: UsersIcon, uso: c.uso.cuentas, limite: c.limiteCuentas, nota: 'Los auditores externos no ocupan asiento.' },
        { clave: 'sistemas', titulo: 'Sistemas de información', icono: ServerIcon, uso: c.uso.sistemas, limite: c.limiteSistemas, nota: 'Cuenta al dar de alta un sistema nuevo.' },
    ].map((m) => {
        const pct = m.limite === null ? 100 : Math.min(100, Math.round((m.uso / m.limite) * 100));
        const libres = m.limite === null ? null : Math.max(0, m.limite - m.uso);

        return {
            ...m,
            pct,
            cerca: m.limite !== null && pct >= 80,
            libres: libres === null ? 'Sin límite' : libres === 1 ? 'Te queda 1' : libres === 0 ? 'Sin hueco' : `Te quedan ${libres}`,
        };
    });
});

/* El escalonado del momento: el bloque, la línea, la marca de hoy y las barras. */
const trazo = { duration: duracion.trazo, ease: curvaEnPantalla, delay: 0.15 };
const llegada = { duration: duracion.lenta, ease: curva, delay: 0.15 + duracion.trazo };
const llenado = (indice: number) => ({ duration: duracion.trazo, ease: curvaEnPantalla, delay: 0.3 + indice * 0.08 });
</script>

<template>
    <div class="grid gap-4">
        <section
            aria-labelledby="titulo-suscripcion"
            class="overflow-hidden rounded-xl bg-marca-900 text-marca-50 shadow-sombra-2"
        >
            <div class="flex flex-wrap items-start justify-between gap-8 p-6 sm:p-8">
                <div class="min-w-0 flex-[1_1_24rem] space-y-3">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 id="titulo-suscripcion" class="text-sm font-medium text-marca-200">Suscripción</h2>
                        <CeldaBadge :valor="contrato.estado" />
                    </div>
                    <p class="text-3xl leading-[2.125rem] font-semibold tracking-[-0.02em] text-balance sm:text-4xl sm:leading-[2.75rem]">
                        {{ contrato.plan ?? 'Sin plan' }}
                    </p>
                    <p class="max-w-xl text-base leading-relaxed text-pretty text-marca-100">{{ resumen }}</p>
                    <p v-if="facturacion" class="text-sm text-marca-200">{{ facturacion }}</p>
                    <p v-if="porVencer" class="text-sm font-medium text-marca-50">
                        Renueva antes del {{ fechaLegible(contrato.venceEn) }} para no entrar en el periodo de gracia.
                    </p>
                </div>

                <div class="flex flex-[0_1_18rem] flex-col gap-1.5 rounded-xl bg-marca-950/60 px-6 py-5 ring-1 ring-marca-700">
                    <template v-if="contrato.dias !== null">
                        <span class="text-sm font-medium text-marca-200">{{ cuenta.etiqueta }}</span>
                        <span class="flex items-baseline gap-2">
                            <Cifra :valor="contrato.dias" class="text-5xl leading-none font-bold tracking-[-0.04em]" />
                            <span class="text-base font-medium text-marca-100">{{ cuenta.unidad }}</span>
                        </span>
                        <span class="text-sm text-marca-200">{{ cuenta.fecha }}</span>
                    </template>
                    <template v-else>
                        <span class="text-sm font-medium text-marca-200">Vencimiento</span>
                        <span class="text-2xl font-semibold">No vence</span>
                        <span class="text-sm text-marca-200">Nada que renovar.</span>
                    </template>
                </div>
            </div>

            <!-- La línea del contrato: vigente, gracia y sólo lectura. -->
            <div v-if="linea" class="space-y-3 px-6 pb-6 sm:px-8">
                <div class="relative h-10" aria-hidden="true">
                    <motion.div
                        class="absolute inset-x-0 top-6 h-2 origin-left overflow-hidden rounded-full bg-marca-800"
                        :initial="reducido ? false : { scaleX: 0 }"
                        :animate="{ scaleX: 1 }"
                        :transition="trazo"
                    >
                        <div class="absolute inset-y-0 left-0 bg-marca-400" :style="{ width: `${linea.vence}%` }" />
                        <div
                            class="absolute inset-y-0 bg-estado-en-revision"
                            :style="{ left: `${linea.vence}%`, width: `${linea.fin - linea.vence}%` }"
                        />
                        <div class="absolute inset-y-0 right-0 bg-destructive" :style="{ left: `${linea.fin}%` }" />
                    </motion.div>
                    <div class="absolute top-0 -translate-x-1/2" :style="{ left: `${linea.hoy}%` }">
                        <motion.div
                            class="flex flex-col items-center gap-0.5"
                            :initial="reducido ? false : { opacity: 0, y: -8 }"
                            :animate="{ opacity: 1, y: 0 }"
                            :transition="llegada"
                        >
                            <span class="rounded bg-marca-50 px-1.5 text-xs font-semibold text-marca-900">Hoy</span>
                            <span class="h-5 w-0.5 rounded-full bg-marca-50" />
                        </motion.div>
                    </div>
                </div>
                <ul class="flex flex-wrap gap-x-7 gap-y-2 text-sm text-marca-100">
                    <li class="flex items-center gap-2">
                        <span class="size-2.5 rounded-sm bg-marca-400" aria-hidden="true" />
                        Vigente<template v-if="contrato.iniciaEn"> desde el {{ fechaLegible(contrato.iniciaEn) }}</template>
                        hasta el {{ fechaLegible(contrato.venceEn) }}
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="size-2.5 rounded-sm bg-estado-en-revision" aria-hidden="true" />
                        Gracia: {{ contrato.diasGracia }} días, trabajando con aviso
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="size-2.5 rounded-sm bg-destructive" aria-hidden="true" />
                        Sólo lectura desde el {{ fechaLegible(contrato.finGracia) }}
                    </li>
                </ul>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-marca-800 px-6 py-4 sm:px-8">
                <p class="text-sm text-marca-200">
                    <template v-if="contrato.puedeContratar">
                        El cambio es inmediato y sólo se paga la parte que queda del periodo.
                    </template>
                    <template v-else>El plan lo asigna el equipo de Statera.</template>
                </p>
                <Button
                    v-if="contrato.puedeContratar"
                    as-child
                    size="lg"
                    class="bg-marca-50 text-marca-900 hover:bg-marca-100 dark:hover:bg-marca-200"
                >
                    <Link href="/organizacion/plan">
                        {{ textoBoton }}
                        <ArrowRightIcon data-icon="inline-end" />
                    </Link>
                </Button>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2">
            <div v-for="(m, indice) in medidores" :key="m.clave" class="grid gap-3 rounded-xl bg-card p-5 ring-1 ring-foreground/10">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="flex items-center gap-2 text-base font-semibold tracking-[-0.01em]">
                        <component :is="m.icono" class="size-4 text-muted-foreground" aria-hidden="true" />
                        {{ m.titulo }}
                    </h3>
                    <span class="shrink-0 text-xs font-medium" :class="m.cerca ? 'text-estado-en-progreso' : 'text-muted-foreground'">
                        {{ m.libres }}
                    </span>
                </div>
                <p class="flex items-baseline gap-1.5">
                    <span class="cifra text-3xl font-semibold">{{ m.uso }}</span>
                    <span class="cifra text-base text-muted-foreground">{{ m.limite === null ? 'sin límite' : `/ ${m.limite}` }}</span>
                </p>
                <div
                    role="meter"
                    :aria-label="m.titulo"
                    :aria-valuenow="m.uso"
                    aria-valuemin="0"
                    :aria-valuemax="m.limite ?? m.uso"
                    class="h-2 overflow-hidden rounded-full bg-muted"
                >
                    <motion.div
                        class="h-full origin-left rounded-full"
                        :class="m.limite === null ? 'bg-marca-200 dark:bg-marca-800' : m.cerca ? 'bg-estado-en-progreso' : 'bg-primary'"
                        :style="{ width: `${m.pct}%` }"
                        :initial="reducido ? false : { scaleX: 0 }"
                        :animate="{ scaleX: 1 }"
                        :transition="llenado(indice)"
                    />
                </div>
                <p class="text-xs text-muted-foreground">{{ m.nota }}</p>
            </div>

            <div class="grid content-start gap-3 rounded-xl bg-card p-5 ring-1 ring-foreground/10 sm:col-span-2">
                <h3 class="flex items-center gap-2 text-base font-semibold tracking-[-0.01em]">
                    <LockIcon class="size-4 text-muted-foreground" aria-hidden="true" />
                    Si vence sin renovar
                </h3>
                <ol class="grid gap-2.5 text-sm text-secondary-foreground">
                    <li class="flex gap-2.5">
                        <span class="cifra grid size-5 shrink-0 place-items-center rounded-full bg-estado-en-revision-suave text-xs text-estado-en-revision">1</span>
                        <span v-if="contrato.diasGracia !== null">Sigues trabajando {{ contrato.diasGracia }} días más, con un aviso arriba.</span>
                        <span v-else>Sin plan no hay vencimiento, así que no aplica.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <span class="cifra grid size-5 shrink-0 place-items-center rounded-full bg-destructive/10 text-xs text-destructive">2</span>
                        <span>Después, sólo lectura: ves y descargas todo, pero no guardas.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <span class="cifra grid size-5 shrink-0 place-items-center rounded-full bg-accent text-xs text-accent-foreground">3</span>
                        <span>No se pierde nada, y tu cuenta, tu contraseña y tu 2FA siguen siendo tuyas.</span>
                    </li>
                </ol>
            </div>
        </div>

        <section
            v-if="contrato.historico.length > 0"
            aria-labelledby="titulo-historico-suscripcion"
            class="rounded-xl bg-card p-5 ring-1 ring-foreground/10"
        >
            <h3 id="titulo-historico-suscripcion" class="text-base font-semibold tracking-[-0.01em]">Histórico de la suscripción</h3>
            <ol class="mt-3">
                <li
                    v-for="cambio in contrato.historico"
                    :key="cambio.id"
                    class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4 border-t py-2.5 first:border-t-0 sm:grid-cols-[8rem_minmax(0,1fr)_auto]"
                >
                    <span class="cifra text-sm text-muted-foreground">{{ fechaLegible(cambio.fecha) }}</span>
                    <span class="grid gap-0.5">
                        <span class="text-sm font-medium">{{ cambio.que }}</span>
                        <span class="text-xs text-muted-foreground">{{ cambio.quien }}</span>
                    </span>
                    <span v-if="cambio.importeCentimos !== null" class="cifra col-start-2 text-sm text-muted-foreground sm:col-start-3 sm:text-right">
                        {{ cambio.importeCentimos < 0 ? `${euros(-cambio.importeCentimos)} a favor` : euros(cambio.importeCentimos) }}
                    </span>
                </li>
            </ol>
        </section>
    </div>
</template>
