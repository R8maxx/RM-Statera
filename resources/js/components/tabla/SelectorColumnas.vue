<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import {
    ChevronDownIcon,
    ChevronUpIcon,
    Columns3Icon,
    GripVerticalIcon,
    PinIcon,
    RotateCcwIcon,
} from '@lucide/vue';
import { ref } from 'vue';

type Columna = App.Http.Resources.Definicion.Columna;

/**
 * Qué columnas se ven y en qué orden.
 *
 * Antes era un desplegable con casillas y el orden se cambiaba de una en una
 * desde el menú de cada cabecera. Con seis columnas bastaba; con trece, mover la
 * última al principio son doce clics y hay que buscarla cada vez.
 *
 * **Es un `Popover` y no un `DropdownMenu` a propósito.** Un menú de Reka
 * gobierna el foco con navegación de roving: las flechas mueven el cursor entre
 * ítems y el arrastre se pelea con eso. Un popover es una caja donde caben
 * controles de verdad.
 *
 * **Las flechas ↑ ↓ no son un adorno.** El arrastre no llega por teclado, y sin
 * ellas reordenar quedaría fuera del alcance de quien no usa ratón (§11 de
 * DESIGN.md).
 *
 * El arrastre va con eventos nativos de HTML5, sin librería: son cuarenta líneas
 * y aquí cada dependencia se justifica en una revisión.
 */
const props = defineProps<{
    /** En el orden en el que están hoy, ancladas primero. */
    columnas: Columna[];
    visibles: Set<string>;
    ancladas: Set<string>;
    ocultas: number;
}>();

const emit = defineEmits<{
    alternar: [clave: string];
    /** Mover `clave` a la posición que ocupa `destino`. */
    reordenar: [clave: string, destino: string];
    anclar: [clave: string, anclada: boolean];
    restablecer: [];
}>();

const arrastrando = ref<string | null>(null);
const encima = ref<string | null>(null);

/**
 * Una columna anclada no se saca del bloque anclado arrastrándola, ni al revés.
 *
 * Es la misma regla que aplica el menú de la cabecera: mover una columna fuera
 * de su bloque dejaría el orden diciendo una cosa y el anclado otra, y la tabla
 * pintaría lo que dice el anclado.
 */
function compatibles(una: string, otra: string): boolean {
    return props.ancladas.has(una) === props.ancladas.has(otra);
}

function empezar(clave: string, evento: DragEvent): void {
    arrastrando.value = clave;

    if (evento.dataTransfer) {
        evento.dataTransfer.effectAllowed = 'move';
        // Firefox no inicia el arrastre si no se escribe algo aquí.
        evento.dataTransfer.setData('text/plain', clave);
    }
}

function sobre(clave: string, evento: DragEvent): void {
    if (arrastrando.value === null || arrastrando.value === clave || !compatibles(arrastrando.value, clave)) {
        return;
    }

    evento.preventDefault();
    encima.value = clave;
}

function soltar(clave: string): void {
    if (arrastrando.value !== null && arrastrando.value !== clave && compatibles(arrastrando.value, clave)) {
        emit('reordenar', arrastrando.value, clave);
    }

    arrastrando.value = null;
    encima.value = null;
}

function terminar(): void {
    arrastrando.value = null;
    encima.value = null;
}

/** El vecino al que saltaría con la flecha, o nulo si no hay a dónde. */
function vecina(clave: string, paso: -1 | 1): Columna | null {
    const posicion = props.columnas.findIndex((columna) => columna.clave === clave);
    const candidata = props.columnas[posicion + paso];

    return candidata !== undefined && compatibles(clave, candidata.clave) ? candidata : null;
}

function mover(clave: string, paso: -1 | 1): void {
    const destino = vecina(clave, paso);

    if (destino !== null) {
        emit('reordenar', clave, destino.clave);
    }
}
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
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
        </PopoverTrigger>

        <PopoverContent align="end" class="w-72 p-0">
            <div class="border-b px-3 py-2.5">
                <p class="text-sm font-medium">Columnas</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Arrastra para reordenar y usa el alfiler para fijar una columna a la izquierda. Lo que
                    apagues sigue estando: se consulta desplegando la fila.
                </p>
            </div>

            <ul class="max-h-80 overflow-y-auto p-1.5">
                <li
                    v-for="columna in columnas"
                    :key="columna.clave"
                    :draggable="true"
                    :class="
                        cn(
                            'flex items-center gap-1.5 rounded-md px-1.5 py-1 transition-colors',
                            arrastrando === columna.clave && 'opacity-40',
                            encima === columna.clave && 'bg-accent ring-1 ring-primary/40',
                        )
                    "
                    @dragstart="(evento: DragEvent) => empezar(columna.clave, evento)"
                    @dragover="(evento: DragEvent) => sobre(columna.clave, evento)"
                    @drop="soltar(columna.clave)"
                    @dragend="terminar"
                >
                    <GripVerticalIcon class="size-3.5 shrink-0 cursor-grab text-muted-foreground" aria-hidden="true" />

                    <Checkbox
                        :id="`columna-${columna.clave}`"
                        :model-value="visibles.has(columna.clave)"
                        :disabled="columna.anclada"
                        @update:model-value="emit('alternar', columna.clave)"
                    />

                    <label
                        :for="`columna-${columna.clave}`"
                        class="min-w-0 flex-1 cursor-pointer truncate text-sm"
                        :class="visibles.has(columna.clave) ? '' : 'text-muted-foreground'"
                    >
                        {{ columna.etiqueta }}
                    </label>

                    <!--
                        Fijar la columna. Deshabilitado en las que el recurso
                        declara ancladas —el código, por ejemplo—: son fijas por
                        diseño, y soltarlas deja la tabla sin ningún punto de
                        referencia al desplazarse en horizontal.
                    -->
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="columna.anclada"
                        :aria-pressed="ancladas.has(columna.clave)"
                        :aria-label="
                            ancladas.has(columna.clave)
                                ? `Soltar ${columna.etiqueta}`
                                : `Fijar ${columna.etiqueta} a la izquierda`
                        "
                        :class="ancladas.has(columna.clave) ? 'text-primary' : 'text-muted-foreground'"
                        @click="emit('anclar', columna.clave, !ancladas.has(columna.clave))"
                    >
                        <PinIcon />
                    </Button>

                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="vecina(columna.clave, -1) === null"
                        :aria-label="`Subir ${columna.etiqueta}`"
                        @click="mover(columna.clave, -1)"
                    >
                        <ChevronUpIcon />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="vecina(columna.clave, 1) === null"
                        :aria-label="`Bajar ${columna.etiqueta}`"
                        @click="mover(columna.clave, 1)"
                    >
                        <ChevronDownIcon />
                    </Button>
                </li>
            </ul>

            <!-- La vista se guarda en el navegador, así que hace falta una
                 puerta de vuelta: sin esto, una columna oculta hace meses sigue
                 oculta y no hay forma de saber por qué. -->
            <div class="border-t p-1.5">
                <Button variant="ghost" size="sm" class="w-full justify-start gap-2" @click="emit('restablecer')">
                    <RotateCcwIcon class="size-4" />
                    Restablecer la vista
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
