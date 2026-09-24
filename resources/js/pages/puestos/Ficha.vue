<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

/**
 * La ficha de puesto.
 *
 * Enseña quién lo ocupa y quién lo ocupó, y **no lo edita**: la asignación se
 * gestiona desde la ficha de la persona, que es donde se pregunta «¿qué hace
 * ésta?» — mucho más a menudo que «¿quién ocupa esto?». Tenerlo en los dos
 * sitios sería dos formularios que escriben la misma tabla.
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

interface Dependiente {
    id: number;
    codigo: string;
    titulo: string;
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

const vigentes = () => props.asignaciones.filter((una) => una.vigente);
const historicas = () => props.asignaciones.filter((una) => !una.vigente);
</script>

<template>
    <AppLayout :titulo="puesto.titulo">
        <CabeceraPagina :titulo="puesto.titulo">
            <template #acciones>
                <Button as-child variant="outline">
                    <Link href="/puestos/organigrama">Ver el organigrama</Link>
                </Button>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/puestos/${puesto.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: puesto.caracterizado ? 'si' : 'no',
                    etiqueta: puesto.caracterizado ? 'Caracterizado' : 'Sin caracterizar',
                    tono: puesto.caracterizado ? 'implantado' : 'no_iniciado',
                    icono: puesto.caracterizado ? 'CheckCircle2' : 'CircleDashed',
                }"
            />
            <span class="cifra text-sm text-muted-foreground">{{ puesto.codigo }}</span>
            <span v-if="puesto.reporta_a" class="text-sm text-muted-foreground">
                Depende de
                <Link
                    :href="`/puestos/${puesto.reporta_a_id}`"
                    class="underline-offset-4 hover:underline"
                >{{ puesto.reporta_a }}</Link>
            </span>
            <span v-else class="text-sm text-muted-foreground">No depende de ningún puesto</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>La caracterización</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <EstadoVacio
                            v-if="!puesto.mision && !puesto.funciones && !puesto.competencias"
                            titulo="Sin caracterizar"
                            descripcion="Es lo que mp.per.1 llama «caracterización del puesto». En categoría básica no es exigible, pero es lo que contesta quién puede ocupar esto cuando hay que cubrirlo."
                        />

                        <template v-else>
                            <div v-if="puesto.mision" class="grid gap-1">
                                <h3 class="text-muted-foreground">Misión</h3>
                                <p class="whitespace-pre-line">{{ puesto.mision }}</p>
                            </div>
                            <div v-if="puesto.funciones" class="grid gap-1">
                                <h3 class="text-muted-foreground">Funciones</h3>
                                <p class="whitespace-pre-line">{{ puesto.funciones }}</p>
                            </div>
                            <div v-if="puesto.competencias" class="grid gap-1">
                                <h3 class="text-muted-foreground">Competencias y requisitos</h3>
                                <p class="whitespace-pre-line">{{ puesto.competencias }}</p>
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Quién lo ocupa</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <p class="text-muted-foreground">
                            Se asigna desde la ficha de la persona, que es donde se mira. Las
                            asignaciones llevan vigencia y no se borran: la pregunta del auditor es
                            desde cuándo, y también hasta cuándo.
                        </p>

                        <EstadoVacio
                            v-if="vigentes().length === 0"
                            titulo="Sin ocupar"
                            descripcion="Nadie tiene este puesto asignado hoy."
                        />

                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="asignacion in vigentes()"
                                :key="asignacion.id"
                                class="flex flex-wrap items-center gap-2 py-2"
                            >
                                <Link
                                    :href="`/personas/${asignacion.persona_id}`"
                                    class="font-medium underline-offset-4 hover:underline"
                                >{{ asignacion.persona }}</Link>
                                <span class="text-muted-foreground">desde el {{ asignacion.desde }}</span>
                                <span v-if="asignacion.nota" class="text-muted-foreground">· {{ asignacion.nota }}</span>
                            </li>
                        </ul>

                        <div v-if="historicas().length > 0" class="space-y-2">
                            <h3 class="text-muted-foreground">Lo ocuparon antes</h3>
                            <ul class="divide-y divide-border">
                                <li
                                    v-for="asignacion in historicas()"
                                    :key="asignacion.id"
                                    class="flex flex-wrap items-center gap-2 py-2 text-muted-foreground"
                                >
                                    <Link
                                        :href="`/personas/${asignacion.persona_id}`"
                                        class="underline-offset-4 hover:underline"
                                    >{{ asignacion.persona }}</Link>
                                    <span>del {{ asignacion.desde }} al {{ asignacion.hasta }}</span>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Puestos que dependen de éste</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <p v-if="dependientes.length === 0" class="text-muted-foreground">
                            Ninguno. Es una hoja del organigrama.
                        </p>
                        <ul v-else class="divide-y divide-border">
                            <li v-for="hijo in dependientes" :key="hijo.id" class="py-2">
                                <Link
                                    :href="`/puestos/${hijo.id}`"
                                    class="underline-offset-4 hover:underline"
                                >
                                    <span class="cifra text-muted-foreground">{{ hijo.codigo }}</span>
                                    {{ hijo.titulo }}
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
