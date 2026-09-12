<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
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
type EstadoTarea = App.Domain.Tarea.Enums.EstadoTarea;

const props = defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    alertas: App.Http.Resources.Panel.Indicador[];
    pendientes: App.Http.Resources.Panel.Indicador[];
    abiertas: number;
}>();

/*
 * Igual que en implantaciones: la acción masiva la resuelve la página, porque la
 * tabla genérica no puede saber que aquí hace falta elegir un estado.
 */
const seleccion = ref<(number | string)[]>([]);
const abierto = ref(false);
const destino = ref<EstadoTarea | null>(null);
const nota = ref('');
const enviando = ref(false);

/*
 * `descartada` no está, y no es un olvido: descartar exige decir por qué, y un
 * motivo escrito una vez para cincuenta tareas no es un motivo. Se descartan de
 * una en una, desde su ficha.
 */
const estados: { valor: EstadoTarea; etiqueta: string }[] = [
    { valor: 'pendiente', etiqueta: 'Pendiente' },
    { valor: 'en_curso', etiqueta: 'En curso' },
    { valor: 'bloqueada', etiqueta: 'Bloqueada' },
    { valor: 'hecha', etiqueta: 'Hecha' },
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
        '/tareas/estado',
        { tareas: seleccion.value, estado: destino.value, nota: nota.value || null },
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
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <TiraIndicadores
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="abiertas"
            denominador-etiqueta="tareas abiertas"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" @masiva="abrir" />

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cambiar el estado</DialogTitle>
                    <DialogDescription>
                        {{ seleccion.length }} tareas seleccionadas. Las que no admitan la transición se quedarán como
                        están. Descartar no está aquí: exige un motivo y se hace desde la ficha.
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
                        ayuda="Se guarda en el histórico junto a la transición."
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
