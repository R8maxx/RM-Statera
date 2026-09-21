<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import { SearchIcon, UsersIcon } from '@lucide/vue';
import IconoTipo from '@/components/IconoTipo.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Accion {
    id: number;
    codigo: string;
    titulo: string;
    tipo: string;
    tipoEtiqueta: string;
    tipoTono: string;
    tipoIcono: string;
    medida: string;
    fechaEtiqueta: string;
    duracion_horas: string | null;
    contenido: string | null;
    evidencia_id: number | null;
    evidencia: string | null;
}

interface PersonaConvocada {
    id: number;
    codigo: string;
    nombre: string;
    puesto: string | null;
    activa: boolean;
    convocada: boolean;
    asistio: boolean;
}

/**
 * La convocatoria de una sesión: quién estaba llamado y quién fue.
 *
 * **Pantalla propia y marcado en bloque**, por el mismo motivo que la checklist
 * de una auditoría: son veinte o cincuenta marcas y una ruta por persona sería
 * veinte peticiones y veinte oportunidades de dejarlo a medias.
 *
 * **Convocar y asistir son dos cosas distintas.** Quien no está marcado como
 * convocado no lo estuvo; quien lo está con la casilla de asistencia vacía fue
 * convocado y no fue, y ése es el que un auditor pregunta. Sin esa diferencia,
 * «formación impartida al 100 % de los convocados» saldría siempre.
 */
const props = defineProps<{
    accion: Accion;
    personas: PersonaConvocada[];
    puedeGestionar: boolean;
}>();

const lista = ref<PersonaConvocada[]>([]);
const busqueda = ref('');

watch(
    () => props.personas,
    (valor) => {
        lista.value = valor.map((persona) => ({ ...persona }));
    },
    { immediate: true, deep: true },
);

const visibles = computed(() => {
    const termino = busqueda.value.trim().toLowerCase();

    if (termino === '') {
        return lista.value;
    }

    return lista.value.filter(
        (persona) =>
            persona.nombre.toLowerCase().includes(termino) ||
            persona.codigo.toLowerCase().includes(termino) ||
            (persona.puesto ?? '').toLowerCase().includes(termino),
    );
});

const convocadas = computed(() => lista.value.filter((persona) => persona.convocada));
const asistentes = computed(() => convocadas.value.filter((persona) => persona.asistio));

/** Marcar en bloque **lo visible**, que es lo que hace útil el buscador. */
function marcarVisibles(asistio: boolean): void {
    for (const persona of visibles.value) {
        persona.convocada = true;
        persona.asistio = asistio;
    }
}

/**
 * Lo marcado y todavía no mandado.
 *
 * Toda la convocatoria se edita en local y viaja de una vez —cincuenta marcas
 * en una petición, que es el motivo de que esto sea pantalla propia—, así que
 * entre la primera casilla y el botón hay un rato en el que salir de aquí lo
 * tira. Decirlo es lo mínimo.
 */
function huella(personas: PersonaConvocada[]): string {
    return JSON.stringify(
        personas
            .filter((persona) => persona.convocada)
            .map((persona) => [persona.id, persona.asistio])
            .sort(),
    );
}

const sinGuardar = computed(() => huella(lista.value) !== huella(props.personas));

const guardando = ref(false);

/** Ver el mismo comentario en la ficha de una persona. */
const error = ref<string | null>(null);

function guardar(): void {
    guardando.value = true;
    error.value = null;

    router.put(
        `/formacion/${props.accion.id}/asistencia`,
        {
            convocadas: convocadas.value.map((persona) => ({
                persona_id: persona.id,
                asistio: persona.asistio,
            })),
        },
        {
            preserveScroll: true,
            onError: (errores) => {
                error.value =
                    Object.values(errores)[0] ??
                    'No se ha podido guardar la asistencia. Inténtalo otra vez.';
            },
            onFinish: () => (guardando.value = false),
        },
    );
}
</script>

<template>
    <AppLayout :titulo="accion.codigo">
        <CabeceraPagina :titulo="accion.titulo" :descripcion="accion.codigo">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/formacion/${accion.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: accion.tipo,
                    etiqueta: accion.tipoEtiqueta,
                    tono: accion.tipoTono,
                    icono: accion.tipoIcono,
                }"
            />
            <span class="cifra text-sm text-muted-foreground">{{ accion.medida }}</span>
            <span class="text-sm text-muted-foreground">{{ accion.fechaEtiqueta }}</span>
            <span v-if="accion.duracion_horas" class="text-sm text-muted-foreground">
                {{ accion.duracion_horas }} h
            </span>
        </div>

        <Card v-if="accion.contenido">
            <CardHeader>
                <CardTitle>Contenido</CardTitle>
            </CardHeader>
            <CardContent class="text-sm whitespace-pre-line">{{ accion.contenido }}</CardContent>
        </Card>

        <!--
            La prueba, que es la mitad de la medida: `mp.per.3` y `mp.per.4` no
            piden que se imparta la sesión, piden poder demostrarlo. Se adjunta
            al editar la sesión.
        -->
        <Card>
            <CardHeader>
                <CardTitle>Hoja de firmas</CardTitle>
            </CardHeader>
            <CardContent class="text-sm">
                <p v-if="accion.evidencia_id" class="flex flex-wrap items-center gap-2">
                    <IconoTipo nombre="FileCheck" />
                    <Link
                        :href="`/evidencias/${accion.evidencia_id}`"
                        class="underline underline-offset-4"
                    >
                        {{ accion.evidencia }}
                    </Link>
                </p>
                <p v-else class="text-muted-foreground">
                    Sin evidencia adjunta. Una sesión registrada y sin prueba está declarada y no
                    demostrada, que es lo que un auditor separa.
                    <Link
                        v-if="puedeGestionar"
                        :href="`/formacion/${accion.id}/editar`"
                        class="font-medium underline underline-offset-4"
                    >
                        Adjuntarla
                    </Link>
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Convocatoria y asistencia</CardTitle>
                <CardDescription>
                    Quien sale de la lista deja de estar convocado, que no es lo mismo que haber
                    faltado. Las personas dadas de baja sólo aparecen si ya estaban convocadas:
                    asistieron de verdad y borrarlas reescribiría el registro.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <Aviso v-if="error" tono="error">{{ error }}</Aviso>

                <!--
                    Sin `Cifra`: el contador es para los números que resumen una
                    pantalla, y éste cambia con cada casilla que se marca —así
                    que se pondría a contar en cada clic—.
                -->
                <p class="text-sm text-muted-foreground">
                    <span class="cifra font-semibold text-foreground">{{ asistentes.length }}</span>
                    de {{ convocadas.length }}
                    {{ convocadas.length === 1 ? 'convocado asistió' : 'convocados asistieron' }}.
                </p>

                <div class="flex flex-wrap items-center gap-2">
                    <Input
                        v-model="busqueda"
                        placeholder="Buscar por nombre, código o puesto"
                        aria-label="Buscar en la plantilla"
                        class="max-w-xs"
                    />
                    <!--
                        Con el buscador vacío, «lo visible» es la plantilla
                        entera. El número va en el botón porque marcar a
                        doscientas personas de un clic y darse cuenta después no
                        tiene vuelta atrás más que no guardando.
                    -->
                    <template v-if="puedeGestionar && visibles.length > 0">
                        <Button variant="outline" size="sm" @click="marcarVisibles(true)">
                            Convocar y dar por asistidas ({{ visibles.length }})
                        </Button>
                        <Button variant="outline" size="sm" @click="marcarVisibles(false)">
                            Convocar sin asistencia ({{ visibles.length }})
                        </Button>
                    </template>
                </div>

                <EstadoVacio
                    v-if="visibles.length === 0 && busqueda.trim() !== ''"
                    :icono="SearchIcon"
                    titulo="Nadie encaja con lo buscado"
                    descripcion="Prueba con parte del nombre, con el código o con el puesto."
                />
                <EstadoVacio
                    v-else-if="visibles.length === 0"
                    :icono="UsersIcon"
                    titulo="Todavía no hay plantilla que convocar"
                    descripcion="Una sesión sin nadie apuntado no prueba que se impartiera: mp.per.3 y mp.per.4 se demuestran con la lista de asistentes."
                    :accion="{ etiqueta: 'Dar de alta a alguien', href: '/personas/crear' }"
                />
                <!--
                    La lista mengua al teclear en el buscador, así que lleva
                    salida: `DESIGN.md` §14, «si puede menguar, lleva salida».
                -->
                <TransitionGroup v-else tag="ul" name="paso" class="divide-y divide-border">
                    <li
                        v-for="persona in visibles"
                        :key="persona.id"
                        class="flex flex-wrap items-center gap-3 py-2 text-sm"
                    >
                        <Checkbox
                            :model-value="persona.convocada"
                            :disabled="!puedeGestionar"
                            :aria-label="`Convocar a ${persona.nombre}`"
                            @update:model-value="
                                (valor) => {
                                    persona.convocada = valor === true;
                                    if (!persona.convocada) {
                                        persona.asistio = false;
                                    }
                                }
                            "
                        />
                        <!--
                            Nombre y metadatos en un solo bloque `min-w-0
                            flex-1`: con el enlace suelto en `flex-1` el código
                            se iba al otro extremo de la fila, y con un ancho
                            mínimo fijo la fila desbordaba a 375 px.
                        -->
                        <div class="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-2">
                            <Link
                                :href="`/personas/${persona.id}`"
                                class="underline underline-offset-4"
                            >
                                {{ persona.nombre }}
                            </Link>
                            <span class="text-xs text-muted-foreground">
                                <span class="cifra">{{ persona.codigo }}</span>
                                <template v-if="persona.puesto"> · {{ persona.puesto }}</template>
                                <template v-if="!persona.activa"> · dada de baja</template>
                            </span>
                        </div>

                        <!--
                            `ml-auto` no: al envolver a 375 px dejaba esta
                            casilla sola en su línea y pegada al borde derecho.
                            Con el nombre en `flex-1` el hueco lo reparte él.
                        -->
                        <label class="flex shrink-0 items-center gap-2">
                            <Checkbox
                                :model-value="persona.asistio"
                                :disabled="!puedeGestionar || !persona.convocada"
                                :aria-label="`${persona.nombre} asistió`"
                                @update:model-value="(valor) => (persona.asistio = valor === true)"
                            />
                            <span class="text-xs text-muted-foreground">Asistió</span>
                        </label>
                    </li>
                </TransitionGroup>

                <div v-if="puedeGestionar" class="flex flex-wrap items-center gap-2">
                    <Button
                        :variant="sinGuardar ? 'default' : 'outline'"
                        :disabled="guardando"
                        @click="guardar"
                    >
                        {{ guardando ? 'Guardando…' : 'Guardar asistencia' }}
                    </Button>
                    <!--
                        `aria-live`: aparece y desaparece solo, así que sin esto
                        quien usa lector de pantalla no se entera de que hay algo
                        pendiente de mandar.
                    -->
                    <span
                        role="status"
                        aria-live="polite"
                        class="text-xs font-medium text-estado-en-progreso"
                    >
                        <template v-if="sinGuardar">Sin guardar</template>
                    </span>
                </div>
            </CardContent>
        </Card>
    </AppLayout>
</template>
