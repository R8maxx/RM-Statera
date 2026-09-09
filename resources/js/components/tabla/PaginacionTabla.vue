<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ChevronLeftIcon, ChevronRightIcon, ChevronsLeftIcon, ChevronsRightIcon } from '@lucide/vue';

type MetaTabla = App.Http.Resources.Definicion.MetaTabla;

/**
 * La paginación, que es de servidor.
 *
 * El tamaño de página pasa de un menú desplegable a un `Select`: elegir un
 * valor de una lista cerrada es lo que hace un select, y así llega también el
 * teclado y el estado marcado, que el menú no daba.
 */
defineProps<{ meta: MetaTabla; tamanos: number[] }>();

const emit = defineEmits<{ pagina: [numero: number]; tamano: [numero: number] }>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t px-3 py-2.5 text-sm">
        <!-- Filtrar no mueve el foco, así que sin `aria-live` nadie que use
             lector de pantalla se entera de cuántas filas quedan. -->
        <p class="text-muted-foreground" aria-live="polite">
            <template v-if="meta.total > 0">
                <span class="cifra text-foreground">{{ meta.desde }}-{{ meta.hasta }}</span>
                de
                <span class="cifra text-foreground">{{ meta.total }}</span>
            </template>
            <template v-else>Sin resultados</template>
        </p>

        <div class="flex items-center gap-3">
            <Select
                :model-value="String(meta.porPagina)"
                @update:model-value="emit('tamano', Number($event))"
            >
                <SelectTrigger size="sm" class="w-[8.5rem]" aria-label="Filas por página">
                    <SelectValue>
                        <span class="cifra">{{ meta.porPagina }}</span>
                        <span class="ml-1 text-muted-foreground">por página</span>
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="tamano in tamanos" :key="tamano" :value="String(tamano)">
                        {{ tamano }} por página
                    </SelectItem>
                </SelectContent>
            </Select>

            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Primera página"
                    :disabled="meta.pagina <= 1"
                    @click="emit('pagina', 1)"
                >
                    <ChevronsLeftIcon />
                </Button>
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Página anterior"
                    :disabled="meta.pagina <= 1"
                    @click="emit('pagina', meta.pagina - 1)"
                >
                    <ChevronLeftIcon />
                </Button>

                <span class="cifra px-2 text-xs text-muted-foreground">
                    {{ meta.pagina }} / {{ Math.max(meta.ultimaPagina, 1) }}
                </span>

                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Página siguiente"
                    :disabled="meta.pagina >= meta.ultimaPagina"
                    @click="emit('pagina', meta.pagina + 1)"
                >
                    <ChevronRightIcon />
                </Button>
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Última página"
                    :disabled="meta.pagina >= meta.ultimaPagina"
                    @click="emit('pagina', meta.ultimaPagina)"
                >
                    <ChevronsRightIcon />
                </Button>
            </div>
        </div>
    </div>
</template>
