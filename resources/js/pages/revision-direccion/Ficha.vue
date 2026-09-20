<script setup lang="ts">
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import EntradasRevision from '@/components/revision/EntradasRevision.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Destino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface Decision {
    id: number;
    titulo: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    responsable: string | null;
    plazoEtiqueta: string;
    plazoTono: string;
    fecha: string | null;
    coste: string | null;
}

interface Revision {
    id: number;
    codigo: string;
    fecha: string;
    fechaLarga: string;
    periodo: string;
    asistentes: string | null;
    conclusiones: string | null;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    admiteCambios: boolean;
    aprobadaPor: string | null;
    aprobadaEn: string | null;
}

/**
 * La ficha de una revisión por la dirección: § 4.15 y la cláusula 9.3.
 *
 * **Las entradas se enseñan en vivo mientras la revisión está abierta, y
 * congeladas cuando el acta está firmada.** Es la distinción que hace el módulo:
 * antes de firmar, lo que se mira es cómo está la cosa hoy —que es para lo que se
 * convoca la reunión—; después, lo que se mira es lo que se revisó aquel día.
 * Mezclar las dos haría que el acta cambiara sola.
 */
const props = defineProps<{
    revision: Revision;
    entradas: Record<string, Record<string, unknown> | undefined>;
    congeladas: boolean;
    decisiones: Decision[];
    coste: { total: string; sinEstimar: number };
    transiciones: Destino[];
    puedeAprobar: boolean;
    puedeGestionar: boolean;
    prioridades: Opcion[];
    responsables: Opcion[];
}>();

const enviando = ref(false);

function mover(paso: Destino): void {
    enviando.value = true;

    router.post(
        `/revision-direccion/${props.revision.id}/estado`,
        { estado: paso.valor },
        { preserveScroll: true, onFinish: () => (enviando.value = false) },
    );
}

function aprobar(): void {
    enviando.value = true;

    router.post(
        `/revision-direccion/${props.revision.id}/aprobacion`,
        {},
        { preserveScroll: true, onFinish: () => (enviando.value = false) },
    );
}

/* --- Las salidas (9.3.3) --- */

const abierto = ref(false);

const decision = useForm({
    titulo: '',
    descripcion: '',
    prioridad: 'media',
    responsable_id: '',
    fecha_limite: '',
    coste_estimado: '',
});

function abrirDecision(): void {
    decision.reset();
    decision.clearErrors();
    abierto.value = true;
}

function crearDecision(): void {
    decision.post(`/revision-direccion/${props.revision.id}/decisiones`, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            decision.reset();
        },
    });
}

function desvincular(id: number): void {
    router.delete(`/revision-direccion/${props.revision.id}/decisiones/${id}`, {
        preserveScroll: true,
    });
}

const abiertas = computed(
    () =>
        props.decisiones.filter((item) => item.estado !== 'hecha' && item.estado !== 'descartada')
            .length,
);
</script>

<template>
    <AppLayout :titulo="revision.codigo">
        <CabeceraPagina :titulo="revision.codigo" :descripcion="`Periodo revisado ${revision.periodo}`">
            <template #acciones>
                <Button v-if="puedeGestionar && revision.admiteCambios" as-child variant="outline">
                    <Link :href="`/revision-direccion/${revision.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: revision.estado,
                    etiqueta: revision.estadoEtiqueta,
                    tono: revision.estadoTono,
                    icono: revision.estadoIcono,
                }"
            />
            <span class="text-sm text-muted-foreground">
                Celebrada el {{ revision.fechaLarga }}
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Entradas de la revisión</CardTitle>
                        <CardDescription>
                            <template v-if="congeladas">
                                Las siete entradas de la cláusula 9.3.2, tal como se recogieron al
                                aprobar el acta. No cambian aunque los registros sigan vivos.
                            </template>
                            <template v-else>
                                Las siete entradas de la cláusula 9.3.2, ahora mismo. Se congelan al
                                aprobar el acta y a partir de ahí no cambian.
                            </template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <EntradasRevision :entradas="entradas" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Decisiones y acciones</CardTitle>
                        <CardDescription>
                            Las salidas de la revisión (9.3.3). Son tareas del plan de acción, y su
                            estado será la primera entrada del acta siguiente: es lo que permite
                            comprobar un año después si lo que se decidió se hizo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="decisiones.length === 0"
                            titulo="Sin decisiones"
                            descripcion="La cláusula 9.3.3 pide dejar constancia de las decisiones sobre oportunidades de mejora y sobre cambios en el sistema de gestión."
                        />

                        <template v-else>
                            <p class="text-sm">
                                <span class="cifra text-2xl font-semibold">{{ abiertas }}</span>
                                <span class="text-muted-foreground">
                                    de {{ decisiones.length }} abiertas · {{ coste.total }}
                                    <template v-if="coste.sinEstimar > 0">
                                        ({{ coste.sinEstimar }} sin estimar)
                                    </template>
                                </span>
                            </p>

                            <ul class="divide-y divide-border">
                                <li
                                    v-for="item in decisiones"
                                    :key="item.id"
                                    class="flex items-start justify-between gap-4 py-3"
                                >
                                    <div class="space-y-1">
                                        <Link
                                            :href="`/tareas/${item.id}`"
                                            class="text-sm font-medium underline-offset-4 hover:underline"
                                        >
                                            {{ item.titulo }}
                                        </Link>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <CeldaBadge
                                                :valor="{
                                                    valor: item.estado,
                                                    etiqueta: item.estadoEtiqueta,
                                                    tono: item.estadoTono,
                                                    icono: item.estadoIcono,
                                                }"
                                            />
                                            <CeldaBadge
                                                :valor="{
                                                    valor: 'plazo',
                                                    etiqueta: item.plazoEtiqueta,
                                                    tono: item.plazoTono,
                                                    icono: null,
                                                }"
                                            />
                                            <span
                                                v-if="item.responsable"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ item.responsable }}
                                            </span>
                                        </div>
                                    </div>
                                    <Button
                                        v-if="puedeGestionar"
                                        variant="ghost"
                                        size="sm"
                                        @click="desvincular(item.id)"
                                    >
                                        Desvincular
                                    </Button>
                                </li>
                            </ul>
                        </template>

                        <!--
                            Se pueden registrar decisiones sobre un acta ya firmada:
                            el trigger blinda el acta, no lo que cuelga de ella, y una
                            decisión se ejecuta en las semanas siguientes.
                        -->
                        <Button v-if="puedeGestionar" variant="outline" @click="abrirDecision">
                            Registrar decisión
                        </Button>
                    </CardContent>
                </Card>

                <Card v-if="revision.conclusiones">
                    <CardHeader>
                        <CardTitle>Conclusiones</CardTitle>
                        <CardDescription>
                            La única parte del acta que escribe una persona.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm whitespace-pre-line">{{ revision.conclusiones }}</p>
                    </CardContent>
                </Card>
            </div>

            <div class="h-fit space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>La reunión</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Periodo revisado</dt>
                            <dd>{{ revision.periodo }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Asistentes</dt>
                            <!--
                                «Sin registrar» y no un hueco: quién asistió es lo
                                primero que un auditor comprueba, y una línea ausente
                                se lee como que el dato no aplica.
                            -->
                            <dd :class="revision.asistentes ? '' : 'text-muted-foreground'">
                                {{ revision.asistentes ?? 'Sin registrar' }}
                            </dd>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Aprobación del acta</CardTitle>
                        <CardDescription>
                            Aprobar es lo que congela las siete entradas. A partir de ahí el acta no
                            se modifica.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <template v-if="revision.aprobadaEn">
                            <p class="text-muted-foreground">
                                {{ revision.aprobadaEn }}
                                <template v-if="revision.aprobadaPor">
                                    · {{ revision.aprobadaPor }}
                                </template>
                            </p>
                        </template>
                        <p v-else class="text-muted-foreground">
                            Todavía sin aprobar. Hasta que se firme, lo que se ve arriba es la
                            situación de hoy y no la del día de la reunión.
                        </p>

                        <Button v-if="puedeAprobar" variant="acento" :disabled="enviando" @click="aprobar">
                            Aprobar el acta
                        </Button>
                    </CardContent>
                </Card>

                <Card v-if="puedeGestionar && transiciones.length > 0">
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                        <CardDescription>
                            De aprobada sólo se vuelve a «en curso»: decir que la reunión no se
                            celebró sería reescribir el pasado.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-2">
                            <BotonEstado
                                v-for="paso in transiciones"
                                :key="paso.valor"
                                :destino="paso"
                                :deshabilitado="enviando"
                                @click="mover(paso)"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar decisión</DialogTitle>
                    <DialogDescription>
                        Nace como tarea del plan de acción, con el origen ya puesto. Su estado será
                        la primera entrada del acta siguiente.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        nombre="titulo"
                        etiqueta="Decisión"
                        :error="decision.errors.titulo"
                        requerido
                        @input="decision.titulo = ($event.target as HTMLInputElement).value"
                    />
                    <CampoSelect
                        nombre="prioridad"
                        etiqueta="Prioridad"
                        :opciones="prioridades"
                        :valor-inicial="decision.prioridad"
                        :error="decision.errors.prioridad"
                        requerido
                        @update:model-value="(valor?: string) => (decision.prioridad = valor ?? 'media')"
                    />
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="decision.errors.responsable_id"
                        @update:model-value="(valor?: string) => (decision.responsable_id = valor ?? '')"
                    />
                    <CampoTexto
                        nombre="fecha_limite"
                        etiqueta="Fecha límite"
                        tipo="date"
                        :error="decision.errors.fecha_limite"
                        @input="decision.fecha_limite = ($event.target as HTMLInputElement).value"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="decision.processing" @click="crearDecision">Registrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
