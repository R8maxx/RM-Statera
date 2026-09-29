<script setup lang="ts">
import { useDesplegable } from '@/composables/useDesplegable';
import { ChevronRightIcon, type LucideIcon } from '@lucide/vue';
import { computed, useId } from 'vue';

/**
 * Un grupo del sidebar desplegado, plegable desde su título.
 *
 * Con treinta módulos el sidebar no cabía en una pantalla de portátil, y quien
 * trabaja en dos o tres tenía que desplazarse por los otros veintisiete cada
 * vez. Así que los grupos salen plegados y **se abre uno cada vez**: el de la
 * pantalla actual al llegar, y el que se pulse después. Quién está abierto lo
 * decide quien lo usa, que sabe cuál es la ruta.
 *
 * El grupo de la pantalla actual **sí se puede plegar**, y no se pierde: plegado
 * lleva un punto en el icono y el nombre de la pantalla a la derecha, donde los
 * demás dicen cuántos módulos guardan. Así la fila sigue diciendo dónde estás.
 *
 * El riel —el sidebar plegado a iconos— no pasa por aquí: allí cada grupo es un
 * botón con su menú flotante, y lo pinta `AppLayout`.
 */
const props = defineProps<{
    titulo: string;
    icono: LucideIcon;
    abierto: boolean;
    /** Cuántos módulos hay dentro, para la fila plegada. */
    cantidad: number;
    /** El título de la entrada activa, si la pantalla actual es de este grupo. */
    actual?: string;
}>();

const emit = defineEmits<{ alternar: [] }>();

const id = useId();
const visible = computed(() => props.abierto);
const { asentada, alTerminarTransicion } = useDesplegable(visible);
</script>

<template>
    <div>
        <button
            type="button"
            class="group flex h-9 w-full items-center gap-2.5 rounded-md px-3 text-left text-sm font-medium transition-colors hover:bg-muted"
            :class="actual ? 'text-foreground' : 'text-secondary-foreground'"
            :aria-expanded="abierto"
            :aria-controls="id"
            @click="emit('alternar')"
        >
            <span class="relative flex shrink-0">
                <component :is="icono" class="size-4" :class="actual ? 'text-primary' : 'text-muted-foreground'" />
                <!-- El punto de «estás aquí dentro», sólo con el grupo plegado:
                     desplegado ya lo dice la entrada activa. -->
                <span
                    v-if="actual"
                    class="absolute -top-0.5 -right-1 size-1.5 rounded-full bg-primary ring-2 ring-superficie transition-[opacity,scale] duration-(--duracion) ease-marca"
                    :class="abierto ? 'scale-50 opacity-0' : 'scale-100 opacity-100'"
                    aria-hidden="true"
                />
            </span>

            <span class="flex-1 truncate" :class="actual && 'font-semibold'">{{ titulo }}</span>

            <Transition name="rotulo-grupo" mode="out-in">
                <span v-if="abierto" key="nada" />
                <span v-else-if="actual" key="actual" class="max-w-[6.5rem] truncate text-xs font-medium text-primary">
                    {{ actual }}
                </span>
                <span v-else key="cantidad" class="cifra text-xs text-muted-foreground">{{ cantidad }}</span>
            </Transition>

            <ChevronRightIcon
                class="size-3.5 shrink-0 text-muted-foreground transition-transform duration-(--duracion) ease-marca"
                :class="abierto && 'rotate-90'"
                aria-hidden="true"
            />
        </button>

        <!--
            La salida más corta que la entrada (DESIGN.md §10): al plegar ya se ha
            decidido y sólo falta que se quite de en medio.
        -->
        <div
            :id="id"
            class="desplegable [&:not([data-abierto])]:duration-(--duracion-salida)"
            :data-abierto="visible ? '' : undefined"
            :data-asentado="asentada ? '' : undefined"
            :inert="!visible"
            @transitionend="alTerminarTransicion"
        >
            <div>
                <div class="entradas-grupo mt-0.5 mb-1.5 ml-[1.125rem] space-y-0.5 border-l pl-2" :data-abierto="visible ? '' : undefined">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/*
 * Las entradas llegan escalonadas mientras la rejilla crece: bajan 4 px —la mitad
 * de una entrada de página, porque aquí la caja ya se está moviendo— y pasan de
 * transparente a opaco, 25 ms una detrás de otra. Seis entradas son 125 ms de
 * escalón más los 220 de la entrada: dentro de los 380 que §10 da a un despliegue.
 * Al plegar se van todas a la vez y en la duración de salida, sin escalón: lo
 * que se va no se presenta.
 */
.entradas-grupo > :slotted(*) {
    opacity: 0;
    translate: 0 -4px;
    transition:
        opacity var(--duracion-salida) var(--curva),
        translate var(--duracion-salida) var(--curva),
        background-color var(--duracion-rapida) var(--curva),
        color var(--duracion-rapida) var(--curva);
}

.entradas-grupo[data-abierto] > :slotted(*) {
    opacity: 1;
    translate: 0 0;
    transition:
        opacity var(--duracion) var(--curva) calc(var(--escalon, 0) * 25ms),
        translate var(--duracion) var(--curva) calc(var(--escalon, 0) * 25ms),
        background-color var(--duracion-rapida) var(--curva),
        color var(--duracion-rapida) var(--curva);
}

.entradas-grupo > :slotted(:nth-child(2)) { --escalon: 1; }
.entradas-grupo > :slotted(:nth-child(3)) { --escalon: 2; }
.entradas-grupo > :slotted(:nth-child(4)) { --escalon: 3; }
.entradas-grupo > :slotted(:nth-child(5)) { --escalon: 4; }
.entradas-grupo > :slotted(:nth-child(n + 6)) { --escalon: 5; }

.rotulo-grupo-enter-active {
    transition: opacity var(--duracion-rapida) var(--curva);
}

.rotulo-grupo-leave-active {
    transition: opacity var(--duracion-rapida) var(--curva);
}

.rotulo-grupo-enter-from,
.rotulo-grupo-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .entradas-grupo > :slotted(*) {
        translate: none;
        transition-delay: 0ms !important;
    }
}
</style>
