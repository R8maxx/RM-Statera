<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Accion = App.Http.Resources.Definicion.Accion;

defineProps<{ accion: Accion }>();

const emit = defineEmits<{ cancelar: []; confirmar: [] }>();
</script>

<template>
    <Dialog :open="true" @update:open="(abierto: boolean) => !abierto && emit('cancelar')">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ accion.etiqueta }}</DialogTitle>
                <DialogDescription>{{ accion.confirmacion }}</DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button variant="outline" @click="emit('cancelar')">Cancelar</Button>
                <Button :variant="accion.destructiva ? 'destructive' : 'default'" @click="emit('confirmar')">
                    {{ accion.etiqueta }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
