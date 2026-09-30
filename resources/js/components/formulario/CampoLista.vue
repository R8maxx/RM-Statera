<script setup lang="ts">
import MarcaProcedencia from '@/components/formulario/MarcaProcedencia.vue';
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PlusIcon, XIcon } from '@lucide/vue';
import { computed, nextTick, useId, useTemplateRef } from 'vue';

/**
 * Una lista de valores cortos, uno por fila: las referencias de una
 * vulnerabilidad. Viaja como `nombre[]`.
 *
 * Era un textarea «una dirección por línea», y el `FormRequest` la partía por
 * saltos de línea. Funcionaba, pero el error de la tercera dirección salía
 * debajo de la caja entera y había que contar líneas para encontrarlo. Aquí
 * cae en su fila.
 *
 * **Una fila vacía no se envía**: se queda sin `name`, y `FormData` se salta lo
 * que no lo tiene. Así el índice del error del servidor (`referencias.2`) es
 * el de la tercera fila **con texto**, y de ahí se traduce a la fila que se ve.
 * Con todas vacías viaja un `nombre[]` vacío, como en `CampoCasillas`, para
 * que vaciar la lista en una edición la vacíe.
 */
const props = withDefaults(
    defineProps<{
        nombre: string;
        etiqueta: string;
        /** La bolsa de errores entera: de ahí salen el del grupo y el de cada fila. */
        errores?: Record<string, string | undefined>;
        ayuda?: string;
        placeholder?: string;
        etiquetaAnadir?: string;
        maximo?: number;
        /** De dónde salieron los valores (ver `CampoBase`). */
        procedencia?: string | null;
        procedenciaEditada?: boolean;
    }>(),
    { errores: () => ({}), etiquetaAnadir: 'Añadir', maximo: 20 },
);

const modelo = defineModel<string[]>({ default: () => [] });
const id = `${props.nombre}-${useId()}`;
const lista = useTemplateRef<HTMLElement>('lista');

/** Las filas que se ven: al menos una, para escribir sin pulsar antes nada. */
const filas = computed(() => (modelo.value.length === 0 ? [''] : modelo.value));

/** Por fila: su posición entre las que llevan texto (la que ve el servidor), o nula. */
const posiciones = computed(() => {
    let siguiente = 0;

    return filas.value.map((valor) => (valor.trim() === '' ? null : siguiente++));
});

const errorGrupo = computed(() => props.errores[props.nombre]);

function errorDe(indice: number): string | undefined {
    const posicion = posiciones.value[indice];

    return posicion === null ? undefined : props.errores[`${props.nombre}.${posicion}`];
}

const hayTexto = computed(() => posiciones.value.some((posicion) => posicion !== null));

function cambiar(indice: number, valor: string | number): void {
    const nuevas = [...filas.value];
    nuevas[indice] = String(valor);
    modelo.value = nuevas;
}

async function anadir(): Promise<void> {
    modelo.value = [...filas.value, ''];
    await nextTick();
    lista.value?.querySelector<HTMLInputElement>('li:last-child input')?.focus();
}

function quitar(indice: number): void {
    modelo.value = filas.value.filter((_, otro) => otro !== indice);
}

const descrito = computed(
    () => [props.ayuda ? `${id}-ayuda` : null, errorGrupo.value ? `${id}-error` : null].filter(Boolean).join(' ') || undefined,
);
</script>

<template>
    <fieldset class="grid gap-2" :aria-describedby="descrito">
        <legend class="mb-2 flex items-center gap-2 text-sm leading-none font-medium">
            {{ etiqueta }}
            <MarcaProcedencia v-if="procedencia" :fuente="procedencia" :editado="procedenciaEditada" />
        </legend>

        <input v-if="!hayTexto" type="hidden" :name="`${nombre}[]`" value="" />

        <ul ref="lista" class="grid gap-2">
            <li v-for="(valor, indice) in filas" :key="indice" class="grid gap-1.5">
                <div class="flex items-center gap-2">
                    <Input
                        :model-value="valor"
                        :name="valor.trim() === '' ? undefined : `${nombre}[]`"
                        :placeholder="placeholder"
                        :aria-label="`${etiqueta}, ${indice + 1} de ${filas.length}`"
                        :aria-invalid="errorDe(indice) ? true : undefined"
                        :aria-describedby="errorDe(indice) ? `${id}-${indice}-error` : undefined"
                        :data-campo="posiciones[indice] === null ? (indice === 0 ? nombre : undefined) : `${nombre}.${posiciones[indice]}`"
                        class="cifra text-[13px]"
                        inputmode="url"
                        @update:model-value="(nuevo) => cambiar(indice, nuevo)"
                    />
                    <Button
                        v-if="filas.length > 1 || valor !== ''"
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="`Quitar la fila ${indice + 1}`"
                        @click="quitar(indice)"
                    >
                        <XIcon aria-hidden="true" />
                    </Button>
                </div>
                <MensajeError :id="`${id}-${indice}-error`" :mensaje="errorDe(indice)" />
            </li>
        </ul>

        <div>
            <Button v-if="filas.length < maximo" type="button" variant="ghost" size="sm" class="-ml-2 text-primary" @click="anadir">
                <PlusIcon aria-hidden="true" />
                {{ etiquetaAnadir }}
            </Button>
        </div>

        <p v-if="ayuda" :id="`${id}-ayuda`" class="text-xs text-muted-foreground">{{ ayuda }}</p>
        <MensajeError :id="`${id}-error`" :mensaje="errorGrupo" />
    </fieldset>
</template>
