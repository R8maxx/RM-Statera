<script setup lang="ts">
import { usarSeccionObligatorios } from '@/composables/useCamposObligatorios';
import { ChevronRightIcon } from '@lucide/vue';
import { computed, ref, useId } from 'vue';

/**
 * Una sección de un formulario, con su explicación al lado.
 *
 * En cumplimiento, la mitad de los campos no se entienden por su etiqueta: nadie
 * sabe qué espera «exclusiones justificadas» hasta que alguien le dice que es lo
 * primero que un auditor rechaza cuando está vacío. Esa explicación vive aquí, a
 * la izquierda y permanente, en vez de en un texto de ayuda de once píxeles
 * debajo de cada campo.
 *
 * Por debajo de `lg` se apila: la explicación arriba y los campos debajo.
 *
 * **Lo que falta se dice aquí y sólo cuando falta.** Con la sección completa no
 * se pinta nada: una palomita por sección es la fila de ceros del inventario
 * otra vez, ruido que se deja de mirar a la semana. Y el recuento vive en la
 * cabecera, **fuera de lo que se pliega**, para que plegar una sección no
 * esconda que todavía debe dos campos.
 *
 * **Plegar es `v-show`, no `v-if` ni el `Collapsible` de Reka.** Los campos
 * tienen que seguir en el DOM —lo que sale del DOM sale del `FormData`, y como
 * son `nullable` en el `FormRequest` una edición los borraría en silencio—, y
 * `display:none` no excluye un campo del envío: `FormData` sólo se salta los
 * deshabilitados y los que no tienen `name`. Con `forceMount` de Reka el
 * contenido se queda montado pero **visible**, que es justo lo que no hace
 * falta.
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

const textoPendientes = computed(() =>
    pendientes.value === 1 ? '1 sin rellenar' : `${pendientes.value} sin rellenar`,
);
</script>

<template>
    <section
        class="grid gap-x-8 gap-y-4 border-t pt-6 first:border-t-0 first:pt-0 lg:grid-cols-[15rem_1fr]"
        :data-plegable="plegable ? '' : undefined"
    >
        <div class="lg:pt-0.5">
            <div class="flex items-baseline justify-between gap-3 lg:flex-col lg:items-start lg:gap-1">
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
                    class="group -ml-1 flex items-center gap-1 rounded px-1 text-left text-sm font-medium transition-colors hover:text-primary"
                    :aria-expanded="abierta"
                    :aria-controls="idContenido"
                    @click="abierta = !abierta"
                >
                    <ChevronRightIcon
                        class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:text-primary"
                        :class="abierta ? 'rotate-90' : undefined"
                    />
                    <h2>{{ titulo }}</h2>
                </button>

                <h2 v-else class="text-sm font-medium">{{ titulo }}</h2>

                <p v-if="pendientes > 0" class="shrink-0 text-xs text-muted-foreground">
                    {{ textoPendientes }}
                </p>
            </div>

            <p v-if="ayuda" class="mt-1.5 text-sm text-muted-foreground" :class="plegable ? 'pl-4' : undefined">
                {{ ayuda }}
            </p>
        </div>

        <div :id="idContenido" v-show="abierta" class="grid content-start gap-5">
            <slot />
        </div>
    </section>
</template>
