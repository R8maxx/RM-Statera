<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ValorFiltro } from '@/composables/useTablaServidor';
import { ChevronDownIcon, XIcon } from '@lucide/vue';
import { ref, watch } from 'vue';

type Filtro = App.Http.Resources.Definicion.Filtro;

const props = defineProps<{
    filtros: Filtro[];
    valores: Record<string, ValorFiltro>;
    hayFiltrosActivos: boolean;
}>();

const emit = defineEmits<{
    aplicar: [clave: string, valor: ValorFiltro];
    limpiar: [];
}>();

/* Los filtros de texto se aplican al dejar de escribir, no en cada tecla. */
const borradores = ref<Record<string, string>>({});
const temporizadores = new Map<string, ReturnType<typeof setTimeout>>();

watch(
    () => props.valores,
    (valores) => {
        for (const filtro of props.filtros) {
            if (filtro.tipo === 'texto') {
                const valor = valores[filtro.clave];
                borradores.value[filtro.clave] = typeof valor === 'string' ? valor : '';
            }
        }
    },
    { immediate: true, deep: true },
);

function escribir(clave: string, valor: string): void {
    borradores.value[clave] = valor;

    clearTimeout(temporizadores.get(clave));
    temporizadores.set(
        clave,
        setTimeout(() => emit('aplicar', clave, valor === '' ? null : valor), 350),
    );
}

function seleccionados(clave: string): string[] {
    const valor = props.valores[clave];

    if (Array.isArray(valor)) {
        return valor;
    }

    return typeof valor === 'string' && valor !== '' ? valor.split(',') : [];
}

function alternar(clave: string, opcion: string): void {
    const actuales = seleccionados(clave);
    const siguientes = actuales.includes(opcion)
        ? actuales.filter((valor) => valor !== opcion)
        : [...actuales, opcion];

    emit('aplicar', clave, siguientes.length === 0 ? null : siguientes);
}

function resumen(filtro: Filtro): string {
    const activos = seleccionados(filtro.clave);

    if (activos.length === 0) {
        return filtro.etiqueta;
    }

    const etiquetas = filtro.opciones
        .filter((opcion) => activos.includes(opcion.valor))
        .map((opcion) => opcion.etiqueta);

    return etiquetas.length <= 2
        ? `${filtro.etiqueta}: ${etiquetas.join(', ')}`
        : `${filtro.etiqueta}: ${etiquetas.length} seleccionados`;
}
</script>

<template>
    <div class="flex flex-wrap items-end gap-2">
        <template v-for="filtro in filtros" :key="filtro.clave">
            <div v-if="filtro.tipo === 'texto'" class="grid gap-1">
                <Label :for="`filtro-${filtro.clave}`" class="text-xs text-muted-foreground">
                    {{ filtro.etiqueta }}
                </Label>
                <Input
                    :id="`filtro-${filtro.clave}`"
                    :model-value="borradores[filtro.clave] ?? ''"
                    :placeholder="filtro.placeholder ?? ''"
                    class="h-8 w-56"
                    type="search"
                    @update:model-value="escribir(filtro.clave, String($event))"
                />
            </div>

            <div v-else-if="filtro.tipo === 'booleano'" class="grid gap-1">
                <span class="text-xs text-muted-foreground">&nbsp;</span>
                <Button
                    :variant="valores[filtro.clave] ? 'secondary' : 'outline'"
                    size="sm"
                    @click="emit('aplicar', filtro.clave, valores[filtro.clave] ? null : '1')"
                >
                    {{ filtro.etiqueta }}
                </Button>
            </div>

            <div v-else-if="filtro.tipo === 'rango_fechas'" class="grid gap-1">
                <Label class="text-xs text-muted-foreground">{{ filtro.etiqueta }}</Label>
                <div class="flex items-center gap-1">
                    <Input
                        type="date"
                        class="h-8 w-36"
                        :model-value="String(valores[filtro.clave] ?? '').split(',')[0] ?? ''"
                        @update:model-value="
                            emit(
                                'aplicar',
                                filtro.clave,
                                `${$event},${String(valores[filtro.clave] ?? '').split(',')[1] ?? ''}`,
                            )
                        "
                    />
                    <span class="text-muted-foreground">–</span>
                    <Input
                        type="date"
                        class="h-8 w-36"
                        :model-value="String(valores[filtro.clave] ?? '').split(',')[1] ?? ''"
                        @update:model-value="
                            emit(
                                'aplicar',
                                filtro.clave,
                                `${String(valores[filtro.clave] ?? '').split(',')[0] ?? ''},${$event}`,
                            )
                        "
                    />
                </div>
            </div>

            <div v-else class="grid gap-1">
                <span class="text-xs text-muted-foreground">&nbsp;</span>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="gap-1">
                            {{ resumen(filtro) }}
                            <ChevronDownIcon class="size-3.5 opacity-60" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="max-h-80 w-64 overflow-y-auto">
                        <DropdownMenuCheckboxItem
                            v-for="opcion in filtro.opciones"
                            :key="opcion.valor"
                            :model-value="seleccionados(filtro.clave).includes(opcion.valor)"
                            @select="(evento: Event) => evento.preventDefault()"
                            @update:model-value="
                                filtro.multiple
                                    ? alternar(filtro.clave, opcion.valor)
                                    : emit(
                                          'aplicar',
                                          filtro.clave,
                                          seleccionados(filtro.clave).includes(opcion.valor) ? null : opcion.valor,
                                      )
                            "
                        >
                            {{ opcion.etiqueta }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </template>

        <Button v-if="hayFiltrosActivos" variant="ghost" size="sm" class="gap-1" @click="emit('limpiar')">
            <XIcon class="size-3.5" />
            Limpiar
        </Button>
    </div>
</template>
