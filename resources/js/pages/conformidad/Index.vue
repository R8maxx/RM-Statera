<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { BadgeCheckIcon, ChevronRightIcon } from '@lucide/vue';

interface Resumen {
    id: number;
    estado: string;
    estadoEtiqueta: string;
    tono: string;
    icono: string;
    categoria: string;
    vigenteHasta: string | null;
    caducada: boolean;
}

interface SistemaEns {
    id: number;
    codigo: string;
    nombre: string;
    categoria: string | null;
    categoriaTono: string | null;
    vigente: Resumen | null;
    enPreparacion: Resumen | null;
}

/**
 * La conformidad con el ENS: § 4.17.
 *
 * **Una fila por sistema y no por declaración.** La pregunta al llegar es «¿cómo
 * está cada sistema?», y en una organización los sistemas bajo el ENS son unos
 * pocos: una tabla paginada con filtros sería ceremonia. Las declaraciones
 * anteriores viven en la ficha de cada uno.
 */
defineProps<{
    sistemas: SistemaEns[];
}>();
</script>

<template>
    <AppLayout titulo="Conformidad ENS">
        <CabeceraPagina
            titulo="Conformidad con el ENS"
            descripcion="En categoría básica, la organización se autoevalúa, firma la Declaración de Conformidad y publica el distintivo. Media y alta se certifican con una entidad acreditada por ENAC."
        />

        <EstadoVacio
            v-if="sistemas.length === 0"
            :icono="BadgeCheckIcon"
            titulo="No hay sistemas bajo el ENS"
            descripcion="La conformidad se declara por sistema. Da de alta un sistema con el marco ENS y valora sus cinco dimensiones."
            :accion="{ etiqueta: 'Ir a sistemas', href: '/sistemas' }"
        />

        <ul v-else class="grid gap-3">
            <li v-for="sistema in sistemas" :key="sistema.id">
                <Card class="transition-colors hover:border-foreground/20">
                    <CardContent class="p-0">
                        <Link
                            :href="`/conformidad/sistemas/${sistema.id}`"
                            class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="cifra text-xs text-muted-foreground">{{ sistema.codigo }}</p>
                                <p class="truncate font-medium">{{ sistema.nombre }}</p>
                            </div>

                            <CeldaBadge
                                v-if="sistema.categoria"
                                :valor="{
                                    valor: sistema.categoriaTono ?? '',
                                    etiqueta: `Categoría ${sistema.categoria}`,
                                    tono: sistema.categoriaTono ?? 'no_iniciado',
                                    icono: null,
                                }"
                            />
                            <span v-else class="text-sm text-muted-foreground">Sin valorar</span>

                            <div class="flex flex-wrap items-center gap-2">
                                <template v-if="sistema.vigente">
                                    <CeldaBadge
                                        :valor="{
                                            valor: sistema.vigente.estado,
                                            etiqueta: sistema.vigente.estadoEtiqueta,
                                            tono: sistema.vigente.tono,
                                            icono: sistema.vigente.icono,
                                        }"
                                    />
                                    <span v-if="sistema.vigente.vigenteHasta" class="text-xs text-muted-foreground">
                                        {{ sistema.vigente.caducada ? 'Caducó el' : 'Hasta el' }}
                                        {{ sistema.vigente.vigenteHasta }}
                                    </span>
                                </template>
                                <CeldaBadge
                                    v-if="sistema.enPreparacion"
                                    :valor="{
                                        valor: sistema.enPreparacion.estado,
                                        etiqueta: sistema.vigente ? 'Renovación en preparación' : sistema.enPreparacion.estadoEtiqueta,
                                        tono: sistema.enPreparacion.tono,
                                        icono: sistema.enPreparacion.icono,
                                    }"
                                />
                                <span
                                    v-if="!sistema.vigente && !sistema.enPreparacion"
                                    class="text-sm text-muted-foreground"
                                >
                                    Sin declarar
                                </span>
                            </div>

                            <ChevronRightIcon class="size-4 text-muted-foreground" aria-hidden="true" />
                        </Link>
                    </CardContent>
                </Card>
            </li>
        </ul>
    </AppLayout>
</template>
