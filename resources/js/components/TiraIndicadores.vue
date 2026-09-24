<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { motion } from 'motion-v';
import Cifra from '@/components/Cifra.vue';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2Icon } from '@lucide/vue';
import { computed } from 'vue';

type Indicador = App.Http.Resources.Panel.Indicador;

/**
 * Lo que pide acción, encima de la tabla que lo contiene.
 *
 * Nació para el inventario y sirve igual para cualquier módulo que tenga tabla:
 * lo único que cambia es el sustantivo del denominador y la ruta a la que se
 * enlaza, y la ruta viaja con cada indicador. Duplicar esto por módulo es cómo
 * se acaba con cuatro tiras que se parecen y se comportan distinto.
 *
 * Antes eran nueve recuentos del mismo tamaño, varios a cero, y mezclaban tres
 * cosas distintas: incumplimiento real, dato que falta y perfil. Había que
 * leerse los nueve para saber si algo iba mal.
 *
 * Ahora **sólo se pinta lo que no está a cero**. Una tarjeta gastada en decir
 * «cero» enseña a ignorar la tira entera, y a la tercera vez ya nadie la mira.
 * Los repartos —qué hay y de qué tipo— se fueron al panel, que es donde se
 * pregunta cómo va la cosa; aquí se viene a trabajar.
 *
 * Lo que sí se conserva es lo mejor que tenía: **cada cifra lleva a su lista**.
 * Un número que no se puede accionar sólo se mira.
 */
const props = withDefaults(defineProps<{
    alertas: Indicador[];
    pendientes: Indicador[];
    /** Sobre cuántas filas se cuenta todo lo de arriba. */
    denominador: number;
    /** Cómo se llaman esas filas: «activos vigentes», «tareas abiertas». */
    denominadorEtiqueta: string;
    /** Los filtros aplicados ahora mismo, para marcar el indicador activo. */
    filtros: Record<string, string | string[]>;
    /**
     * Si el registro tiene alertas por diseño. Mejoras no las tiene —una idea
     * sin hacer no incumple nada—, y sin esto la tira decía «Sin incidencias
     * abiertas» encima de su propia línea de pendientes: un visto bueno que no
     * comprobaba nada.
     */
    conAlertas?: boolean;
}>(), { conAlertas: true });

const tonos: Record<string, string> = {
    caducada: 'text-destructive',
    en_progreso: 'text-estado-en-progreso',
    alta: 'text-primary',
};

const abiertas = computed(() => props.alertas.filter((alerta) => alerta.valor > 0));
const porCompletar = computed(() => props.pendientes.filter((pendiente) => pendiente.valor > 0));
const activos = computed(() => new Set(Object.keys(props.filtros)));

const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();

/*
 * La tira entra escalonada, 40 ms por indicador.
 *
 * Es lo que pide acción HOY, y lo primero que se mira al abrir el módulo: que
 * llegue de izquierda a derecha es lo que hace que se recorra en vez de que se
 * dé por vista. Las cifras ya cuentan con `Cifra`; esto sólo ordena la entrada
 * de las tarjetas, y ocurre al montar, nunca al filtrar.
 */
const escalonado = variantesEscalonado(0.04);
</script>

<template>
    <section v-if="conAlertas || porCompletar.length > 0" :aria-label="`Lo que pide acción: ${denominadorEtiqueta}`">
        <motion.ul
            v-if="abiertas.length > 0"
            class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            :variants="escalonado"
            initial="oculto"
            animate="visible"
        >
            <motion.li v-for="alerta in abiertas" :key="alerta.clave" :variants="variantesEntrada">
                <Link
                    :href="`${alerta.base}?${alerta.filtro}`"
                    class="block h-full rounded-xl border bg-superficie px-3.5 py-3 transition-colors hover:bg-accent"
                    :class="activos.has(alerta.clave) ? 'border-primary ring-1 ring-primary/25' : ''"
                    :title="alerta.ayuda ?? undefined"
                >
                    <span class="flex items-baseline gap-1.5">
                        <Cifra
                            class="text-xl font-bold"
                            :class="tonos[alerta.tono] ?? ''"
                            :valor="alerta.valor"
                        />
                        <!-- El denominador siempre al lado: «2 sin cifrar» sobre
                             4 es una urgencia y sobre 307 es un martes. -->
                        <span class="cifra text-xs text-muted-foreground">de <Cifra :valor="denominador" /></span>
                    </span>
                    <span class="mt-0.5 block text-xs leading-snug text-muted-foreground">
                        {{ alerta.etiqueta }}
                    </span>
                </Link>
            </motion.li>
        </motion.ul>

        <!--
            Sin nada abierto, una línea y no una fila de ceros. Es un estado
            vacío de verdad: dice que se ha mirado y que no hay nada, en vez de
            obligar a comprobar nueve casillas para llegar a la misma conclusión.
        -->
        <p
            v-else-if="conAlertas"
            class="flex items-center gap-2 rounded-xl border border-estado-implantado/40 bg-estado-implantado/5 px-3.5 py-2.5 text-sm text-estado-implantado"
        >
            <CheckCircle2Icon class="size-4 shrink-0" aria-hidden="true" />
            Sin incidencias abiertas sobre
            <span class="cifra font-medium">{{ denominador }}</span>
            {{ denominadorEtiqueta }}.
        </p>

        <!--
            Lo que falta por rellenar va en una línea de texto y no en tarjetas:
            es otra clase de deuda —la ficha está a medias, no hay nada roto— y
            en tarjetas competía en peso con lo que sí arde.

            **La etiqueta se pinta tal cual llega**: es una frase de estado
            completa —«Sin valorar», «Sin propietario»— y la cifra va detrás.
            La plantilla anterior era «falta {etiqueta} en {n}», que sólo
            cuadraba con sustantivos, y doce de los quince registros mandaban
            estados: en /riesgos se leía «falta Sin valorar en 1». Y el rótulo es
            «Por atender» y no «Fichas incompletas», porque no todo lo que llega
            aquí es un dato que falta: «Abiertas» o «Vence en 90 días» también.
        -->
        <p v-if="porCompletar.length > 0" :class="conAlertas && 'mt-2.5'" class="flex flex-wrap gap-x-1.5 gap-y-1 text-xs text-muted-foreground">
            <span>Por atender:</span>
            <template v-for="(pendiente, indice) in porCompletar" :key="pendiente.clave">
                <Link
                    :href="`${pendiente.base}?${pendiente.filtro}`"
                    class="rounded underline-offset-4 hover:underline"
                    :title="pendiente.ayuda ?? undefined"
                >
                    {{ pendiente.etiqueta }}
                    <Cifra class="font-medium text-foreground" :valor="pendiente.valor" />
                </Link>
                <span v-if="indice < porCompletar.length - 1" aria-hidden="true">·</span>
            </template>
        </p>
    </section>
</template>
