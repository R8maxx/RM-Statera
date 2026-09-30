<script setup lang="ts">
import CampoBase from '@/components/formulario/CampoBase.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import type { Opcion } from '@/lib/formularios';
import { tono } from '@/lib/tonos';
import { CheckIcon, XIcon } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref } from 'vue';

/**
 * Varios valores de una lista larga, con búsqueda. Viaja como `nombre[]`, igual
 * que `CampoCasillas`.
 *
 * **Para lo que decide el inventario, no el dominio.** Cinco dimensiones o tres
 * sistemas caben en casillas y se ven de golpe; doscientos activos en una caja
 * con desplazamiento no se encuentran. Aquí lo elegido sube arriba como chips y
 * lo demás se busca por código o por nombre, sin tildes ni mayúsculas, con la
 * coincidencia resaltada como en la tabla (DESIGN.md §9).
 *
 * **El contrato con el envío es el de `CampoCasillas`**: un campo oculto por
 * valor, y un `nombre[]` vacío cuando no hay ninguno, para que el servidor
 * distinga «ninguno» de «no venía el campo». Sin eso, quitar el último chip no
 * desvincularía nada.
 *
 * La búsqueda se queda al elegir —para marcar tres servidores seguidos sin
 * reescribir «servidor»— y se vacía al salir del campo: texto suelto en la
 * caja se leería como un valor.
 *
 * **Cada opción puede traer su tono, su icono y una descripción**, y con los
 * activos traen los de su tipo MAGERIT (`TipoActivo::tono()`): el chip se pinta
 * con el badge de `--tipo-*` y su icono, igual que la columna «Tipo» de la
 * tabla, y la lista dice el tipo en texto a la derecha. Color, icono y texto,
 * los tres (DESIGN.md §3): el color sólo agrupa —la peor pareja de tipos queda
 * en ΔE 5.2— y la identidad la carga el icono. Sin tono, el chip es `muted`.
 *
 * El filtrado es propio (`ignore-filter`): el de Reka compara la etiqueta
 * entera y no sabe de tildes, y resaltar exige saber dónde coincidió.
 */
export interface OpcionTipada extends Opcion {
    /** El tono de `lib/tonos.ts` —`tipo:software`—; lo decide el servidor. */
    tono?: string;
    /** El icono de `IconoTipo`, por nombre. */
    icono?: string;
    /** Una línea de texto a la derecha en la lista: el tipo, dicho. */
    descripcion?: string;
}

const props = defineProps<{
    nombre: string;
    etiqueta: string;
    opciones: OpcionTipada[];
    error?: string | string[] | null;
    ayuda?: string;
    requerido?: boolean;
    placeholder?: string;
    vacio?: string;
}>();

const modelo = defineModel<string[]>({ default: () => [] });
const busqueda = ref('');

/** Cada carácter a su base, uno a uno: así el índice del normalizado vale en el original. */
function normalizar(texto: string): string {
    return [...texto].map((caracter) => caracter.normalize('NFD').charAt(0).toLowerCase()).join('');
}

const porValor = computed(() => new Map(props.opciones.map((opcion) => [opcion.valor, opcion])));

const elegidas = computed(() =>
    modelo.value.map((valor): OpcionTipada => porValor.value.get(valor) ?? { valor, etiqueta: valor }),
);

interface Fragmento {
    texto: string;
    coincide: boolean;
}

const filtradas = computed(() => {
    const termino = normalizar(busqueda.value.trim());

    return props.opciones.flatMap((opcion) => {
        if (termino === '') {
            return [{ ...opcion, fragmentos: [{ texto: opcion.etiqueta, coincide: false }] as Fragmento[] }];
        }

        const posicion = normalizar(opcion.etiqueta).indexOf(termino);

        if (posicion === -1) {
            return [];
        }

        const caracteres = [...opcion.etiqueta];
        const fin = posicion + [...termino].length;

        return [
            {
                ...opcion,
                fragmentos: [
                    { texto: caracteres.slice(0, posicion).join(''), coincide: false },
                    { texto: caracteres.slice(posicion, fin).join(''), coincide: true },
                    { texto: caracteres.slice(fin).join(''), coincide: false },
                ].filter((fragmento) => fragmento.texto !== ''),
            },
        ];
    });
});

function quitar(valor: string): void {
    modelo.value = modelo.value.filter((elegido) => elegido !== valor);
}

/** Retroceso con la búsqueda vacía quita el último, como en cualquier campo de etiquetas. */
function alRetroceder(): void {
    if (busqueda.value === '' && modelo.value.length > 0) {
        modelo.value = modelo.value.slice(0, -1);
    }
}
</script>

<template>
    <CampoBase
        :nombre="nombre"
        :etiqueta="etiqueta"
        :error="error"
        :ayuda="ayuda"
        :requerido="requerido"
        #default="{ atributos }"
    >
        <input v-for="valor in modelo" :key="valor" type="hidden" :name="`${nombre}[]`" :value="valor" />
        <input v-if="modelo.length === 0" type="hidden" :name="`${nombre}[]`" value="" />

        <p v-if="opciones.length === 0" class="text-sm text-muted-foreground">
            {{ vacio ?? 'No hay ninguna opción todavía.' }}
        </p>

        <ComboboxRoot
            v-else
            v-model="modelo"
            multiple
            ignore-filter
            open-on-click
            :reset-search-term-on-select="false"
        >
            <ComboboxAnchor
                class="flex min-h-9 w-full flex-wrap items-center gap-1.5 rounded-md border border-input bg-transparent px-2 py-1 shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 has-[[aria-invalid=true]]:border-destructive has-[[aria-invalid=true]]:ring-3 has-[[aria-invalid=true]]:ring-destructive/20 dark:bg-input/30"
            >
                <span
                    v-for="elegida in elegidas"
                    :key="elegida.valor"
                    class="inline-flex h-6 max-w-full items-center gap-1 rounded-full pr-1 text-xs font-medium"
                    :class="[
                        elegida.tono ? tono(elegida.tono).badge : 'bg-muted text-secondary-foreground',
                        elegida.icono ? 'pl-2' : 'pl-2.5',
                    ]"
                >
                    <IconoTipo :nombre="elegida.icono" clase="size-3.5 shrink-0" />
                    <span class="truncate">{{ elegida.etiqueta }}</span>
                    <span v-if="elegida.descripcion" class="sr-only">, {{ elegida.descripcion }}</span>
                    <button
                        type="button"
                        class="grid size-4 shrink-0 place-items-center rounded-full opacity-70 transition-[opacity,background-color] hover:bg-foreground/10 hover:opacity-100"
                        :aria-label="`Quitar ${elegida.etiqueta}`"
                        @click="quitar(elegida.valor)"
                    >
                        <XIcon class="size-3" aria-hidden="true" />
                    </button>
                </span>

                <ComboboxInput
                    v-model="busqueda"
                    v-bind="atributos"
                    class="h-7 min-w-32 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    :placeholder="placeholder ?? 'Buscar por código o nombre'"
                    @keydown.backspace="alRetroceder"
                />
            </ComboboxAnchor>

            <ComboboxPortal>
                <ComboboxContent
                    position="popper"
                    :side-offset="4"
                    class="z-(--z-superposicion) max-h-72 w-(--reka-combobox-trigger-width) overflow-y-auto rounded-xl bg-popover p-1 text-popover-foreground shadow-sombra-2 ring-1 ring-foreground/10"
                >
                    <ComboboxViewport>
                        <ComboboxEmpty class="px-2 py-3 text-sm text-muted-foreground">
                            Nada coincide con «{{ busqueda }}».
                        </ComboboxEmpty>

                        <ComboboxItem
                            v-for="opcion in filtradas"
                            :key="opcion.valor"
                            :value="opcion.valor"
                            class="relative flex cursor-default items-center gap-2 rounded-md py-1.5 pr-8 pl-2 text-sm outline-hidden select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                        >
                            <span
                                v-if="opcion.icono"
                                class="grid size-5 shrink-0 place-items-center rounded-md"
                                :class="opcion.tono ? tono(opcion.tono).badge : 'bg-muted text-muted-foreground'"
                            >
                                <IconoTipo :nombre="opcion.icono" clase="size-3.5" />
                            </span>
                            <span class="min-w-0 flex-1 truncate">
                                <template v-for="(fragmento, indice) in opcion.fragmentos" :key="indice">
                                    <mark v-if="fragmento.coincide" class="rounded-[3px] bg-primary/20 text-inherit">{{
                                        fragmento.texto
                                    }}</mark>
                                    <template v-else>{{ fragmento.texto }}</template>
                                </template>
                            </span>
                            <span v-if="opcion.descripcion" class="shrink-0 text-xs text-muted-foreground">
                                {{ opcion.descripcion }}
                            </span>
                            <ComboboxItemIndicator class="absolute right-2 flex size-4 items-center justify-center">
                                <CheckIcon class="size-4" aria-hidden="true" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>
    </CampoBase>
</template>
