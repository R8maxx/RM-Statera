<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Columns3Icon } from '@lucide/vue';

/**
 * La visibilidad de columnas la lleva TanStack; aquí sólo se pinta.
 *
 * El tipo es estructural a propósito: los genéricos de `Column` dependen del
 * juego de características de cada tabla, y atarlos aquí obligaría a este
 * componente a conocerlo.
 */
interface ColumnaConVisibilidad {
    id: string;
    columnDef: { header?: unknown };
    getIsVisible: () => boolean;
    getCanHide: () => boolean;
    toggleVisibility: (visible: boolean) => void;
}

defineProps<{ columnas: ColumnaConVisibilidad[] }>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="gap-1">
                <Columns3Icon class="size-3.5" />
                Columnas
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="max-h-96 w-56 overflow-y-auto">
            <DropdownMenuLabel>Columnas visibles</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuCheckboxItem
                v-for="columna in columnas"
                :key="columna.id"
                :model-value="columna.getIsVisible()"
                :disabled="!columna.getCanHide()"
                @select="(evento: Event) => evento.preventDefault()"
                @update:model-value="columna.toggleVisibility(!columna.getIsVisible())"
            >
                {{ String(columna.columnDef.header ?? columna.id) }}
            </DropdownMenuCheckboxItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
