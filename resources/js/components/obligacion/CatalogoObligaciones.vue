<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Proponible {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    baseLegal: string | null;
    marco: string | null;
    cadencia: string;
}

/**
 * Lo que el catálogo propone a esta organización y nadie ha asumido.
 *
 * **Propone, no obliga.** La lista ya viene filtrada por marco, por las banderas
 * del ENS y por la categoría derivada de los sistemas, así que aquí no sale nada
 * que no le toque; lo que falta es que alguien lo acepte, con la fecha desde la
 * que corre el reloj — que es lo único que la herramienta no puede saber.
 *
 * **La base legal va impresa y no escondida en una ayuda**: es lo que separa esta
 * lista de una de buenas intenciones, y es lo primero que se comprueba.
 */
defineProps<{
    obligaciones: Proponible[];
    sistemas: Opcion[];
    responsables: Opcion[];
}>();

const abierta = ref<number | null>(null);

const formulario = useForm({
    computa_desde: new Date().toISOString().slice(0, 10),
    sistema_id: '',
    responsable_id: '',
});

function asumir(id: number): void {
    formulario.post(`/obligaciones/asumir/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Card class="mb-6 p-5">
        <h2 class="text-base font-medium">Del catálogo</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Lo periódico que los marcos de esta organización exigen. Asumir una copia su cadencia; lo
            único que hay que decir es desde cuándo se cuenta.
        </p>

        <ul class="mt-4 divide-y">
            <li v-for="obligacion in obligaciones" :key="obligacion.id" class="py-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ obligacion.nombre }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            <span class="font-mono">{{ obligacion.codigo }}</span>
                            · {{ obligacion.cadencia }}
                            <template v-if="obligacion.baseLegal"> · {{ obligacion.baseLegal }}</template>
                        </p>
                        <p v-if="obligacion.descripcion" class="mt-1 max-w-prose text-sm text-muted-foreground">
                            {{ obligacion.descripcion }}
                        </p>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        @click="abierta = abierta === obligacion.id ? null : obligacion.id"
                    >
                        Asumir
                    </Button>
                </div>

                <!--
                    Los tres datos que la herramienta no puede deducir. Aparecen
                    al pulsar y no siempre: siete formularios abiertos a la vez
                    convierten una lista en un cuestionario.
                -->
                <div v-if="abierta === obligacion.id" class="mt-3 grid gap-3 sm:grid-cols-3">
                    <label class="text-xs">
                        <span class="mb-1 block text-muted-foreground">Desde cuándo se cuenta</span>
                        <input
                            v-model="formulario.computa_desde"
                            type="date"
                            class="h-9 w-full rounded-md border bg-transparent px-2 text-sm"
                        />
                    </label>

                    <label class="text-xs">
                        <span class="mb-1 block text-muted-foreground">Responsable</span>
                        <select
                            v-model="formulario.responsable_id"
                            class="h-9 w-full rounded-md border bg-transparent px-2 text-sm"
                        >
                            <option value="">Sin asignar</option>
                            <option v-for="opcion in responsables" :key="opcion.valor" :value="opcion.valor">
                                {{ opcion.etiqueta }}
                            </option>
                        </select>
                    </label>

                    <label class="text-xs">
                        <span class="mb-1 block text-muted-foreground">Sistema</span>
                        <select
                            v-model="formulario.sistema_id"
                            class="h-9 w-full rounded-md border bg-transparent px-2 text-sm"
                        >
                            <option value="">La organización entera</option>
                            <option v-for="opcion in sistemas" :key="opcion.valor" :value="opcion.valor">
                                {{ opcion.etiqueta }}
                            </option>
                        </select>
                    </label>

                    <div class="sm:col-span-3">
                        <Button size="sm" :disabled="formulario.processing" @click="asumir(obligacion.id)">
                            Asumir «{{ obligacion.nombre }}»
                        </Button>
                    </div>
                </div>
            </li>
        </ul>
    </Card>
</template>
