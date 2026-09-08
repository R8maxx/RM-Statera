<script setup lang="ts">
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import { Button } from '@/components/ui/button';
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
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Accion = App.Http.Resources.Definicion.Accion;
type EstadoImplantacion = App.Domain.Implantacion.Enums.EstadoImplantacion;

defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
}>();

/*
 * La acción masiva la resuelve la página, no el `DataTable`: la tabla genérica
 * no puede saber que aquí hace falta elegir un estado y escribir una nota. Lo
 * que la tabla aporta es la acción y la selección.
 */
const seleccion = ref<(number | string)[]>([]);
const abierto = ref(false);
const destino = ref<EstadoImplantacion | null>(null);
const nota = ref('');
const enviando = ref(false);

/** `no_aplica` no está: lo deriva el motor tras un recálculo, nunca una persona. */
const estados: { valor: EstadoImplantacion; etiqueta: string }[] = [
    { valor: 'no_iniciado', etiqueta: 'No iniciado' },
    { valor: 'planificado', etiqueta: 'Planificado' },
    { valor: 'en_progreso', etiqueta: 'En progreso' },
    { valor: 'implantado', etiqueta: 'Implantado' },
];

function abrir(_accion: Accion, ids: (number | string)[]): void {
    seleccion.value = ids;
    destino.value = null;
    nota.value = '';
    abierto.value = true;
}

function confirmar(): void {
    if (destino.value === null) {
        return;
    }

    router.post(
        '/implantaciones/estado',
        { implantaciones: seleccion.value, estado: destino.value, nota: nota.value || null },
        {
            preserveScroll: true,
            onStart: () => (enviando.value = true),
            onFinish: () => {
                enviando.value = false;
                abierto.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :titulo="recurso.etiquetas.plural">
        <p v-if="recurso.etiquetas.descripcion" class="mb-4 max-w-3xl text-sm text-muted-foreground">
            {{ recurso.etiquetas.descripcion }}
        </p>

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" @masiva="abrir" />

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cambiar el estado</DialogTitle>
                    <DialogDescription>
                        {{ seleccion.length }} implantaciones seleccionadas. Las que no admitan la transición se
                        quedarán como están.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>Nuevo estado</Label>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="estado in estados"
                                :key="estado.valor"
                                type="button"
                                size="sm"
                                :variant="destino === estado.valor ? 'default' : 'outline'"
                                @click="destino = estado.valor"
                            >
                                {{ estado.etiqueta }}
                            </Button>
                        </div>
                    </div>

                    <CampoTextarea
                        v-model="nota"
                        nombre="nota"
                        etiqueta="Nota"
                        :filas="3"
                        ayuda="Se guarda en el histórico junto a la transición. El auditor pregunta desde cuándo, y por qué."
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="destino === null || enviando" @click="confirmar">Aplicar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
