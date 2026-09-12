<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/vue3';
import { ChevronDownIcon, ChevronUpIcon, PlusIcon, XIcon } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

export interface Paso {
    id: number | null;
    titulo: string;
    hecha: boolean;
    hechaEn?: string | null;
}

const props = defineProps<{
    tareaId: number;
    pasos: Paso[];
    maximo: number;
    /** Sin permiso de gestión, la lista se lee y no se toca. */
    editable: boolean;
}>();

/*
 * Copia local: la lista se edita entera y se manda entera, así que el estado
 * vive aquí hasta que se guarda. El servidor manda al volver.
 */
const lista = ref<Paso[]>(props.pasos.map((paso) => ({ ...paso })));
const guardando = ref(false);
const nuevo = ref('');

watch(
    () => props.pasos,
    (pasos) => {
        lista.value = pasos.map((paso) => ({ ...paso }));
    },
);

const hechos = computed(() => lista.value.filter((paso) => paso.hecha).length);
const lleno = computed(() => lista.value.length >= props.maximo);

function guardar(): void {
    guardando.value = true;

    router.put(
        `/tareas/${props.tareaId}/subtareas`,
        { pasos: lista.value.map(({ id, titulo, hecha }) => ({ id, titulo, hecha })) },
        {
            preserveScroll: true,
            onFinish: () => (guardando.value = false),
        },
    );
}

function anadir(): void {
    const titulo = nuevo.value.trim();

    if (titulo === '' || lleno.value) {
        return;
    }

    lista.value = [...lista.value, { id: null, titulo, hecha: false }];
    nuevo.value = '';
    guardar();
}

function quitar(indice: number): void {
    lista.value = lista.value.filter((_, i) => i !== indice);
    guardar();
}

function mover(indice: number, salto: number): void {
    const destino = indice + salto;

    if (destino < 0 || destino >= lista.value.length) {
        return;
    }

    const copia = [...lista.value];
    [copia[indice], copia[destino]] = [copia[destino], copia[indice]];
    lista.value = copia;
    guardar();
}
</script>

<template>
    <div>
        <p v-if="lista.length > 0" class="mb-3 text-sm text-muted-foreground">
            <span class="cifra font-medium text-foreground">{{ hechos }}/{{ lista.length }}</span>
            pasos hechos.
            <!--
                Marcarlos todos no cierra la tarea: cerrarla es una decisión con
                su transición, su fecha y su autor.
            -->
            <span v-if="hechos === lista.length && editable"> Ciérrala desde «Estado» cuando toque.</span>
        </p>

        <ul v-if="lista.length > 0" class="space-y-1">
            <li
                v-for="(paso, indice) in lista"
                :key="paso.id ?? `nuevo-${indice}`"
                class="group flex items-center gap-2 rounded-md px-1 py-1 hover:bg-fila-hover"
            >
                <Checkbox
                    :id="`paso-${indice}`"
                    :model-value="paso.hecha"
                    :disabled="!editable || guardando"
                    @update:model-value="
                        (valor) => {
                            paso.hecha = valor === true;
                            guardar();
                        }
                    "
                />

                <label
                    :for="`paso-${indice}`"
                    class="min-w-0 flex-1 cursor-pointer text-sm"
                    :class="paso.hecha ? 'text-muted-foreground line-through' : ''"
                >
                    {{ paso.titulo }}
                </label>

                <div v-if="editable" class="flex shrink-0 gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="indice === 0 || guardando"
                        :aria-label="`Subir «${paso.titulo}»`"
                        @click="mover(indice, -1)"
                    >
                        <ChevronUpIcon class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="indice === lista.length - 1 || guardando"
                        :aria-label="`Bajar «${paso.titulo}»`"
                        @click="mover(indice, 1)"
                    >
                        <ChevronDownIcon class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="guardando"
                        :aria-label="`Quitar «${paso.titulo}»`"
                        @click="quitar(indice)"
                    >
                        <XIcon class="size-3.5" />
                    </Button>
                </div>
            </li>
        </ul>

        <p v-else class="text-sm text-muted-foreground">
            Sin pasos. Una lista de comprobación sirve para trocear una tarea sin inflar el plan con
            tareas sueltas.
        </p>

        <form v-if="editable" class="mt-3 flex gap-2" @submit.prevent="anadir">
            <Input
                v-model="nuevo"
                :disabled="lleno || guardando"
                :placeholder="lleno ? `El tope son ${maximo} pasos` : 'Añadir un paso…'"
                aria-label="Nuevo paso"
                class="h-8"
            />
            <Button type="submit" variant="outline" size="sm" :disabled="nuevo.trim() === '' || lleno || guardando">
                <PlusIcon class="size-4" />
                Añadir
            </Button>
        </form>
    </div>
</template>
