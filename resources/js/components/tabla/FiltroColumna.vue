<script setup lang="ts">
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { ValorFiltro } from '@/composables/useTablaServidor';
import { estaActivo, rango, resumenFiltro, seleccionados } from '@/lib/filtros';
import { cn } from '@/lib/utils';
import { CheckIcon, ChevronDownIcon, SearchIcon, XIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

type Filtro = App.Http.Resources.Definicion.Filtro;
type Opcion = App.Http.Resources.Definicion.Opcion;

/**
 * El control de un filtro, sea cual sea su tipo.
 *
 * Se pinta en dos sitios con el mismo código: en la fila de filtros de la
 * cabecera, debajo de la columna que estrecha, y en el panel «Filtros» de la
 * barra para los que no tienen columna visible. Que sea el mismo componente es
 * lo que evita que el mismo filtro se comporte distinto según dónde se abra.
 */
const props = withDefaults(
    defineProps<{
        filtro: Filtro;
        valor: ValorFiltro;
        /** `columna` va dentro de un `<th>`; `panel`, apilado en el desplegable. */
        variante?: 'columna' | 'panel';
    }>(),
    { variante: 'columna' },
);

const emit = defineEmits<{ aplicar: [clave: string, valor: ValorFiltro] }>();

const abierto = ref(false);
const busquedaOpciones = ref('');

const activo = computed(() => estaActivo(props.valor));
const resumen = computed(() => resumenFiltro(props.filtro, props.valor));
const marcados = computed(() => seleccionados(props.valor));
const extremos = computed(() => rango(props.valor));

/* Un booleano es una lista de dos opciones; pintarlo aparte no aportaba nada. */
const opciones = computed<Opcion[]>(() =>
    props.filtro.tipo === 'booleano'
        ? [
              { valor: '1', etiqueta: 'Sí' },
              { valor: '0', etiqueta: 'No' },
          ]
        : props.filtro.opciones,
);

/* Con veinte sistemas en la lista, buscar es más rápido que recorrerla. */
const listable = computed(() => opciones.value.length > 8);

const visibles = computed(() => {
    const termino = busquedaOpciones.value.trim().toLowerCase();

    return termino === ''
        ? opciones.value
        : opciones.value.filter((opcion) => opcion.etiqueta.toLowerCase().includes(termino));
});

/* El texto se aplica al dejar de escribir, no en cada tecla. */
const borrador = ref('');
let temporizador: ReturnType<typeof setTimeout> | undefined;

watch(
    () => props.valor,
    (valor) => {
        if (props.filtro.tipo === 'texto' || props.filtro.tipo === 'busqueda') {
            borrador.value = typeof valor === 'string' ? valor : '';
        }
    },
    { immediate: true },
);

watch(abierto, (esta) => {
    if (!esta) {
        busquedaOpciones.value = '';
    }
});

onBeforeUnmount(() => clearTimeout(temporizador));

function escribir(valor: string): void {
    borrador.value = valor;

    clearTimeout(temporizador);
    temporizador = setTimeout(() => emit('aplicar', props.filtro.clave, valor === '' ? null : valor), 350);
}

function limpiar(): void {
    clearTimeout(temporizador);
    borrador.value = '';
    emit('aplicar', props.filtro.clave, null);
}

function elegir(opcion: string): void {
    if (props.filtro.multiple) {
        const siguientes = marcados.value.includes(opcion)
            ? marcados.value.filter((valor) => valor !== opcion)
            : [...marcados.value, opcion];

        emit('aplicar', props.filtro.clave, siguientes.length === 0 ? null : siguientes);

        return;
    }

    emit('aplicar', props.filtro.clave, marcados.value.includes(opcion) ? null : opcion);
    abierto.value = false;
}

function fijarExtremo(indice: 0 | 1, valor: string): void {
    const partes = [...extremos.value] as [string, string];
    partes[indice] = valor;

    emit('aplicar', props.filtro.clave, partes[0] === '' && partes[1] === '' ? null : partes.join(','));
}

const alto = computed(() => (props.variante === 'panel' ? 'h-9' : 'h-8'));

const disparador = computed(() =>
    cn(
        'flex w-full min-w-0 items-center gap-1 rounded-md border px-2 text-left text-xs transition-colors outline-none',
        'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
        alto.value,
        activo.value
            ? 'border-primary/40 bg-accent font-medium text-accent-foreground'
            : 'border-input bg-background text-muted-foreground hover:bg-muted hover:text-foreground',
    ),
);
</script>

<template>
    <!-- Texto y búsqueda: un cuadro y ya. Abrir un desplegable para escribir
         tres letras es un clic de más en el sitio donde más se filtra. -->
    <div v-if="filtro.tipo === 'texto' || filtro.tipo === 'busqueda'" class="relative w-full min-w-0">
        <SearchIcon
            v-if="filtro.tipo === 'busqueda'"
            class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
        />
        <input
            :value="borrador"
            type="text"
            inputmode="search"
            autocomplete="off"
            :placeholder="filtro.placeholder ?? 'Filtrar…'"
            :aria-label="`Filtrar por ${filtro.etiqueta}`"
            :class="
                cn(
                    'w-full min-w-0 rounded-md border bg-background transition-colors outline-none placeholder:text-muted-foreground',
                    'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
                    alto,
                    filtro.tipo === 'busqueda' ? 'pl-8 text-sm' : 'px-2 text-xs',
                    borrador !== '' ? 'border-primary/40 pr-7 font-medium' : 'border-input pr-2',
                )
            "
            @input="escribir(($event.target as HTMLInputElement).value)"
        />
        <button
            v-if="borrador !== ''"
            type="button"
            class="absolute top-1/2 right-1 flex size-5 -translate-y-1/2 items-center justify-center rounded text-muted-foreground transition-colors hover:text-destructive"
            :aria-label="`Quitar el filtro de ${filtro.etiqueta}`"
            @click="limpiar"
        >
            <XIcon class="size-3" />
        </button>
    </div>

    <!-- Rango de fechas: dos extremos, y cualquiera puede ir suelto. -->
    <Popover v-else-if="filtro.tipo === 'rango_fechas'" v-model:open="abierto">
        <PopoverTrigger :class="disparador" :aria-label="`Filtrar por ${filtro.etiqueta}`">
            <span class="min-w-0 flex-1 truncate">{{ resumen ?? filtro.etiqueta }}</span>
            <ChevronDownIcon class="size-3 shrink-0 opacity-60" />
        </PopoverTrigger>
        <PopoverContent align="start" class="w-64 gap-3 p-3">
            <div class="grid gap-1.5">
                <label :for="`rango-${filtro.clave}-desde`" class="text-xs font-medium">Desde</label>
                <input
                    :id="`rango-${filtro.clave}-desde`"
                    type="date"
                    :value="extremos[0]"
                    class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                    @change="fijarExtremo(0, ($event.target as HTMLInputElement).value)"
                />
            </div>
            <div class="grid gap-1.5">
                <label :for="`rango-${filtro.clave}-hasta`" class="text-xs font-medium">Hasta</label>
                <input
                    :id="`rango-${filtro.clave}-hasta`"
                    type="date"
                    :value="extremos[1]"
                    class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                    @change="fijarExtremo(1, ($event.target as HTMLInputElement).value)"
                />
            </div>
            <button
                v-if="activo"
                type="button"
                class="h-8 rounded-md text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                @click="limpiar"
            >
                Quitar el filtro
            </button>
        </PopoverContent>
    </Popover>

    <!-- Opciones: select, multi-select y booleano comparten lista. -->
    <Popover v-else v-model:open="abierto">
        <PopoverTrigger :class="disparador" :aria-label="`Filtrar por ${filtro.etiqueta}`">
            <span class="min-w-0 flex-1 truncate">{{ resumen ?? filtro.etiqueta }}</span>
            <span
                v-if="filtro.multiple && marcados.length > 1"
                class="cifra shrink-0 rounded-full bg-primary/15 px-1.5 text-[10px] text-primary"
            >
                {{ marcados.length }}
            </span>
            <ChevronDownIcon class="size-3 shrink-0 opacity-60" />
        </PopoverTrigger>

        <PopoverContent align="start" class="w-60 gap-0 overflow-hidden p-0">
            <div v-if="listable" class="border-b p-1.5">
                <input
                    v-model="busquedaOpciones"
                    type="text"
                    autocomplete="off"
                    placeholder="Buscar…"
                    :aria-label="`Buscar en las opciones de ${filtro.etiqueta}`"
                    class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                />
            </div>

            <div class="max-h-64 overflow-y-auto p-1">
                <button
                    v-for="opcion in visibles"
                    :key="opcion.valor"
                    type="button"
                    :aria-pressed="marcados.includes(opcion.valor)"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-muted"
                    @click="elegir(opcion.valor)"
                >
                    <CheckIcon
                        class="size-3.5 shrink-0 text-primary"
                        :class="marcados.includes(opcion.valor) ? 'opacity-100' : 'opacity-0'"
                    />
                    <span class="min-w-0 flex-1 truncate">{{ opcion.etiqueta }}</span>
                </button>

                <p v-if="visibles.length === 0" class="px-2 py-3 text-center text-xs text-muted-foreground">
                    Ninguna opción coincide
                </p>
            </div>

            <button
                v-if="activo"
                type="button"
                class="border-t px-3 py-2 text-left text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                @click="limpiar"
            >
                Quitar el filtro
            </button>
        </PopoverContent>
    </Popover>
</template>
