<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
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
    convocadas: number;
    asistentes: number;
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

function guardar(): void {
    router.put(
        `/formacion/${props.accion.id}/asistencia`,
        {
            convocadas: convocadas.value.map((persona) => ({
                persona_id: persona.id,
                asistio: persona.asistio,
            })),
        },
        { preserveScroll: true },
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
                <p class="text-sm text-muted-foreground">
                    <Cifra class="font-semibold text-foreground" :valor="asistentes.length" />
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
                    <template v-if="puedeGestionar">
                        <Button variant="outline" size="sm" @click="marcarVisibles(true)">
                            Marcar asistencia de lo visible
                        </Button>
                        <Button variant="outline" size="sm" @click="marcarVisibles(false)">
                            Convocar lo visible sin asistencia
                        </Button>
                    </template>
                </div>

                <EstadoVacio
                    v-if="visibles.length === 0"
                    titulo="Sin personas"
                    descripcion="No hay nadie en plantilla que encaje con lo buscado."
                />
                <ul v-else class="divide-y divide-border">
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
                        <Link
                            :href="`/personas/${persona.id}`"
                            class="min-w-40 underline underline-offset-4"
                        >
                            {{ persona.nombre }}
                        </Link>
                        <span class="text-xs text-muted-foreground">
                            <span class="cifra">{{ persona.codigo }}</span>
                            <template v-if="persona.puesto"> · {{ persona.puesto }}</template>
                            <template v-if="!persona.activa"> · dada de baja</template>
                        </span>

                        <label class="ml-auto flex items-center gap-2">
                            <Checkbox
                                :model-value="persona.asistio"
                                :disabled="!puedeGestionar || !persona.convocada"
                                @update:model-value="(valor) => (persona.asistio = valor === true)"
                            />
                            <span class="text-xs text-muted-foreground">Asistió</span>
                        </label>
                    </li>
                </ul>

                <Button v-if="puedeGestionar" @click="guardar">Guardar asistencia</Button>
            </CardContent>
        </Card>
    </AppLayout>
</template>
