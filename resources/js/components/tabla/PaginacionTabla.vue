<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ChevronLeftIcon, ChevronRightIcon, ChevronsLeftIcon, ChevronsRightIcon } from '@lucide/vue';

type MetaTabla = App.Http.Resources.Definicion.MetaTabla;

const props = defineProps<{ meta: MetaTabla; tamanos: number[] }>();

const emit = defineEmits<{ pagina: [numero: number]; tamano: [numero: number] }>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t px-3 py-2 text-sm">
        <p class="text-muted-foreground">
            <template v-if="meta.total > 0">
                {{ meta.desde }}–{{ meta.hasta }} de {{ meta.total }}
            </template>
            <template v-else>Sin resultados</template>
        </p>

        <div class="flex items-center gap-3">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" size="sm">{{ meta.porPagina }} por página</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem v-for="tamano in tamanos" :key="tamano" @select="emit('tamano', tamano)">
                        {{ tamano }} por página
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

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

                <span class="px-2 text-muted-foreground tabular-nums">
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
