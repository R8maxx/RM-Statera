<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import { Button } from '@/components/ui/button';
import type { Opcion } from '@/lib/formularios';
import { Link, router, useForm } from '@inertiajs/vue3';
import { NetworkIcon } from '@lucide/vue';

interface Servicio {
    id: number;
    codigo: string;
    nombre: string;
}

/**
 * Qué servicios cubre un plan de continuidad: § 4.11.
 *
 * Sobre el bloque de indicadores vinculados de `objetivos/Ficha.vue`, mismo
 * reparto: se vincula por un desplegable de lo que todavía no está cubierto,
 * nunca creando un activo desde aquí, y se desvincula con un botón por fila.
 * `puedeGestionar` es `documentos.redactar` visto desde el controlador —el
 * mismo permiso que guarda las dos rutas, así que ocultar el control es sólo
 * cortesía y nunca la autorización de verdad.
 */
const props = defineProps<{
    documentoId: number;
    servicios: Servicio[];
    disponibles: Opcion[];
    puedeGestionar: boolean;
}>();

const servicio = useForm({ activo_id: '' });

function vincular(): void {
    servicio.post(`/documentos/${props.documentoId}/servicios`, {
        preserveScroll: true,
        onSuccess: () => servicio.reset(),
    });
}

function desvincular(activoId: number): void {
    router.delete(`/documentos/${props.documentoId}/servicios/${activoId}`, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-4">
        <EstadoVacio
            v-if="servicios.length === 0"
            :icono="NetworkIcon"
            titulo="Sin servicios vinculados"
            descripcion="Este plan todavía no cubre ningún servicio del inventario."
        />

        <ul v-else class="divide-y divide-border">
            <li
                v-for="item in servicios"
                :key="item.id"
                class="flex items-center justify-between gap-4 py-3 text-sm"
            >
                <Link :href="`/activos/${item.id}`" class="underline-offset-4 hover:underline">
                    <span class="cifra">{{ item.codigo }}</span> · {{ item.nombre }}
                </Link>
                <Button v-if="puedeGestionar" variant="ghost" size="sm" @click="desvincular(item.id)">
                    Desvincular
                </Button>
            </li>
        </ul>

        <div v-if="puedeGestionar && disponibles.length > 0" class="space-y-2">
            <CampoSelect
                nombre="activo_id"
                etiqueta="Vincular un servicio"
                :opciones="disponibles"
                :error="servicio.errors.activo_id"
                @update:model-value="(valor?: string) => (servicio.activo_id = valor ?? '')"
            />
            <Button
                variant="outline"
                :disabled="servicio.processing || servicio.activo_id === ''"
                @click="vincular"
            >
                Vincular
            </Button>
        </div>
    </div>
</template>
