<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ArrowDownAZIcon,
    ArrowUpAZIcon,
    ChevronsLeftIcon,
    ChevronsRightIcon,
    EllipsisVerticalIcon,
    EyeOffIcon,
    PinIcon,
    PinOffIcon,
} from '@lucide/vue';

type Columna = App.Http.Resources.Definicion.Columna;

/**
 * Lo que se puede hacer con una columna, desde su propia cabecera.
 *
 * Ordenar ya estaba en el clic del título; lo que faltaba era todo lo demás
 * —anclar, mover, ocultar—, que vivía en un desplegable al otro lado de la
 * pantalla o directamente no existía. Con doce columnas, la que estorba se
 * quita desde donde está, no buscándola en una lista.
 */
defineProps<{
    columna: Columna;
    /** El sentido del orden aplicado, o `null` si ordena otra columna. */
    sentido: 'asc' | 'desc' | null;
    anclada: boolean;
    puedeAnclar: boolean;
    puedeOcultar: boolean;
    puedeMover: { izquierda: boolean; derecha: boolean };
}>();

const emit = defineEmits<{
    ordenar: [descendente: boolean];
    anclar: [anclada: boolean];
    ocultar: [];
    mover: [paso: -1 | 1];
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon-xs"
                class="opacity-0 transition-opacity group-hover/columna:opacity-100 focus-visible:opacity-100 aria-expanded:opacity-100"
                :aria-label="`Opciones de la columna ${columna.etiqueta}`"
            >
                <EllipsisVerticalIcon />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" class="w-52">
            <template v-if="columna.ordenable">
                <DropdownMenuItem :disabled="sentido === 'asc'" @select="emit('ordenar', false)">
                    <ArrowUpAZIcon class="size-4" />
                    Ascendente
                </DropdownMenuItem>
                <DropdownMenuItem :disabled="sentido === 'desc'" @select="emit('ordenar', true)">
                    <ArrowDownAZIcon class="size-4" />
                    Descendente
                </DropdownMenuItem>
                <DropdownMenuSeparator />
            </template>

            <DropdownMenuItem v-if="puedeAnclar" @select="emit('anclar', !anclada)">
                <component :is="anclada ? PinOffIcon : PinIcon" class="size-4" />
                {{ anclada ? 'Desanclar' : 'Anclar al inicio' }}
            </DropdownMenuItem>

            <DropdownMenuItem :disabled="!puedeMover.izquierda" @select="emit('mover', -1)">
                <ChevronsLeftIcon class="size-4" />
                Mover a la izquierda
            </DropdownMenuItem>
            <DropdownMenuItem :disabled="!puedeMover.derecha" @select="emit('mover', 1)">
                <ChevronsRightIcon class="size-4" />
                Mover a la derecha
            </DropdownMenuItem>

            <template v-if="puedeOcultar">
                <DropdownMenuSeparator />
                <DropdownMenuItem @select="emit('ocultar')">
                    <EyeOffIcon class="size-4" />
                    Ocultar la columna
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
