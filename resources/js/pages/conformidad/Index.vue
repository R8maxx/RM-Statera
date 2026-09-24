<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible } from '@/lib/celdas';
import { Link, router } from '@inertiajs/vue3';
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
 *
 * **Tabla estática y no tarjetas.** Eran una tarjeta por sistema, y cinco
 * tarjetas iguales son una tabla (DESIGN.md §9): con columnas, la categoría y el
 * estado de cada sistema se comparan en vertical. Sin `Recurso` ni `MetaTabla`,
 * por lo de arriba: `ui/table` es la tabla de lo que no se pagina.
 */
defineProps<{
    sistemas: SistemaEns[];
}>();
</script>

<template>
    <AppLayout titulo="Conformidad ENS" ancho="completo">
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

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Sistema</TableHead>
                    <TableHead>Categoría</TableHead>
                    <TableHead>Declaración vigente</TableHead>
                    <TableHead>En preparación</TableHead>
                    <TableHead class="w-10"><span class="sr-only">Abrir</span></TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <!-- La fila entera abre la ficha, como en `DataTable`; el enlace
                     del nombre es el que se alcanza con el teclado. -->
                <TableRow
                    v-for="sistema in sistemas"
                    :key="sistema.id"
                    class="cursor-pointer"
                    @click="router.visit(`/conformidad/sistemas/${sistema.id}`)"
                >
                    <TableCell>
                        <Link
                            :href="`/conformidad/sistemas/${sistema.id}`"
                            class="flex items-baseline gap-2 rounded underline-offset-4 hover:underline"
                            @click.stop
                        >
                            <span class="cifra text-xs text-muted-foreground">{{ sistema.codigo }}</span>
                            <span class="font-medium">{{ sistema.nombre }}</span>
                        </Link>
                    </TableCell>

                    <TableCell>
                        <CeldaBadge
                            v-if="sistema.categoria"
                            :valor="{
                                valor: sistema.categoriaTono ?? '',
                                etiqueta: `Categoría ${sistema.categoria}`,
                                tono: sistema.categoriaTono ?? 'no_iniciado',
                                icono: null,
                            }"
                        />
                        <span v-else class="text-muted-foreground">Sin valorar</span>
                    </TableCell>

                    <TableCell>
                        <div v-if="sistema.vigente" class="flex flex-wrap items-center gap-2">
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
                                {{ fechaLegible(sistema.vigente.vigenteHasta) }}
                            </span>
                        </div>
                        <span v-else class="text-muted-foreground">Sin declarar</span>
                    </TableCell>

                    <TableCell>
                        <CeldaBadge
                            v-if="sistema.enPreparacion"
                            :valor="{
                                valor: sistema.enPreparacion.estado,
                                etiqueta: sistema.vigente ? 'Renovación en preparación' : sistema.enPreparacion.estadoEtiqueta,
                                tono: sistema.enPreparacion.tono,
                                icono: sistema.enPreparacion.icono,
                            }"
                        />
                        <span v-else class="text-muted-foreground" aria-label="sin valor">—</span>
                    </TableCell>

                    <TableCell>
                        <ChevronRightIcon class="size-4 text-muted-foreground" aria-hidden="true" />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </AppLayout>
</template>
