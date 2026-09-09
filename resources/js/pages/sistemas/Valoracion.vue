<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import BarraAcciones from '@/components/formulario/BarraAcciones.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { Button } from '@/components/ui/button';
import DiffValoracion from '@/components/valoracion/DiffValoracion.vue';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useHttp } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import { computed, ref } from 'vue';

type Previsualizacion = App.Http.Resources.Valoracion.PrevisualizacionValoracion;

/** Las cinco del Anexo I. Fijas por norma, igual que el enum `Dimension`. */
type ClaveDimension = 'C' | 'I' | 'D' | 'A' | 'T';

/**
 * Claves planas (`nivel_C`) y no anidadas, para que el nombre del control y la
 * clave del error del `FormRequest` sean la misma cosa.
 */
type DatosValoracion = Record<`nivel_${ClaveDimension}`, string> &
    Record<`justificacion_${ClaveDimension}`, string>;

interface Nivel {
    valor: string;
    etiqueta: string;
    peso: number;
    /** La categoría que ese nivel implica. Nula para «no aplica». */
    categoria: string | null;
}

interface DimensionValorada {
    clave: ClaveDimension;
    nombre: string;
    pregunta: string;
    nivel: string;
    justificacion: string;
}

const props = defineProps<{
    sistema: { id: number; codigo: string; nombre: string; marco: string | null; categoria: string | null };
    exigiblesHoy: number;
    valorada: boolean;
    dimensiones: DimensionValorada[];
    niveles: Nivel[];
}>();

const { variantesEntrada } = useMovimientoReducido();

/**
 * Estado controlado, y no el `FormData` de `<Form>`, porque esta pantalla tiene
 * que contestar dos preguntas mientras se rellena: qué categoría sale de lo
 * elegido, y a qué medidas afecta guardarlo.
 *
 * El propio formulario de `useHttp` hace de estado: la previsualización es una
 * petición que no navega, y es exactamente para eso que existe el hook.
 */
const previa = useHttp<DatosValoracion, Previsualizacion>(
    Object.fromEntries(
        props.dimensiones.flatMap((dimension) => [
            [`nivel_${dimension.clave}`, dimension.nivel],
            [`justificacion_${dimension.clave}`, dimension.justificacion],
        ]),
    ) as DatosValoracion,
);

const porValor = computed(() => new Map(props.niveles.map((nivel) => [nivel.valor, nivel])));

/**
 * Paso 1 del motor, pintado en vivo: la categoría es la más alta de las cinco.
 *
 * El mapa nivel → categoría llega del servidor, así que la correspondencia del
 * Anexo I no se reescribe aquí. Y esto es lo que se ve, no lo que manda: al
 * guardar la recalcula el servidor a partir de la valoración escrita.
 */
const categoriaDerivada = computed<string | null>(() => {
    const maximo = props.dimensiones.reduce<Nivel | undefined>((mayor, dimension) => {
        const nivel = porValor.value.get(previa[`nivel_${dimension.clave}`]);

        return nivel && (mayor === undefined || nivel.peso > mayor.peso) ? nivel : mayor;
    }, undefined);

    return maximo?.categoria ?? null;
});

const diff = ref<Previsualizacion | null>(null);
const guardando = ref(false);

/** Pregunta qué pasaría. No escribe nada, y valida con las mismas reglas. */
async function revisar(): Promise<void> {
    diff.value = null;

    try {
        await previa.post(`/sistemas/${props.sistema.id}/valoracion/simulacion`, {
            // Sólo en 2xx: un 422 rellena `previa.errors` y no abre el diálogo.
            onSuccess: (previsualizacion) => (diff.value = previsualizacion),
        });
    } catch {
        /* Un fallo de red o un 500 los anuncia el manejador global de Inertia. */
    }
}

function aplicar(): void {
    router.put(`/sistemas/${props.sistema.id}/valoracion`, { ...previa.data() }, {
        onStart: () => (guardando.value = true),
        onFinish: () => (guardando.value = false),
    });
}
</script>

<template>
    <AppLayout :titulo="`Valorar ${sistema.codigo}`">
        <div class="mx-auto w-full max-w-4xl pb-24">
            <CabeceraPagina
                :titulo="`Valoración de ${sistema.codigo}`"
                descripcion="Las cinco dimensiones del Anexo I son la única entrada del motor. La categoría no se elige: es la más alta de las cinco, y de ella sale todo lo que se le exige al sistema."
            />

            <motion.div :variants="variantesEntrada" initial="oculto" animate="visible" class="space-y-6">
                <div class="flex flex-wrap items-end justify-between gap-4 rounded-xl border bg-superficie px-5 py-4">
                    <div class="min-w-0">
                        <p class="font-medium">{{ sistema.nombre }}</p>
                        <p v-if="sistema.marco" class="mt-0.5 text-sm text-muted-foreground">{{ sistema.marco }}</p>
                    </div>

                    <div class="text-right">
                        <p class="text-xs text-muted-foreground">Categoría resultante</p>
                        <p class="text-lg font-semibold tracking-tight">
                            {{ categoriaDerivada ?? 'Fuera del ámbito del ENS' }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{
                                valorada
                                    ? `${exigiblesHoy} medidas exigibles hoy`
                                    : 'Sin valorar: el sistema no tiene todavía ninguna medida exigible'
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="previa.hasErrors"
                    role="alert"
                    class="rounded-xl border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
                >
                    Revisa las dimensiones marcadas: las cinco tienen que llevar un nivel.
                </div>

                <SeccionFormulario
                    v-for="dimension in dimensiones"
                    :key="dimension.clave"
                    :titulo="dimension.nombre"
                    :ayuda="dimension.pregunta"
                >
                    <CampoSelect
                        v-model="previa[`nivel_${dimension.clave}`]"
                        :nombre="`nivel_${dimension.clave}`"
                        etiqueta="Nivel"
                        :opciones="niveles"
                        :error="previa.errors[`nivel_${dimension.clave}`]"
                        requerido
                    />

                    <CampoTextarea
                        v-model="previa[`justificacion_${dimension.clave}`]"
                        :nombre="`justificacion_${dimension.clave}`"
                        etiqueta="Justificación"
                        :filas="2"
                        :error="previa.errors[`justificacion_${dimension.clave}`]"
                        ayuda="Lo que el auditor contrasta cuando discute la categoría del sistema."
                    />
                </SeccionFormulario>
            </motion.div>

            <BarraAcciones url-cancelar="/sistemas">
                <template #nota>No se guarda nada hasta que confirmes el recálculo.</template>

                <Button type="button" :disabled="previa.processing" @click="revisar">
                    {{ previa.processing ? 'Calculando…' : 'Revisar cambios' }}
                </Button>
            </BarraAcciones>
        </div>

        <DiffValoracion
            v-if="diff"
            :diff="diff"
            :guardando="guardando"
            @cancelar="diff = null"
            @confirmar="aplicar"
        />
    </AppLayout>
</template>
