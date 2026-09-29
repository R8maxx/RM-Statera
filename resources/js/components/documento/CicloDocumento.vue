<script setup lang="ts">
import { CheckIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Dónde está la próxima versión del documento, en cuatro pasos.
 *
 * Contesta «¿en qué punto está esto?» de un vistazo, que antes había que
 * deducir cruzando el badge del borrador, el de la aprobación y la ficha. Habla
 * siempre de **la versión siguiente** —la que está en camino—, no de la vigente:
 * la vigente ya está entregada y la ficha lateral dice cuál es.
 *
 * **No inventa fechas.** Una versión no guarda cuándo se generó el PDF ni cuándo
 * se mandó a revisión —`updated_at` se mueve con cualquiera de las dos—, así que
 * cada paso dice lo que se sabe y nada más.
 *
 * El violeta sólo en el paso de revisión, que es su único sitio fuera del botón
 * de firma (DESIGN.md §3). El resto va en el teal de marca o en neutro.
 */
interface VersionEnCurso {
    estado: string;
    generacion: string;
    enCurso: boolean;
    descargable: boolean;
    aprobadaPor: string | null;
    quien: string | null;
}

const props = defineProps<{
    version: VersionEnCurso | null;
    /** La etiqueta que llevará al emitirse: `v3`. */
    siguiente: string;
    periodicidadMeses: number | null;
}>();

type Situacion = 'hecho' | 'actual' | 'pendiente';

interface Paso {
    titulo: string;
    detalle: string;
    situacion: Situacion;
    /** Sólo el paso de revisión va en violeta. */
    revision?: boolean;
}

const pasos = computed<Paso[]>(() => {
    const v = props.version;
    const emitiendo = v !== null && v.estado === 'en_revision' && v.aprobadaPor !== null;

    const borrador: Paso = (() => {
        if (v === null) {
            return { titulo: 'Generar el borrador', detalle: 'Todavía no hay ninguno.', situacion: 'actual' };
        }
        if (emitiendo) {
            return { titulo: 'Borrador generado', detalle: v.quien ? `Por ${v.quien}.` : '', situacion: 'hecho' };
        }
        if (v.enCurso) {
            return { titulo: 'Generando el borrador', detalle: 'Tarda unos segundos.', situacion: 'actual' };
        }
        if (v.generacion === 'fallida') {
            return { titulo: 'La generación falló', detalle: 'Corrige lo que indica el error y regenéralo.', situacion: 'actual' };
        }
        if (!v.descargable) {
            return { titulo: 'Generar el borrador', detalle: 'Todavía no hay PDF.', situacion: 'actual' };
        }

        return { titulo: 'Borrador generado', detalle: v.quien ? `Por ${v.quien}.` : '', situacion: 'hecho' };
    })();

    const revision: Paso = (() => {
        if (v === null || borrador.situacion !== 'hecho') {
            return { titulo: 'Enviar a revisión', detalle: 'Cuando el borrador se haya leído entero.', situacion: 'pendiente' };
        }
        if (emitiendo) {
            return { titulo: 'Revisado', detalle: `Firmado por ${v.aprobadaPor}.`, situacion: 'hecho' };
        }
        if (v.estado === 'en_revision') {
            return { titulo: 'En revisión', detalle: 'Espera la firma de la dirección.', situacion: 'actual', revision: true };
        }
        if (v.estado === 'rechazado') {
            return { titulo: 'Rechazado', detalle: 'Corrígelo y vuelve a enviarlo a revisión.', situacion: 'actual' };
        }

        return { titulo: 'Enviar a revisión', detalle: 'Cuando el borrador se haya leído entero.', situacion: 'actual' };
    })();

    const emision: Paso = emitiendo
        ? { titulo: `Emitiendo la ${props.siguiente}`, detalle: 'Se regenera con la firma en portada.', situacion: 'actual' }
        : {
              titulo: `Emitida como ${props.siguiente}`,
              detalle: 'La firma numera la versión y congela el PDF.',
              situacion: 'pendiente',
          };

    const periodica: Paso = {
        titulo: 'Revisión periódica',
        detalle:
            props.periodicidadMeses === null
                ? 'Sin periodicidad: se rehace cuando cambia lo que declara.'
                : `Toca revisarla a los ${props.periodicidadMeses} ${props.periodicidadMeses === 1 ? 'mes' : 'meses'} de la firma.`,
        situacion: 'pendiente',
    };

    return [borrador, revision, emision, periodica];
});
</script>

<template>
    <section aria-labelledby="ciclo-documento" class="rounded-xl bg-card px-6 py-5 ring-1 ring-foreground/10">
        <h2 id="ciclo-documento" class="sr-only">Ciclo de la {{ siguiente }}</h2>

        <ol class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
            <li
                v-for="(paso, indice) in pasos"
                :key="indice"
                class="flex flex-col gap-2.5"
                :aria-current="paso.situacion === 'actual' ? 'step' : undefined"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="cifra flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-medium"
                        :class="{
                            'bg-primary text-primary-foreground': paso.situacion === 'hecho',
                            'border-2 border-acento bg-acento-suave text-acento': paso.situacion === 'actual' && paso.revision,
                            'border-2 border-primary bg-accent text-primary': paso.situacion === 'actual' && !paso.revision,
                            'border-2 border-border text-muted-foreground': paso.situacion === 'pendiente',
                        }"
                    >
                        <CheckIcon v-if="paso.situacion === 'hecho'" class="size-4" aria-hidden="true" />
                        <template v-else>{{ indice + 1 }}</template>
                    </span>

                    <!-- El tramo hasta el siguiente paso, lleno si éste ya está hecho. -->
                    <div
                        v-if="indice < pasos.length - 1"
                        class="hidden h-0.5 flex-1 rounded-full lg:block"
                        :class="paso.situacion === 'hecho' ? 'bg-primary' : 'bg-border'"
                        aria-hidden="true"
                    />
                </div>

                <p
                    class="text-sm font-semibold"
                    :class="{
                        'text-acento': paso.situacion === 'actual' && paso.revision,
                        'text-primary': paso.situacion === 'actual' && !paso.revision,
                        'text-secondary-foreground': paso.situacion === 'pendiente',
                    }"
                >
                    {{ paso.titulo }}
                    <span v-if="paso.situacion === 'actual'" class="font-medium text-muted-foreground">· ahora</span>
                    <span v-else-if="paso.situacion === 'hecho'" class="sr-only">(hecho)</span>
                </p>
                <p v-if="paso.detalle" class="text-[13px] leading-[18px] text-pretty text-muted-foreground">
                    {{ paso.detalle }}
                </p>
            </li>
        </ol>
    </section>
</template>
