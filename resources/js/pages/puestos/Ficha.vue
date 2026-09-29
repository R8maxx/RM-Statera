<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible } from '@/lib/celdas';
import { Link } from '@inertiajs/vue3';
import { Check, CircleDashed, UserRound } from '@lucide/vue';
import { computed } from 'vue';

/**
 * La ficha de puesto.
 *
 * Enseña quién lo ocupa y quién lo ocupó, y **no lo edita**: la asignación se
 * gestiona desde la ficha de la persona, que es donde se pregunta «¿qué hace
 * ésta?» — mucho más a menudo que «¿quién ocupa esto?». Tenerlo en los dos
 * sitios sería dos formularios que escriben la misma tabla.
 *
 * **«Caracterizado» y «2 de 3 apartados» son dos cosas.** El badge es el del
 * dominio —`Puesto::estaCaracterizado()`, que mira sólo las competencias porque
 * es lo que pide `mp.per.1`—; el recuento dice cuánto de la ficha está escrito.
 * Mezclarlos haría que la cifra del índice y la de esta pantalla discreparan.
 */
interface Puesto {
    id: number;
    codigo: string;
    titulo: string;
    reporta_a_id: number | null;
    reporta_a: string | null;
    mision: string | null;
    funciones: string | null;
    competencias: string | null;
    caracterizado: boolean;
}

interface Ocupante {
    id: number;
    nombre: string;
}

interface Dependiente {
    id: number;
    codigo: string;
    titulo: string;
    ocupantes: Ocupante[];
}

interface Asignacion {
    id: number;
    persona_id: number;
    persona: string;
    desde: string;
    hasta: string | null;
    vigente: boolean;
    nota: string | null;
}

const props = defineProps<{
    puesto: Puesto;
    dependientes: Dependiente[];
    asignaciones: Asignacion[];
    puedeGestionar: boolean;
}>();

const vigentes = computed(() => props.asignaciones.filter((una) => una.vigente));
const historicas = computed(() => props.asignaciones.filter((una) => !una.vigente));
const vacantes = computed(() => props.dependientes.filter((hijo) => hijo.ocupantes.length === 0).length);

/** Los tres apartados, siempre en este orden y siempre pintados: el que falta se ve. */
const apartados = computed(() => [
    {
        clave: 'mision',
        etiqueta: 'Misión',
        texto: props.puesto.mision,
        falta: 'Para qué existe el puesto, en una o dos frases.',
    },
    {
        clave: 'funciones',
        etiqueta: 'Funciones',
        texto: props.puesto.funciones,
        falta: 'Es lo que tendría que cubrir quien sustituya al titular.',
    },
    {
        clave: 'competencias',
        etiqueta: 'Competencias y requisitos',
        texto: props.puesto.competencias,
        falta: 'Qué formación y experiencia pide. Es lo que mira mp.per.1.',
    },
].map((apartado) => ({ ...apartado, relleno: (apartado.texto ?? '').trim() !== '' })));

const rellenos = computed(() => apartados.value.filter((apartado) => apartado.relleno).length);

/** Una línea por renglón: las funciones se escriben como lista aunque el campo sea texto. */
function renglones(texto: string | null): string[] {
    return (texto ?? '')
        .split('\n')
        .map((linea) => linea.trim())
        .filter((linea) => linea !== '');
}

function iniciales(nombre: string): string {
    return nombre
        .split(/\s+/)
        .filter((parte) => parte !== '')
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}

function cuenta(n: number, singular: string, plural: string): string {
    return `${n} ${n === 1 ? singular : plural}`;
}
</script>

<template>
    <AppLayout :titulo="puesto.titulo">
        <CabeceraPagina :titulo="puesto.titulo" :codigo="puesto.codigo">
            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-muted-foreground">
                <CeldaBadge
                    :valor="{
                        valor: puesto.caracterizado ? 'si' : 'no',
                        etiqueta: puesto.caracterizado ? 'Caracterizado' : 'Sin caracterizar',
                        tono: puesto.caracterizado ? 'implantado' : 'no_iniciado',
                        icono: puesto.caracterizado ? 'CheckCircle2' : 'CircleDashed',
                    }"
                />
                <span v-if="puesto.reporta_a">
                    Depende de
                    <Link
                        :href="`/puestos/${puesto.reporta_a_id}`"
                        class="text-primary underline-offset-4 hover:underline"
                    >{{ puesto.reporta_a }}</Link>
                </span>
                <span v-else>Raíz del organigrama</span>
                <span aria-hidden="true">·</span>
                <span>{{ vigentes.length === 0 ? 'Sin ocupar' : cuenta(vigentes.length, 'ocupante', 'ocupantes') }}</span>
                <template v-if="dependientes.length > 0">
                    <span aria-hidden="true">·</span>
                    <span>{{ cuenta(dependientes.length, 'puesto depende', 'puestos dependen') }} de él</span>
                </template>
            </div>

            <template #acciones>
                <Button as-child variant="outline">
                    <Link href="/puestos/organigrama">Ver el organigrama</Link>
                </Button>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/puestos/${puesto.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start">
            <div class="space-y-6">
                <Card>
                    <CardHeader class="flex flex-wrap items-start justify-between gap-4">
                        <div class="grid gap-1">
                            <CardTitle>Caracterización</CardTitle>
                            <p class="text-[13px] leading-[18px] text-muted-foreground">
                                Responde a
                                <span class="cifra rounded-full bg-muted px-1.5 py-px text-xs text-secondary-foreground">mp.per.1</span>
                                del ENS. En categoría básica no es exigible.
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1.5">
                            <span class="text-[13px] font-medium text-secondary-foreground">
                                <span class="cifra">{{ rellenos }}</span> de <span class="cifra">3</span> apartados
                            </span>
                            <div class="flex gap-0.5" aria-hidden="true">
                                <span
                                    v-for="apartado in apartados"
                                    :key="apartado.clave"
                                    class="h-1.5 w-7 rounded-full"
                                    :class="apartado.relleno ? 'bg-primary' : 'bg-muted'"
                                />
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <dl class="divide-y divide-border border-t border-border">
                            <div
                                v-for="apartado in apartados"
                                :key="apartado.clave"
                                class="grid gap-2 py-4 last:pb-0 sm:grid-cols-[13rem_minmax(0,1fr)] sm:gap-6"
                            >
                                <dt class="flex items-center gap-2 text-[13px] leading-[26px] font-medium text-muted-foreground">
                                    <Check v-if="apartado.relleno" class="size-3.5 text-estado-implantado" aria-hidden="true" />
                                    <CircleDashed v-else class="size-3.5 text-estado-no-iniciado" aria-hidden="true" />
                                    {{ apartado.etiqueta }}
                                    <span class="sr-only">{{ apartado.relleno ? '(escrito)' : '(sin escribir)' }}</span>
                                </dt>
                                <dd>
                                    <template v-if="apartado.relleno">
                                        <ul
                                            v-if="renglones(apartado.texto).length > 1"
                                            class="max-w-prose list-disc space-y-1 pl-5 text-base leading-[26px]"
                                        >
                                            <li v-for="(linea, i) in renglones(apartado.texto)" :key="i">{{ linea }}</li>
                                        </ul>
                                        <p v-else class="max-w-prose text-base leading-[26px] whitespace-pre-line">{{ apartado.texto }}</p>
                                    </template>
                                    <div
                                        v-else
                                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-md border border-dashed border-border bg-superficie px-4 py-3"
                                    >
                                        <p class="text-sm text-muted-foreground">Sin escribir. {{ apartado.falta }}</p>
                                        <Button v-if="puedeGestionar" as-child variant="link" size="sm">
                                            <Link :href="`/puestos/${puesto.id}/editar`">Escribirlo</Link>
                                        </Button>
                                    </div>
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-wrap items-center justify-between gap-2">
                        <CardTitle>Quién lo ocupa</CardTitle>
                        <span class="text-[13px] text-muted-foreground">Se asigna desde la ficha de la persona</span>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <EstadoVacio
                            v-if="vigentes.length === 0"
                            :icono="UserRound"
                            titulo="Sin ocupar hoy"
                            descripcion="Nadie tiene este puesto asignado. Se cubre desde la ficha de la persona, con fecha de inicio."
                            :accion="{ etiqueta: 'Ir a Personas', href: '/personas' }"
                        />

                        <ul v-else class="divide-y divide-border">
                            <li v-for="asignacion in vigentes" :key="asignacion.id" class="py-3 first:pt-0 last:pb-0">
                                <Link
                                    :href="`/personas/${asignacion.persona_id}`"
                                    class="group flex items-center gap-3"
                                >
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-semibold text-accent-foreground"
                                        aria-hidden="true"
                                    >{{ iniciales(asignacion.persona) }}</span>
                                    <span class="grid gap-0.5">
                                        <span class="text-sm font-medium underline-offset-4 group-hover:underline">{{ asignacion.persona }}</span>
                                        <span class="text-[13px] leading-[18px] text-muted-foreground">
                                            Desde el {{ fechaLegible(asignacion.desde) }}<template v-if="asignacion.nota"> · {{ asignacion.nota }}</template>
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        </ul>

                        <div class="space-y-2 border-t border-border pt-4">
                            <h3 class="text-[13px] leading-[18px] font-medium text-muted-foreground">Histórico de ocupación</h3>
                            <p v-if="historicas.length === 0" class="text-sm text-muted-foreground">
                                Sin asignaciones anteriores. Las asignaciones no se borran: cuando alguien deje el puesto, aparecerá aquí con su vigencia.
                            </p>
                            <ul v-else class="divide-y divide-border text-sm">
                                <li
                                    v-for="asignacion in historicas"
                                    :key="asignacion.id"
                                    class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2"
                                >
                                    <Link
                                        :href="`/personas/${asignacion.persona_id}`"
                                        class="underline-offset-4 hover:underline"
                                    >{{ asignacion.persona }}</Link>
                                    <span class="text-muted-foreground">
                                        {{ fechaLegible(asignacion.desde) }} – {{ fechaLegible(asignacion.hasta) }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                            <dt class="text-muted-foreground">Código</dt>
                            <dd class="cifra text-right">{{ puesto.codigo }}</dd>
                            <dt class="text-muted-foreground">Depende de</dt>
                            <dd class="text-right">
                                <Link
                                    v-if="puesto.reporta_a"
                                    :href="`/puestos/${puesto.reporta_a_id}`"
                                    class="text-primary underline-offset-4 hover:underline"
                                >{{ puesto.reporta_a }}</Link>
                                <template v-else>Ninguno · raíz</template>
                            </dd>
                            <dt class="text-muted-foreground">Ocupantes</dt>
                            <dd class="cifra text-right">{{ vigentes.length }}</dd>
                            <dt class="text-muted-foreground">Dependientes</dt>
                            <dd class="text-right">
                                <template v-if="dependientes.length === 0">Ninguno</template>
                                <template v-else>
                                    <span class="cifra">{{ dependientes.length }}</span>
                                    <template v-if="vacantes > 0">
                                        · <span class="cifra">{{ vacantes }}</span> {{ vacantes === 1 ? 'vacante' : 'vacantes' }}
                                    </template>
                                </template>
                            </dd>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex items-baseline justify-between">
                        <CardTitle>Dependen de éste</CardTitle>
                        <span v-if="dependientes.length > 0" class="cifra text-[13px] text-muted-foreground">{{ dependientes.length }}</span>
                    </CardHeader>
                    <CardContent class="text-sm" :class="dependientes.length > 0 ? 'px-0' : ''">
                        <p v-if="dependientes.length === 0" class="text-muted-foreground">
                            Ninguno. Es una hoja del organigrama.
                        </p>
                        <ul v-else class="border-t border-border">
                            <li v-for="hijo in dependientes" :key="hijo.id" class="border-b border-border last:border-b-0">
                                <Link
                                    :href="`/puestos/${hijo.id}`"
                                    class="group grid grid-cols-[4.5rem_minmax(0,1fr)] gap-x-2 gap-y-1 px-6 py-2.5 hover:bg-(--fila-hover)"
                                >
                                    <span class="cifra pt-px text-xs text-muted-foreground">{{ hijo.codigo }}</span>
                                    <span class="font-medium underline-offset-4 group-hover:underline">{{ hijo.titulo }}</span>
                                    <span class="col-start-2 text-[13px] text-muted-foreground">
                                        <template v-if="hijo.ocupantes.length > 0">
                                            {{ hijo.ocupantes.map((uno) => uno.nombre).join(', ') }}
                                        </template>
                                        <CeldaBadge
                                            v-else
                                            :valor="{ valor: 'vacante', etiqueta: 'Vacante', tono: 'no_iniciado', icono: 'CircleDashed' }"
                                        />
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
