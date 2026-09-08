<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

interface SistemaResumido {
    id: number;
    codigo: string;
    nombre: string;
    marco: string | null;
    categoria: string | null;
    aplicables: number;
    implantadas: number;
}

const props = defineProps<{
    sistemas: SistemaResumido[];
    resumen: { sistemas: number; aplicables: number; implantadas: number; pendientes: number };
}>();

const porcentaje = (implantadas: number, aplicables: number): number =>
    aplicables === 0 ? 0 : Math.round((implantadas / aplicables) * 100);

const porcentajeGlobal = porcentaje(props.resumen.implantadas, props.resumen.aplicables);
</script>

<template>
    <AppLayout titulo="Panel">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader>
                    <CardDescription>Sistemas en alcance</CardDescription>
                    <CardTitle class="text-3xl">{{ resumen.sistemas }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Requisitos aplicables</CardDescription>
                    <CardTitle class="text-3xl">{{ resumen.aplicables }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Implantados</CardDescription>
                    <CardTitle class="text-3xl">{{ porcentajeGlobal }}%</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Pendientes</CardDescription>
                    <CardTitle class="text-3xl">{{ resumen.pendientes }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Sistemas</CardTitle>
                <CardDescription>La categoría se deriva de la valoración de las cinco dimensiones.</CardDescription>
            </CardHeader>
            <CardContent>
                <p v-if="sistemas.length === 0" class="text-sm text-muted-foreground">
                    Todavía no hay ningún sistema. Da de alta el primero desde
                    <Link href="/sistemas" class="text-primary underline-offset-4 hover:underline">Sistemas</Link>.
                </p>

                <ul v-else class="divide-y">
                    <li v-for="sistema in sistemas" :key="sistema.id" class="flex items-center gap-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                <span class="text-muted-foreground">{{ sistema.codigo }}</span>
                                — {{ sistema.nombre }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ sistema.marco ?? 'Sin marco' }}
                                <template v-if="sistema.categoria"> · categoría {{ sistema.categoria }}</template>
                            </p>
                        </div>

                        <div class="w-40 shrink-0">
                            <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-estado-implantado"
                                    :style="{ width: `${porcentaje(sistema.implantadas, sistema.aplicables)}%` }"
                                />
                            </div>
                            <p class="mt-1 text-right text-xs text-muted-foreground">
                                {{ sistema.implantadas }} / {{ sistema.aplicables }}
                            </p>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </AppLayout>
</template>
