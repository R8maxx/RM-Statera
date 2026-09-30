<script setup lang="ts">
import { usarSeccionObligatorios } from '@/composables/useCamposObligatorios';
import { useDesplegable } from '@/composables/useDesplegable';
import { ChevronRightIcon } from '@lucide/vue';
import { registrarEnIndice } from '@/composables/useIndiceFormulario';
import { computed, onMounted, ref, useId } from 'vue';

/**
 * Una sección de un formulario, con su explicación encima.
 *
 * En cumplimiento, la mitad de los campos no se entienden por su etiqueta: nadie
 * sabe qué espera «exclusiones justificadas» hasta que alguien le dice que es lo
 * primero que un auditor rechaza cuando está vacío. Esa explicación vive aquí,
 * bajo el título y permanente, en vez de en un texto de ayuda de once píxeles
 * debajo de cada campo.
 *
 * **Encima y no a la izquierda.** Estuvo en una columna de 16 rem al lado de
 * los campos; con el carril de `FormularioRecurso` a la derecha, eran tres
 * columnas y los campos se quedaban con la mitad del ancho. Encima, la
 * explicación se lee antes que los campos, que es cuando sirve.
 *
 * **Se da de alta en el índice del carril** (`useIndiceFormulario`) con su
 * título, su recuento y su elemento; el `id` es el ancla a la que salta.
 *
 * **Lo que falta se dice aquí y sólo cuando falta.** Con la sección completa no
 * se pinta nada: una palomita por sección es la fila de ceros del inventario
 * otra vez, ruido que se deja de mirar a la semana. Y el recuento vive en la
 * cabecera, **fuera de lo que se pliega**, para que plegar una sección no
 * esconda que todavía debe dos campos.
 *
 * **Plegar no es `v-if` ni el `Collapsible` de Reka.** Los campos tienen que
 * seguir en el DOM —lo que sale del DOM sale del `FormData`, y como son
 * `nullable` en el `FormRequest` una edición los borraría en silencio—. Con
 * `forceMount` de Reka el contenido se queda montado pero **visible**, que es
 * justo lo que no hace falta.
 *
 * **Y tampoco es ya `v-show`.** El chevron rotaba con transición y el contenido
 * aparecía de golpe, que es peor que no animar nada: el mando se mueve y lo
 * mandado no. Ahora es `.desplegable` (una rejilla de `0fr` a `1fr`, en
 * `app.css`), que anima una altura que nadie ha medido y deja el contenido
 * donde estaba. `display:none` tampoco excluía un campo del envío —`FormData`
 * sólo se salta los deshabilitados y los que no tienen `name`—, así que el
 * razonamiento de arriba sigue intacto.
 *
 * Lo que `display:none` sí resolvía **por accidente** era el foco: a altura cero
 * el contenido sigue siendo tabulable, y tabular hasta un campo que no se ve es
 * peor que no poder llegar a él. De ahí el `inert`, que hay que poner a mano.
 */
const props = withDefaults(
    defineProps<{
        titulo: string;
        ayuda?: string;
        /** Deja plegar la sección desde su título. El contenido se envía igual. */
        plegable?: boolean;
        /** Arranca plegada. Sólo se mira al montar; después manda el usuario. */
        plegadaPorDefecto?: boolean;
    }>(),
    { plegable: false, plegadaPorDefecto: false },
);

const pendientes = usarSeccionObligatorios();

const idContenido = `seccion-${useId()}`;
const abierta = ref(!props.plegable || !props.plegadaPorDefecto);
const { asentada, alTerminarTransicion } = useDesplegable(abierta);

const textoPendientes = computed(() =>
    pendientes.value === 1 ? '1 sin rellenar' : `${pendientes.value} sin rellenar`,
);

const idSeccion = `seccion-${useId()}-ancla`;
const elemento = ref<HTMLElement | null>(null);
const registrar = registrarEnIndice();

onMounted(() =>
    registrar({
        id: idSeccion,
        titulo: props.titulo,
        pendientes,
        elemento,
        abrir: () => {
            abierta.value = true;
        },
    }),
);
</script>

<template>
    <!--
        `scroll-mt-24`: el índice salta aquí, y sin margen el título quedaba
        debajo de la cabecera pegajosa de la aplicación (64 px).
    -->
    <section
        :id="idSeccion"
        ref="elemento"
        tabindex="-1"
        class="grid scroll-mt-24 outline-none gap-y-5 border-t pt-6 first:border-t-0 first:pt-0"
        :data-plegable="plegable ? '' : undefined"
    >
        <div>
            <div class="flex items-baseline justify-between gap-3">
                <!--
                    El título es el mando: es donde se mira y donde se pulsa. Un
                    enlace aparte encima de los campos obligaba a buscarlo.
                    `data-plegar` es el asidero de `irAlCampo`, que tiene que
                    poder abrir la sección donde está el campo que falla.
                -->
                <button
                    v-if="plegable"
                    type="button"
                    data-plegar
                    class="group -ml-1 flex items-center gap-1.5 rounded px-1 text-left transition-colors hover:text-primary"
                    :aria-expanded="abierta"
                    :aria-controls="idContenido"
                    @click="abierta = !abierta"
                >
                    <ChevronRightIcon
                        class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:text-primary"
                        :class="abierta ? 'rotate-90' : undefined"
                    />
                    <h2 class="text-base font-semibold tracking-[-0.01em]">{{ titulo }}</h2>
                </button>

                <h2 v-else class="text-base font-semibold tracking-[-0.01em]">{{ titulo }}</h2>

                <p v-if="pendientes > 0" class="shrink-0 text-xs text-muted-foreground">
                    {{ textoPendientes }}
                </p>
            </div>

            <p
                v-if="ayuda"
                class="mt-1 max-w-2xl text-sm text-pretty text-muted-foreground"
                :class="plegable ? 'pl-6' : undefined"
            >
                {{ ayuda }}
            </p>
        </div>

        <div
            :id="idContenido"
            class="desplegable"
            :data-abierto="abierta ? '' : undefined"
            :data-asentado="asentada ? '' : undefined"
            :inert="!abierta"
            @transitionend="alTerminarTransicion"
        >
            <div class="grid content-start gap-5">
                <slot />
            </div>
        </div>
    </section>
</template>
