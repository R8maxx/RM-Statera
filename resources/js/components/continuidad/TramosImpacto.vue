<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { tono } from '@/lib/tonos';
import { computed } from 'vue';

export interface Tramo {
    clave: string;
    etiqueta: string;
    horas: number;
    nivel: string;
    nivelEtiqueta: string;
    nivelTono: string;
    nivelIcono: string;
    /** `NivelImpacto::peso()`: la altura del paso en la escala. */
    nivelPeso: number;
    /** Si es el primer tramo en el que el impacto llega a «muy alto»: el MTPD. */
    esUmbral: boolean;
}

/**
 * Los cinco tramos del MTPD como escala de pasos, con el RTO y el umbral
 * puestos encima.
 *
 * **Escala de pasos y no cinco badges sueltos**, porque el nivel es ordinal
 * (DESIGN.md § 9, «Qué se pinta con color»): lo que se pregunta es si a una
 * semana duele más que a un día, y eso lo contesta la altura antes que el
 * texto. Los pasos van en un solo hue —el de marca, magnitud de una sola
 * serie— y **el badge de cada tramo sigue debajo**, con su tono, su icono y su
 * palabra: la altura ordena, el badge identifica.
 *
 * **El RTO se coloca sobre el mismo eje que los tramos**, interpolado entre
 * los centros de las dos columnas que lo rodean. Los tramos no están
 * equiespaciados en horas —4 h, 1 día, 3 días, 1 semana, 1 mes—, así que la
 * posición dice «entre este tramo y el siguiente», no una proporción de
 * tiempo; es lo que hace falta para ver de un vistazo cuánto margen deja el
 * RTO hasta el umbral.
 *
 * **La contradicción va en ámbar (`en_progreso`) y no en rojo.** El rojo del
 * dominio es `caducada`, reservado a lo vencido o lo incumplido (DESIGN.md §3):
 * un RTO que promete más de lo que el propio BIA tolera es una contradicción
 * que hay que corregir, no un incumplimiento consumado —nadie ha dejado de
 * cumplir un plazo todavía—, así que el aviso pide atención sin gastar el
 * único rojo del vocabulario.
 */
const props = defineProps<{
    tramos: Tramo[];
    pesoMaximo: number;
    rtoHoras: number;
    umbralHoras: number | null;
    rtoIncoherente: boolean;
}>();

const aviso = computed(() => tono('en_progreso'));

/**
 * Los cuatro escalones de la marca, de claro a oscuro. Escritos enteros, que
 * Tailwind no genera clases compuestas en ejecución.
 */
const PASOS = ['bg-marca-200', 'bg-marca-300', 'bg-marca-500', 'bg-marca-700'];

function relleno(peso: number): string {
    const indice = Math.round(((peso - 1) / Math.max(props.pesoMaximo - 1, 1)) * (PASOS.length - 1));

    return PASOS[Math.min(Math.max(indice, 0), PASOS.length - 1)];
}

/** Dónde cae un número de horas sobre el eje, en porcentaje del ancho. */
function posicion(horas: number): number {
    const tramos = props.tramos;
    const columnas = tramos.length;

    if (columnas === 0) {
        return 0;
    }

    if (horas <= tramos[0].horas) {
        return ((0.5 * horas) / tramos[0].horas / columnas) * 100;
    }

    for (let indice = 0; indice < columnas - 1; indice++) {
        const desde = tramos[indice].horas;
        const hasta = tramos[indice + 1].horas;

        if (horas <= hasta) {
            return ((indice + 0.5 + (horas - desde) / (hasta - desde)) / columnas) * 100;
        }
    }

    return 100;
}

const rto = computed(() => posicion(props.rtoHoras));
const umbral = computed(() => (props.umbralHoras === null ? null : posicion(props.umbralHoras)));

/** La franja entre el RTO y el umbral: margen si queda dentro, exceso si no. */
const franja = computed(() => {
    if (umbral.value === null) {
        return null;
    }

    const desde = Math.min(rto.value, umbral.value);

    return { izquierda: desde, ancho: Math.abs(umbral.value - rto.value) };
});

/**
 * Cómo se ancla cada rótulo para no salirse del eje ni pisar al otro. Si el
 * RTO y el umbral caen a menos de un décimo del ancho, el que va a la
 * izquierda se alinea por su final y el otro por su principio.
 */
function anclaje(propia: number, otra: number | null): string {
    if (otra !== null && Math.abs(propia - otra) < 10) {
        return propia <= otra ? '-translate-x-full' : 'translate-x-0';
    }

    if (propia < 8) {
        return 'translate-x-0';
    }

    return propia > 92 ? '-translate-x-full' : '-translate-x-1/2';
}

/** «6 días», «1 día», «30 h»: en días cuando cuadra, en horas si no. */
function duracion(horas: number): string {
    if (horas >= 24 && horas % 24 === 0) {
        const dias = horas / 24;

        return dias === 1 ? '1 día' : `${dias} días`;
    }

    return `${horas} h`;
}

const diferencia = computed(() =>
    props.umbralHoras === null ? null : duracion(Math.abs(props.umbralHoras - props.rtoHoras)),
);

const descripcion = computed(() => {
    const niveles = props.tramos.map((tramo) => `${tramo.etiqueta}, ${tramo.nivelEtiqueta.toLowerCase()}`).join('; ');
    const mtpd = props.tramos.find((tramo) => tramo.esUmbral);

    return `Impacto por tramo: ${niveles}. RTO de ${props.rtoHoras} horas${mtpd ? `; umbral tolerable en ${mtpd.etiqueta}` : '; sin umbral intolerable'}.`;
});
</script>

<template>
    <figure class="space-y-3">
        <figcaption class="sr-only">{{ descripcion }}</figcaption>

        <div aria-hidden="true" class="relative h-48">
            <div
                v-if="franja && franja.ancho > 0"
                class="absolute top-6 h-34 rounded-md"
                :class="rtoIncoherente ? 'bg-estado-en-progreso-suave' : 'bg-marca-50 dark:bg-marca-950'"
                :style="{ left: `${franja.izquierda}%`, width: `${franja.ancho}%` }"
            />
            <span
                v-if="franja && franja.ancho > 12 && diferencia"
                class="absolute top-9 -translate-x-1/2 text-xs font-medium whitespace-nowrap"
                :class="rtoIncoherente ? 'text-estado-en-progreso' : 'text-primary'"
                :style="{ left: `${franja.izquierda + franja.ancho / 2}%` }"
            >
                {{ rtoIncoherente ? `${diferencia} por encima` : `${diferencia} de margen` }}
            </span>

            <div
                class="absolute top-6 h-34 border-l-2 border-dashed border-secondary-foreground"
                :style="{ left: `${rto}%` }"
            />
            <span
                class="cifra absolute top-0 rounded-full bg-secondary-foreground px-2 py-0.5 text-xs font-medium whitespace-nowrap text-background"
                :class="anclaje(rto, umbral)"
                :style="{ left: `${rto}%` }"
            >
                RTO {{ rtoHoras }} h
            </span>

            <template v-if="umbral !== null">
                <div class="absolute top-6 h-34 border-l-2 border-primary" :style="{ left: `${umbral}%` }" />
                <span
                    class="cifra absolute top-0 rounded-full bg-primary px-2 py-0.5 text-xs font-medium whitespace-nowrap text-primary-foreground"
                    :class="anclaje(umbral, rto)"
                    :style="{ left: `${umbral}%` }"
                >
                    MTPD
                </span>
            </template>

            <ol
                class="absolute inset-x-0 top-10 grid h-30 items-end border-b border-border"
                :style="{ gridTemplateColumns: `repeat(${tramos.length}, minmax(0, 1fr))` }"
            >
                <li v-for="tramo in tramos" :key="tramo.clave" class="flex justify-center">
                    <div
                        class="w-14 rounded-t-md"
                        :class="relleno(tramo.nivelPeso)"
                        :style="{ height: `${(tramo.nivelPeso / pesoMaximo) * 108}px` }"
                    />
                </li>
            </ol>

            <div
                class="cifra absolute inset-x-0 top-42 grid text-center text-xs text-muted-foreground"
                :style="{ gridTemplateColumns: `repeat(${tramos.length}, minmax(0, 1fr))` }"
            >
                <span v-for="tramo in tramos" :key="tramo.clave" :class="tramo.esUmbral ? 'font-medium text-primary' : undefined">
                    {{ tramo.etiqueta }}
                </span>
            </div>
        </div>

        <ul
            class="grid justify-items-center gap-2"
            :style="{ gridTemplateColumns: `repeat(${tramos.length}, minmax(0, 1fr))` }"
        >
            <li v-for="tramo in tramos" :key="tramo.clave">
                <CeldaBadge
                    :valor="{
                        valor: tramo.nivel,
                        etiqueta: tramo.nivelEtiqueta,
                        tono: tramo.nivelTono,
                        icono: tramo.nivelIcono,
                    }"
                />
            </li>
        </ul>

        <p
            v-if="rtoIncoherente"
            class="flex items-center gap-2 rounded-xl border px-3.5 py-2.5 text-sm"
            :class="aviso.badge"
        >
            <IconoTipo :nombre="aviso.icono" clase="size-4 shrink-0" />
            RTO <span class="cifra font-medium">{{ rtoHoras }} h</span> por encima del umbral tolerable: el propio BIA
            no lo aguanta.
        </p>
    </figure>
</template>
