<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { euros } from '@/lib/dinero';
import { Link } from '@inertiajs/vue3';

/**
 * Los planes que se venden (punto 43): qué límites ponen, cuánto aguanta un
 * impago antes de pasar a sólo lectura y, desde el punto 51, cuánto cuestan y
 * si la organización los contrata por su cuenta. Un plan retirado sigue con
 * quien ya lo tiene.
 */
interface Plan {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    limiteCuentas: number | null;
    limiteSistemas: number | null;
    diasGracia: number;
    activo: boolean;
    precioMensualCentimos: number | null;
    descuentoAnual: number;
    contratable: boolean;
    clientes: number;
}

defineProps<{ planes: Plan[] }>();
</script>

<template>
    <AppLayout titulo="Planes">
        <CabeceraPagina
            titulo="Planes"
            descripcion="Lo que se le vende a cada organización: cuántas cuentas y sistemas admite, y cuántos días de gracia tiene tras vencer antes de pasar a sólo lectura."
        >
            <template #acciones>
                <Button as-child>
                    <Link href="/plataforma/planes/crear">Crear un plan</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <Card v-if="planes.length > 0" class="py-0">
            <CardContent class="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Plan</TableHead>
                            <TableHead class="text-right">Cuentas</TableHead>
                            <TableHead class="text-right">Sistemas</TableHead>
                            <TableHead class="text-right">Gracia</TableHead>
                            <TableHead class="text-right">Al mes</TableHead>
                            <TableHead class="text-right">Clientes</TableHead>
                            <TableHead class="sr-only">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="plan in planes" :key="plan.id" :class="!plan.activo && 'text-muted-foreground'">
                            <TableCell>
                                <span class="font-medium">{{ plan.nombre }}</span>
                                <span class="cifra ml-2 text-xs text-muted-foreground">{{ plan.codigo }}</span>
                                <span v-if="!plan.activo" class="ml-2 text-xs">· Retirado</span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Cifra v-if="plan.limiteCuentas" :valor="plan.limiteCuentas" />
                                <span v-else class="text-muted-foreground">Sin límite</span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Cifra v-if="plan.limiteSistemas" :valor="plan.limiteSistemas" />
                                <span v-else class="text-muted-foreground">Sin límite</span>
                            </TableCell>
                            <TableCell class="cifra text-right">{{ plan.diasGracia }} d</TableCell>
                            <TableCell class="text-right">
                                <span v-if="plan.precioMensualCentimos !== null" class="cifra">{{ euros(plan.precioMensualCentimos) }}</span>
                                <span v-else class="text-muted-foreground">Sin precio</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ plan.contratable ? 'Lo contrata el cliente' : 'Sólo la plataforma' }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right"><Cifra :valor="plan.clientes" /></TableCell>
                            <TableCell class="text-right">
                                <Button as-child variant="ghost" size="sm">
                                    <Link :href="`/plataforma/planes/${plan.id}/editar`">Editar</Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <EstadoVacio
            v-else
            titulo="Todavía no hay planes"
            descripcion="Una organización sin plan no tiene límites ni vence. Crea uno cuando haya algo que vender."
        />
    </AppLayout>
</template>
