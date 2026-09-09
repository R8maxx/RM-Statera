<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Columns3Icon, RotateCcwIcon } from '@lucide/vue';

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

defineProps<{ columnas: ColumnaConVisibilidad[]; ocultas: number }>();

const emit = defineEmits<{ restablecer: [] }>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="h-9 gap-1.5">
                <Columns3Icon class="size-3.5" />
                Columnas
                <span
                    v-if="ocultas > 0"
                    class="cifra rounded-full bg-muted px-1.5 text-[10px] text-muted-foreground"
                >
                    {{ ocultas }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel>Columnas visibles</DropdownMenuLabel>
            <DropdownMenuSeparator />

            <div class="max-h-80 overflow-y-auto">
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
            </div>

            <DropdownMenuSeparator />
            <!-- La vista se guarda en el navegador, así que hace falta una
                 puerta de vuelta: sin esto, una columna oculta hace meses sigue
                 oculta y no hay forma de saber por qué. -->
            <DropdownMenuItem @select="emit('restablecer')">
                <RotateCcwIcon class="size-4" />
                Restablecer la vista
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
