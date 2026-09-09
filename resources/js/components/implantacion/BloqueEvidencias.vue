<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatoFecha } from '@/lib/celdas';
import type { Opcion } from '@/lib/formularios';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export interface EvidenciaVinculada {
    id: number;
    titulo: string;
    tipo: string;
    esFichero: boolean;
    fecha_obtencion: string;
    fecha_caducidad: string | null;
    haCaducado: boolean;
    nota: string | null;
}

/**
 * Las pruebas de que este requisito se cumple.
 *
 * Se adjunta una evidencia que ya está en el repositorio, no se sube una por
 * requisito: el invariante 6 dice que la misma captura prueba un control de ISO
 * y tres medidas del ENS, y subirla cuatro veces sería volver a las hojas de
 * cálculo duplicadas con otra interfaz.
 *
 * La lista de candidatas no viaja con la ficha: se pide al abrir el diálogo con
 * una recarga parcial, porque el repositorio puede tener cientos y aquí se
 * enseñan tres.
 */
const props = defineProps<{
    implantacionId: number;
    evidencias: EvidenciaVinculada[];
    /** Llega sólo cuando se pide: prop opcional de Inertia. */
    disponibles?: Opcion[];
}>();

const abierto = ref(false);
const cargando = ref(false);

const vincular = useForm({ evidencia_id: undefined as string | undefined, nota: '' });

const candidatas = computed<Opcion[]>(() => props.disponibles ?? []);

function abrir(): void {
    abierto.value = true;
    cargando.value = true;

    router.reload({
        only: ['evidenciasDisponibles'],
        onFinish: () => (cargando.value = false),
    });
}

function confirmar(): void {
    vincular.post(`/implantaciones/${props.implantacionId}/evidencias`, {
        preserveScroll: true,
        onSuccess: () => {
            vincular.reset();
            abierto.value = false;
        },
    });
}

function desvincular(evidenciaId: number): void {
    router.delete(`/implantaciones/${props.implantacionId}/evidencias/${evidenciaId}`, {
        preserveScroll: true,
    });
}

const fecha = (valor: string): string => formatoFecha.format(new Date(valor));
</script>

<template>
    <div class="space-y-4">
        <ul v-if="evidencias.length > 0" class="divide-y divide-border">
            <li
                v-for="evidencia in evidencias"
                :key="evidencia.id"
                class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0"
            >
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="`/evidencias/${evidencia.id}`"
                            class="text-sm font-medium underline-offset-4 hover:underline"
                        >
                            {{ evidencia.titulo }}
                        </Link>
                        <CeldaBadge
                            v-if="evidencia.haCaducado"
                            :valor="{ valor: evidencia.fecha_caducidad, etiqueta: 'Caducada', tono: 'caducada' }"
                        />
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ evidencia.tipo }} · obtenida el {{ fecha(evidencia.fecha_obtencion) }}
                        <template v-if="evidencia.fecha_caducidad">
                            · caduca el {{ fecha(evidencia.fecha_caducidad) }}
                        </template>
                    </p>
                    <p v-if="evidencia.nota" class="mt-1 text-sm">{{ evidencia.nota }}</p>
                </div>

                <Button variant="ghost" size="sm" @click="desvincular(evidencia.id)">Desvincular</Button>
            </li>
        </ul>

        <p v-else class="text-sm text-muted-foreground">
            Este requisito no tiene ninguna prueba detrás. Está implantado o no según lo que diga su estado, pero
            ante un auditor no se puede demostrar.
        </p>

        <div class="flex flex-wrap gap-2">
            <Button variant="outline" @click="abrir">Adjuntar una evidencia</Button>
            <Link href="/evidencias/crear">
                <Button variant="ghost">Registrar una nueva</Button>
            </Link>
        </div>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Adjuntar una evidencia</DialogTitle>
                    <DialogDescription>
                        Del repositorio. La misma evidencia puede probar este requisito y varios de otros marcos.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <p v-if="cargando" class="text-sm text-muted-foreground">Cargando el repositorio…</p>

                    <p v-else-if="candidatas.length === 0" class="text-sm text-muted-foreground">
                        No queda ninguna evidencia por adjuntar a este requisito.
                    </p>

                    <template v-else>
                        <CampoSelect
                            v-model="vincular.evidencia_id"
                            nombre="evidencia_id"
                            etiqueta="Evidencia"
                            :opciones="candidatas"
                            :error="vincular.errors.evidencia_id"
                            placeholder="Elige del repositorio"
                            requerido
                        />

                        <CampoTextarea
                            v-model="vincular.nota"
                            nombre="nota"
                            etiqueta="Nota"
                            :filas="2"
                            :error="vincular.errors.nota"
                            ayuda="Qué parte de este requisito prueba. Una evidencia que cubre cuatro medidas las cubre por motivos distintos."
                        />
                    </template>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button
                        :disabled="vincular.processing || !vincular.evidencia_id"
                        @click="confirmar"
                    >
                        {{ vincular.processing ? 'Adjuntando…' : 'Adjuntar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
