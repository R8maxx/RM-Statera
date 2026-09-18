<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ConmutadorContexto from '@/components/contexto/ConmutadorContexto.vue';
import MatrizDafo from '@/components/contexto/MatrizDafo.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { tono } from '@/lib/tonos';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * El panorama del contexto: la cara del § 4.1.
 *
 * Tres bloques y en este orden, que es el de la norma: **qué hay firmado y desde
 * cuándo**, **qué dice el DAFO** y **hasta dónde llega el SGSI**. La pregunta que
 * un auditor hace primero es la primera, y por eso la tarjeta del análisis va
 * arriba aunque la matriz sea lo que se mira a diario.
 *
 * **El alcance se enseña y no se edita aquí.** La cláusula 4.3 vive en
 * `sistemas.alcance_declarado` desde la primera migración y ya se imprime en la
 * portada de los cuatro documentos; repetir el campo aquí sería el mismo dato en
 * dos pantallas que pueden discrepar. Lo que este módulo le añade es histórico: al
 * aprobar, se congela en la instantánea.
 */

interface Estado {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface Analisis {
    id: number;
    numero: number | null;
    etiqueta: string;
    fechaAnalisis: string;
    estado: Estado;
    climaPertinente: boolean | null;
    climaJustificacion: string | null;
    nota: string | null;
    creadoPor: string | null;
    aprobadoPor: string | null;
    aprobadoEn: string | null;
    tieneInstantanea: boolean;
}

interface Sistema {
    id: number;
    codigo: string;
    nombre: string;
    marco: string | null;
    alcanceDeclarado: string | null;
    exclusiones: string | null;
}

/*
 * Repetidas aquí y en `MatrizDafo`, a propósito y sin compartirlas: un SFC no
 * exporta tipos desde `<script setup>` sin un segundo bloque de script, y montar
 * ese andamiaje para dos interfaces que el servidor ya fija en
 * `ContextoController` cuesta más de lo que ahorra.
 */
interface Cuestion {
    id: number;
    codigo: string;
    titulo: string;
    tipo: string;
    tipoEtiqueta: string;
    tono: string;
    icono: string;
    materia: string;
    esClimatica: boolean;
    responsable: string | null;
    riesgos: number;
    tareas: number;
}

interface TipoEje {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    signo: string;
    signoEtiqueta: string;
}

interface AmbitoEje {
    valor: string;
    etiqueta: string;
    ayuda: string;
    tipos: TipoEje[];
}

const props = defineProps<{
    vigente: Analisis | null;
    borrador: Analisis | null;
    dafo: Record<string, Cuestion[]>;
    ejes: { ambitos: AmbitoEje[] };
    alcance: Sistema[];
    resumen: App.Http.Resources.Panel.ResumenContextoPanel;
    puedeGestionar: boolean;
    puedeAprobar: boolean;
}>();

/* El borrador manda cuando existe: es lo que se está escribiendo ahora mismo. */
const enCurso = computed(() => props.borrador ?? props.vigente);

const editando = ref(false);
const enviando = ref(false);
const fecha = ref('');
const nota = ref('');
const clima = ref<'' | 'si' | 'no'>('');
const climaJustificacion = ref('');

function abrirEdicion(): void {
    const base = enCurso.value;
    fecha.value = base?.fechaAnalisis ?? new Date().toISOString().slice(0, 10);
    nota.value = base?.nota ?? '';
    clima.value = base?.climaPertinente === null || base?.climaPertinente === undefined
        ? ''
        : base.climaPertinente
          ? 'si'
          : 'no';
    climaJustificacion.value = base?.climaJustificacion ?? '';
    editando.value = true;
}

function guardar(): void {
    enviando.value = true;

    router.put(
        '/contexto/analisis',
        {
            fecha_analisis: fecha.value,
            nota: nota.value || null,
            // Cadena vacía es «sin contestar», que no es lo mismo que «no es
            // pertinente»: la enmienda 1:2024 obliga a determinarlo, y las dos
            // respuestas tienen que poder distinguirse.
            clima_pertinente: clima.value === '' ? null : clima.value === 'si',
            clima_justificacion: climaJustificacion.value || null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
            },
            onSuccess: () => {
                editando.value = false;
            },
        },
    );
}

function aprobar(): void {
    if (!props.borrador) {
        return;
    }

    enviando.value = true;
    router.post(
        `/contexto/analisis/${props.borrador.id}/aprobacion`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
            },
        },
    );
}

const climaTexto = computed(() => {
    const base = enCurso.value;

    if (!base || base.climaPertinente === null) {
        return null;
    }

    return base.climaPertinente
        ? 'Sí, es una cuestión pertinente.'
        : 'No es una cuestión pertinente.';
});
</script>

<template>
    <AppLayout titulo="Contexto de la organización">
        <CabeceraPagina
            titulo="Contexto de la organización"
            descripcion="Lo que la organización tiene a favor y en contra, quién le exige qué y hasta dónde llega el SGSI. Cláusulas 4.1 a 4.3 de ISO 27001."
        >
            <template #acciones>
                <ConmutadorContexto vista="matriz" />
            </template>
        </CabeceraPagina>

        <div class="space-y-6">
            <!-- 1. Qué hay firmado y desde cuándo. -->
            <Card>
                <CardHeader class="flex flex-row flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <CardTitle class="flex flex-wrap items-center gap-2">
                            <span>{{ enCurso?.etiqueta ?? 'Sin análisis del contexto' }}</span>
                            <span
                                v-if="enCurso"
                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="tono(enCurso.estado.tono).badge"
                            >
                                <IconoTipo :nombre="enCurso.estado.icono" />
                                {{ enCurso.estado.etiqueta }}
                            </span>
                        </CardTitle>
                        <p v-if="enCurso" class="mt-1 text-sm text-muted-foreground">
                            Analizado el {{ enCurso.fechaAnalisis }}<template v-if="enCurso.aprobadoPor">, aprobado por {{ enCurso.aprobadoPor }}</template>.
                        </p>
                        <p v-else class="mt-1 text-sm text-muted-foreground">
                            Nadie ha declarado todavía cuál es el contexto de la organización. Es la
                            cláusula 4.1, y de ella cuelgan el alcance del SGSI y la apreciación de riesgos.
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <!--
                            «Empezar» cuando no hay borrador, aunque haya un
                            análisis vigente: guardar estrena una revisión nueva
                            partiendo de la anterior, no reescribe la firmada — que
                            además el trigger no dejaría.
                        -->
                        <Button v-if="puedeGestionar" variant="outline" size="sm" @click="abrirEdicion">
                            {{ borrador ? 'Editar la revisión' : 'Empezar una revisión' }}
                        </Button>
                        <Button
                            v-if="puedeAprobar && borrador"
                            variant="acento"
                            size="sm"
                            :disabled="enviando"
                            @click="aprobar"
                        >
                            Aprobar y congelar
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4">
                    <!--
                        La declaración del cambio climático, en su propio bloque y
                        no como una línea más: la enmienda 1:2024 obliga a
                        determinar si es pertinente, y «no lo hemos mirado» y «lo
                        hemos mirado y no aplica» tienen que poder distinguirse.
                    -->
                    <div class="rounded-xl bg-muted/40 p-4">
                        <h3 class="text-sm font-semibold">Cambio climático</h3>
                        <p v-if="climaTexto" class="mt-1 text-sm">{{ climaTexto }}</p>
                        <p v-else class="mt-1 text-sm text-muted-foreground">
                            Sin contestar. La enmienda 1:2024 obliga a determinar si es una cuestión
                            pertinente, y el análisis no se puede aprobar hasta que se diga.
                        </p>
                        <p v-if="enCurso?.climaJustificacion" class="mt-2 text-sm text-muted-foreground">
                            {{ enCurso.climaJustificacion }}
                        </p>
                    </div>

                    <p v-if="enCurso?.nota" class="text-sm text-muted-foreground">{{ enCurso.nota }}</p>

                    <p v-if="borrador && vigente" class="text-sm text-muted-foreground">
                        Hay una revisión en curso sobre el
                        <Link :href="`/contexto/analisis/${vigente.id}`" class="text-primary hover:underline">
                            {{ vigente.etiqueta.toLowerCase() }}
                        </Link>, que sigue siendo el vigente hasta que se apruebe la nueva.
                    </p>
                </CardContent>
            </Card>

            <!-- 2. El DAFO. -->
            <section aria-labelledby="titulo-dafo" class="space-y-3">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 id="titulo-dafo" class="text-base font-semibold">
                        Cuestiones internas y externas
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ resumen.cuestiones }} vigentes<template v-if="resumen.sinRiesgo > 0">
                            · {{ resumen.sinRiesgo }} adversas sin riesgo vinculado</template>
                    </p>
                </div>

                <MatrizDafo :ambitos="ejes.ambitos" :dafo="dafo" />
            </section>

            <!-- 3. Hasta dónde llega. -->
            <section aria-labelledby="titulo-alcance" class="space-y-3">
                <h2 id="titulo-alcance" class="text-base font-semibold">Alcance declarado</h2>

                <p v-if="alcance.length === 0" class="text-sm text-muted-foreground">
                    No hay ningún sistema activo, así que no hay alcance que declarar todavía.
                </p>

                <div v-else class="grid gap-3 md:grid-cols-2">
                    <Card v-for="sistema in alcance" :key="sistema.id">
                        <CardHeader>
                            <CardTitle class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="cifra text-xs text-muted-foreground">{{ sistema.codigo }}</span>
                                <span>{{ sistema.nombre }}</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p v-if="sistema.alcanceDeclarado">{{ sistema.alcanceDeclarado }}</p>
                            <p v-else class="text-muted-foreground">
                                Sin alcance declarado.
                                <Link :href="`/sistemas/${sistema.id}/editar`" class="text-primary hover:underline">
                                    Escribirlo
                                </Link>
                            </p>
                            <p v-if="sistema.exclusiones" class="text-muted-foreground">
                                <strong class="font-medium text-foreground">Exclusiones:</strong>
                                {{ sistema.exclusiones }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>

        <Dialog v-model:open="editando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Revisión del contexto</DialogTitle>
                    <DialogDescription>
                        Se guarda en el borrador. Lo que lo convierte en el contexto vigente de la
                        organización es aprobarlo, y eso lo congela.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="fecha-analisis">Fecha del análisis</Label>
                        <input
                            id="fecha-analisis"
                            v-model="fecha"
                            type="date"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="clima">¿Es pertinente el cambio climático?</Label>
                        <select
                            id="clima"
                            v-model="clima"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">Sin contestar</option>
                            <option value="si">Sí, es pertinente</option>
                            <option value="no">No es pertinente</option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            La enmienda 1:2024 obliga a determinarlo. «No es pertinente» es una
                            respuesta válida siempre que venga razonada.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="clima-justificacion">Razonamiento sobre el clima</Label>
                        <textarea
                            id="clima-justificacion"
                            v-model="climaJustificacion"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="nota-analisis">Cómo se hizo</Label>
                        <textarea
                            id="nota-analisis"
                            v-model="nota"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <p class="text-xs text-muted-foreground">
                            Quién participó, qué fuentes se miraron. Es lo que el auditor pregunta
                            cuando quiere saber si el análisis lo hizo alguien o salió de una plantilla.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="enviando" @click="editando = false">Cancelar</Button>
                    <Button :disabled="enviando" @click="guardar">Guardar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
