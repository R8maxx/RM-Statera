<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFechaHora } from '@/lib/celdas';

/**
 * La salud del servicio (punto 55): si hay copia de anoche y si se ha
 * comprobado que se restaura, cómo van las colas y qué trabajos han fallado.
 *
 * El rojo es para lo que deja el servicio expuesto: una copia vieja, una
 * verificación que falló o no se hace, un disco que no responde. Los trabajos
 * fallidos se enseñan sin sus datos: sólo qué falló y dónde.
 */
interface Copias {
    disponible: boolean;
    ultimaCopia: string | null;
    copiaAlDia: boolean;
    ultimaVerificacion: string | null;
    verificacionCorrecta: boolean | null;
    verificacionAlDia: boolean;
}

defineProps<{
    copias: Copias;
    colas: { nombre: string; pendientes: number | null }[];
    fallidos: { id: number; cola: string; trabajo: string; error: string; fecha: string }[];
    totalFallidos: number;
}>();

const cuando = (fecha: string | null): string => (fecha ? formatoFechaHora.format(new Date(fecha)) : 'Nunca');
</script>

<template>
    <AppLayout titulo="Salud del servicio">
        <CabeceraPagina
            titulo="Salud del servicio"
            descripcion="Las copias y su restauración probada, las colas de trabajos y lo que ha fallado. La herramienta entra en el alcance de su propio SGSI."
        >
            <template #acciones>
                <Button as-child variant="outline">
                    <a href="/horizon" target="_blank" rel="noopener">Abrir Horizon</a>
                </Button>
            </template>
        </CabeceraPagina>

        <Aviso v-if="!copias.disponible" tono="error" titulo="El disco de las copias no responde">
            No se puede leer el almacenamiento de las copias. Sin él no hay forma de saber si la de anoche existe.
        </Aviso>

        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Última copia</CardTitle>
                    <CardDescription>Se hace cada noche a las 02:00. En rojo si tiene más de un día.</CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="cifra text-lg" :class="copias.copiaAlDia ? 'text-foreground' : 'text-destructive'">
                        {{ cuando(copias.ultimaCopia) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Última restauración probada</CardTitle>
                    <CardDescription>Los domingos se restaura la última copia y se compara. En rojo si falló o tiene más de una semana.</CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="cifra text-lg" :class="copias.verificacionAlDia ? 'text-foreground' : 'text-destructive'">
                        {{ cuando(copias.ultimaVerificacion) }}
                    </p>
                    <p v-if="copias.verificacionCorrecta === false" class="text-sm text-destructive">La última verificación no cuadró.</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Colas</CardTitle>
                <CardDescription>Trabajos esperando en cada cola de Horizon.</CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                <div v-for="cola in colas" :key="cola.nombre">
                    <p class="text-muted-foreground">{{ cola.nombre }}</p>
                    <Cifra v-if="cola.pendientes !== null" class="text-xl font-semibold" :valor="cola.pendientes" />
                    <p v-else class="text-destructive">No responde</p>
                </div>
            </CardContent>
        </Card>

        <Card class="py-0">
            <CardHeader class="pt-6">
                <CardTitle>Trabajos fallidos</CardTitle>
                <CardDescription>
                    {{ totalFallidos === 0 ? 'Ninguno.' : `${totalFallidos} en total; los veinte más recientes.` }}
                    Sin sus datos: sólo qué falló y dónde. Se reintentan desde Horizon.
                </CardDescription>
            </CardHeader>
            <CardContent v-if="fallidos.length > 0" class="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="pl-6">Cuándo</TableHead>
                            <TableHead>Cola</TableHead>
                            <TableHead>Trabajo</TableHead>
                            <TableHead class="pr-6">Error</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="fallido in fallidos" :key="fallido.id">
                            <TableCell class="cifra pl-6 text-[13px]">{{ fallido.fecha }}</TableCell>
                            <TableCell>{{ fallido.cola }}</TableCell>
                            <TableCell>{{ fallido.trabajo }}</TableCell>
                            <TableCell class="max-w-md truncate pr-6 text-muted-foreground" :title="fallido.error">{{ fallido.error }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </AppLayout>
</template>
