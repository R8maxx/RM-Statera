<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFechaHora } from '@/lib/celdas';

/**
 * La ficha comercial de una organización cliente (punto 41).
 *
 * **Lo que se ve es lo que la plataforma necesita para atender al cliente**:
 * quién es, quién entra y qué ha hecho la plataforma con él. Nada de lo que
 * guarda dentro —sistemas, riesgos, evidencias—, que no se lee sin que el
 * cliente abra la puerta.
 */
interface Cuenta {
    id: number;
    nombre: string;
    email: string;
    rol: string | null;
    estado: { valor: string; etiqueta: string; tono: string; icono: string };
}

interface EventoTraza {
    id: number;
    accion: string;
    autor: string | null;
    fecha: string;
}

defineProps<{
    organizacion: {
        id: number;
        nombre: string;
        razonSocial: string | null;
        cif: string | null;
        sector: string | null;
        altaEn: string | null;
    };
    cuentas: Cuenta[];
    invitadas: number;
    traza: EventoTraza[];
}>();

const cuando = (fecha: string | null): string => (fecha ? formatoFechaHora.format(new Date(fecha)) : '—');
</script>

<template>
    <AppLayout :titulo="organizacion.nombre">
        <CabeceraPagina :titulo="organizacion.nombre" :descripcion="organizacion.razonSocial ?? undefined" />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Cuentas</CardTitle>
                        <CardDescription>
                            Quién entra y con qué rol. Las da de alta el responsable de seguridad del cliente, no la
                            plataforma.
                            <template v-if="invitadas > 0">
                                {{ invitadas === 1 ? 'Una invitación sigue' : `${invitadas} invitaciones siguen` }} sin
                                aceptar.
                            </template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y text-sm">
                            <li
                                v-for="cuenta in cuentas"
                                :key="cuenta.id"
                                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2.5"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ cuenta.nombre }}</span>
                                    <span class="block truncate text-muted-foreground">{{ cuenta.email }}</span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <span v-if="cuenta.rol" class="text-muted-foreground">{{ cuenta.rol }}</span>
                                    <CeldaBadge :valor="{ ...cuenta.estado }" />
                                </span>
                            </li>
                            <li v-if="cuentas.length === 0" class="py-2.5 text-muted-foreground">Sin cuentas.</li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lo que ha hecho la plataforma</CardTitle>
                        <CardDescription>De la traza de la plataforma. Los veinte eventos más recientes.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y text-sm">
                            <li v-for="evento in traza" :key="evento.id" class="flex flex-wrap justify-between gap-x-4 py-2.5">
                                <span>
                                    {{ evento.accion }}
                                    <span v-if="evento.autor" class="text-muted-foreground">· {{ evento.autor }}</span>
                                </span>
                                <span class="cifra text-muted-foreground">{{ cuando(evento.fecha) }}</span>
                            </li>
                            <li v-if="traza.length === 0" class="py-2.5 text-muted-foreground">Sin eventos.</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid gap-2">
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">CIF</dt>
                                <dd>{{ organizacion.cif ?? '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Sector</dt>
                                <dd>{{ organizacion.sector ?? '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Alta</dt>
                                <dd>{{ cuando(organizacion.altaEn) }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
