<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { formatoFecha } from '@/lib/celdas';
import { ChevronDownIcon, ChevronUpIcon, PencilIcon, PlusIcon, XIcon } from '@lucide/vue';
import { computed, nextTick, ref, useId, useTemplateRef, watch } from 'vue';

export interface Paso {
    id: number | null;
    titulo: string;
    hecho: boolean;
    hechoEn?: string | null;
}

/**
 * Una lista de comprobación: añadir, renombrar, marcar, reordenar y quitar.
 *
 * **Dos consumidores desde el § 4.8**: las subtareas de una tarea y las dos
 * checklists de una persona —la de incorporación y la de salida—. Sigue en
 * `components/tarea/` porque es donde nació y la regla de la casa mueve al
 * tercero, no al segundo; cuando llegue, esto va a `components/` a secas, al
 * lado de `Aviso` y `EstadoVacio`, y son dos líneas de import. Lo que no puede
 * pasar mientras tanto es que este fichero vuelva a nombrar «tarea» por dentro,
 * y por eso el texto del vacío y el del cierre llegan por prop.
 *
 * **El componente no sabe a dónde escribe: emite `guardar`.** Los dos contratos
 * HTTP son distintos de verdad —una tarea exige título y estado en cada paso,
 * una persona los admite nulos y pide además de qué checklist se trata—, y el
 * `FormRequest` es la única fuente de verdad de cada uno. Pasarle una URL y una
 * bolsa de campos extra metería los dos vocabularios dentro de la pieza
 * compartida, que es el problema del que venimos.
 *
 * **Habla `hecho`, en masculino**, que es lo que dice su tipo. Tareas traduce
 * desde su `hecha` en la página; el día del tercer consumidor se unifica la
 * palabra del cable y esa traducción desaparece.
 *
 * **Se guarda en cada gesto y no hay botón.** La clase `.tachado` existe porque
 * la respuesta del servidor tarda y ver cómo se tacha lo escrito es el único
 * acuse que hay; con guardado diferido esa raya dibujaría el acuse de algo que
 * todavía no ha salido del navegador.
 */
const props = defineProps<{
    pasos: Paso[];
    maximo: number;
    /** Sin permiso de gestión, la lista se lee y no se toca. */
    editable: boolean;
    /** Hay una petición en vuelo: la pone la página, que es quien la lanza. */
    ocupado?: boolean;
    /** Qué decir cuando no hay ni un paso. */
    vacio?: string;
    /** Qué decir cuando están todos marcados. Nada, si no procede. */
    cierre?: string;
    /** Pinta cuándo se marcó cada paso. */
    conFecha?: boolean;
}>();

const emit = defineEmits<{ guardar: [pasos: Paso[]] }>();

/**
 * El prefijo de los `id`, por instancia.
 *
 * Con `paso-0` a secas bastaba mientras sólo hubiera una lista por pantalla.
 * La ficha de una persona monta **dos** —la de incorporación y la de salida—,
 * así que los dos primeros pasos compartían `id`: el `<label>` del segundo
 * apuntaba por `for` al checkbox del primero y marcar en la lista de salida
 * tocaba la de entrada. No lanza, no avisa y toca el dato equivocado.
 */
const id = useId();

/*
 * Copia local: la lista se edita entera y se manda entera, así que el estado
 * vive aquí hasta que se guarda. El servidor manda al volver.
 */
const lista = ref<Paso[]>(props.pasos.map((paso) => ({ ...paso })));
const nuevo = ref('');

/** Qué fila se está renombrando, y con qué texto. */
const renombrando = ref<number | null>(null);
const borrador = ref('');
const campoRenombre = useTemplateRef<HTMLInputElement[]>('campoRenombre');

watch(
    () => props.pasos,
    (pasos) => {
        lista.value = pasos.map((paso) => ({ ...paso }));
        renombrando.value = null;
    },
);

const hechos = computed(() => lista.value.filter((paso) => paso.hecho).length);
const lleno = computed(() => lista.value.length >= props.maximo);

const fecha = (valor?: string | null): string =>
    valor ? formatoFecha.format(new Date(valor)) : '';

function guardar(): void {
    emit('guardar', lista.value.map((paso) => ({ ...paso })));
}

function anadir(): void {
    const titulo = nuevo.value.trim();

    if (titulo === '' || lleno.value) {
        return;
    }

    lista.value = [...lista.value, { id: null, titulo, hecho: false }];
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

/**
 * Renombrar en sitio, y no quitar y volver a añadir.
 *
 * Quitar y añadir pierde el `id`, y con él `hecho_en` — el sello de cuándo se
 * hizo el paso, que es justamente lo que una checklist de salida existe para
 * conservar.
 */
async function empezarARenombrar(indice: number): Promise<void> {
    renombrando.value = indice;
    borrador.value = lista.value[indice]?.titulo ?? '';

    await nextTick();
    campoRenombre.value?.[0]?.focus();
    campoRenombre.value?.[0]?.select();
}

function confirmarRenombre(indice: number): void {
    if (renombrando.value !== indice) {
        return;
    }

    const titulo = borrador.value.trim();
    renombrando.value = null;

    // Un título en blanco restaura el anterior en vez de mandarse: el
    // `FormRequest` de una tarea lo exige y aquí no hay nada que corregir.
    if (titulo === '' || titulo === lista.value[indice]?.titulo) {
        return;
    }

    lista.value = lista.value.map((paso, i) => (i === indice ? { ...paso, titulo } : paso));
    guardar();
}
</script>

<template>
    <div>
        <p v-if="lista.length > 0" class="mb-3 text-sm text-muted-foreground">
            <span class="cifra font-medium text-foreground">{{ hechos }}/{{ lista.length }}</span>
            pasos hechos.
            <span v-if="cierre && hechos === lista.length && editable"> {{ cierre }}</span>
        </p>

        <!--
            Reordenar con las flechas intercambiaba dos filas de un fotograma al
            siguiente y había que releer la lista para saber cuál se había
            movido. `<TransitionGroup>` hace FLIP con el `:key` que ya estaba, y
            no hace falta ni una línea de estado: la posición la sigue el
            navegador.

            Aquí sí y en la tabla no, y la diferencia es de dónde viene el orden:
            esto es una lista corta que el usuario reordena a mano, no cincuenta
            filas que el servidor devuelve otra vez cada vez que alguien filtra.
        -->
        <TransitionGroup
            v-if="lista.length > 0"
            tag="ul"
            name="paso"
            class="relative space-y-1"
        >
            <li
                v-for="(paso, indice) in lista"
                :key="paso.id ?? `nuevo-${indice}`"
                class="group flex items-center gap-2 rounded-md px-1 py-1 hover:bg-fila-hover"
            >
                <Checkbox
                    :id="`${id}-paso-${indice}`"
                    :model-value="paso.hecho"
                    :disabled="!editable || ocupado"
                    @update:model-value="
                        (valor) => {
                            paso.hecho = valor === true;
                            guardar();
                        }
                    "
                />

                <!--
                    Renombrar cambia esta fila y sólo ésta. Una prop que
                    convirtiera todas las filas en campos serían dos componentes
                    en una gabardina, y de paso perdería el tachado.
                -->
                <Input
                    v-if="renombrando === indice"
                    ref="campoRenombre"
                    v-model="borrador"
                    :aria-label="`Renombrar «${paso.titulo}»`"
                    class="h-7 min-w-0 flex-1"
                    @blur="confirmarRenombre(indice)"
                    @keydown.enter.prevent="confirmarRenombre(indice)"
                    @keydown.esc.prevent="renombrando = null"
                />

                <!--
                    La raya se traza, no aparece. `line-through` es instantáneo y
                    no confirma nada; ver cómo se tacha lo escrito es lo que dice
                    que el clic llegó, y es el único acuse que hay —la lista se
                    guarda contra el servidor y la respuesta tarda—. La clase
                    `.tachado` de `app.css` lo hace con `scaleX` sobre un
                    pseudo-elemento: `text-decoration` no se puede animar.
                -->
                <label
                    v-else
                    :for="`${id}-paso-${indice}`"
                    class="min-w-0 flex-1 cursor-pointer text-sm transition-colors duration-[var(--duracion)] ease-marca"
                    :class="paso.hecho ? 'text-muted-foreground' : ''"
                >
                    <!--
                        La raya va en un `<span>` en línea y no en el `<label>`:
                        el label es un elemento flex, y `box-decoration-break`
                        —que es lo que hace que un paso de dos líneas se tache
                        entero— sólo actúa sobre cajas en línea.
                    -->
                    <span class="tachado" :data-hecha="paso.hecho ? '' : undefined">{{ paso.titulo }}</span>
                </label>

                <span
                    v-if="conFecha && paso.hecho && paso.hechoEn"
                    class="shrink-0 text-xs text-muted-foreground"
                >
                    {{ fecha(paso.hechoEn) }}
                </span>

                <div v-if="editable" class="flex shrink-0 gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="ocupado || renombrando === indice"
                        :aria-label="`Renombrar «${paso.titulo}»`"
                        @click="empezarARenombrar(indice)"
                    >
                        <PencilIcon class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="indice === 0 || ocupado"
                        :aria-label="`Subir «${paso.titulo}»`"
                        @click="mover(indice, -1)"
                    >
                        <ChevronUpIcon class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="indice === lista.length - 1 || ocupado"
                        :aria-label="`Bajar «${paso.titulo}»`"
                        @click="mover(indice, 1)"
                    >
                        <ChevronDownIcon class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        :disabled="ocupado"
                        :aria-label="`Quitar «${paso.titulo}»`"
                        @click="quitar(indice)"
                    >
                        <XIcon class="size-3.5" />
                    </Button>
                </div>
            </li>
        </TransitionGroup>

        <p v-else class="text-sm text-muted-foreground">
            {{ vacio ?? 'Sin pasos todavía.' }}
        </p>

        <form v-if="editable" class="mt-3 flex gap-2" @submit.prevent="anadir">
            <Input
                v-model="nuevo"
                :disabled="lleno || ocupado"
                :placeholder="lleno ? `El tope son ${maximo} pasos` : 'Añadir un paso…'"
                aria-label="Nuevo paso"
                class="h-8"
            />
            <Button type="submit" variant="outline" size="sm" :disabled="nuevo.trim() === '' || lleno || ocupado">
                <PlusIcon class="size-4" />
                Añadir
            </Button>
        </form>
    </div>
</template>
