<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/HistoricoTransiciones.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible, formatoFechaHora } from '@/lib/celdas';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * La ficha de una cuenta (§ 4.19).
 *
 * La columna lateral va en el orden de `DESIGN.md`: «Estado» —desactivar,
 * reactivar, reenviar la invitación— arriba y «Ficha» después. La actividad
 * sale de la traza y va en la columna principal, con el mismo histórico que
 * las máquinas de estados, porque contesta a lo mismo: «¿desde cuándo tiene
 * este rol?».
 *
 * **Sobre la propia cuenta no hay botón de desactivar**: el dominio lo impide y
 * ofrecerlo sería invitar a un error.
 */
interface Cuenta {
    id: number;
    nombre: string;
    email: string;
    rol: string | null;
    rolEtiqueta: string | null;
    rolDescripcion: string | null;
    estado: { valor: string; etiqueta: string; descripcion: string; tono: string; icono: string };
    dosFactores: boolean;
    accesoHasta: string | null;
    invitadaEn: string | null;
    activadaEn: string | null;
    desactivadaEn: string | null;
    motivoDesactivacion: string | null;
    ultimoAcceso: string | null;
    persona: { id: number; nombre: string } | null;
    sistemas: { id: number; codigo: string; nombre: string }[];
    esLaPropia: boolean;
    sinAlcance: boolean;
}

const props = defineProps<{
    cuenta: Cuenta;
    actividad: Transicion[];
}>();

const pagina = usePage();
const errorCuenta = computed(() => (pagina.props.errors as Record<string, string | undefined>).cuenta);

const desactivando = ref(false);
const desactivacion = useForm({ motivo: '' });

function desactivar(): void {
    desactivacion.post(`/cuentas/${props.cuenta.id}/desactivar`, {
        preserveScroll: true,
        onSuccess: () => {
            desactivando.value = false;
            desactivacion.reset();
        },
    });
}

const enviando = ref(false);

function enviar(accion: 'reactivar' | 'reenviar'): void {
    router.post(`/cuentas/${props.cuenta.id}/${accion}`, {}, {
        preserveScroll: true,
        onStart: () => (enviando.value = true),
        onFinish: () => (enviando.value = false),
    });
}

const cuando = (fecha: string | null): string => (fecha ? formatoFechaHora.format(new Date(fecha)) : '—');
</script>

<template>
    <AppLayout :titulo="cuenta.nombre">
        <CabeceraPagina :titulo="cuenta.nombre" :descripcion="cuenta.email">
            <template #acciones>
                <Button as-child variant="outline">
                    <Link :href="`/cuentas/${cuenta.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: cuenta.estado.valor,
                    etiqueta: cuenta.estado.etiqueta,
                    tono: cuenta.estado.tono,
                    icono: cuenta.estado.icono,
                }"
            />
            <span v-if="cuenta.rolEtiqueta" class="text-sm text-muted-foreground">{{ cuenta.rolEtiqueta }}</span>
            <span v-if="cuenta.esLaPropia" class="text-sm text-muted-foreground">· Es tu cuenta</span>
        </div>

        <Aviso v-if="errorCuenta" tono="error">{{ errorCuenta }}</Aviso>

        <Aviso v-if="cuenta.sinAlcance" tono="error" titulo="Sin alcance">
            Es una cuenta de auditor y no tiene ningún sistema asignado, así que ve la organización entera.
            Edítala para decir qué audita y hasta cuándo.
        </Aviso>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Qué puede hacer</CardTitle>
                        <CardDescription v-if="cuenta.rolDescripcion">{{ cuenta.rolDescripcion }}</CardDescription>
                        <CardDescription v-else>Sin rol: no puede hacer nada hasta que se le dé uno.</CardDescription>
                    </CardHeader>
                    <CardContent v-if="cuenta.rol === 'auditor' && cuenta.sistemas.length > 0" class="space-y-2 text-sm">
                        <p class="text-muted-foreground">
                            Ve sólo estos sistemas, y lo que es de toda la organización:
                        </p>
                        <ul class="grid gap-1">
                            <li v-for="sistema in cuenta.sistemas" :key="sistema.id">
                                <span class="cifra">{{ sistema.codigo }}</span> · {{ sistema.nombre }}
                            </li>
                        </ul>
                        <p v-if="cuenta.accesoHasta" class="text-muted-foreground">
                            Hasta el {{ fechaLegible(cuenta.accesoHasta) }}, incluido.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Actividad</CardTitle>
                        <CardDescription>
                            De la traza: entradas, cambios de rol y de alcance. Las veinte más recientes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <HistoricoTransiciones :transiciones="actividad" vacio="Sin actividad registrada todavía." />
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                        <CardDescription>{{ cuenta.estado.descripcion }}</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <Button
                            v-if="cuenta.estado.valor === 'invitada'"
                            variant="outline"
                            size="sm"
                            :disabled="enviando"
                            @click="enviar('reenviar')"
                        >
                            Reenviar la invitación
                        </Button>
                        <Button
                            v-if="cuenta.desactivadaEn"
                            variant="outline"
                            size="sm"
                            :disabled="enviando"
                            @click="enviar('reactivar')"
                        >
                            Reactivar
                        </Button>
                        <Button
                            v-else-if="!cuenta.esLaPropia"
                            variant="outline"
                            size="sm"
                            @click="desactivando = true"
                        >
                            Desactivar
                        </Button>
                        <p v-else class="text-sm text-muted-foreground">
                            Tu propia cuenta sólo la puede desactivar otro responsable de seguridad.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid gap-2">
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Verificación en dos pasos</dt>
                                <dd>{{ cuenta.dosFactores ? 'Activada' : 'Sin activar' }}</dd>
                            </div>
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Último acceso</dt>
                                <dd>{{ cuenta.ultimoAcceso ? cuando(cuenta.ultimoAcceso) : 'Nunca' }}</dd>
                            </div>
                            <div v-if="cuenta.persona" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Persona</dt>
                                <dd>
                                    <Link :href="`/personas/${cuenta.persona.id}`" class="underline underline-offset-4">
                                        {{ cuenta.persona.nombre }}
                                    </Link>
                                </dd>
                            </div>
                            <div v-if="cuenta.invitadaEn" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Invitada</dt>
                                <dd>{{ cuando(cuenta.invitadaEn) }}</dd>
                            </div>
                            <div v-if="cuenta.activadaEn" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Activa desde</dt>
                                <dd>{{ cuando(cuenta.activadaEn) }}</dd>
                            </div>
                            <div v-if="cuenta.desactivadaEn" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Desactivada</dt>
                                <dd>{{ cuando(cuenta.desactivadaEn) }}</dd>
                            </div>
                            <div v-if="cuenta.motivoDesactivacion" class="grid gap-0.5">
                                <dt class="text-muted-foreground">Motivo</dt>
                                <dd class="whitespace-pre-line">{{ cuenta.motivoDesactivacion }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="desactivando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Desactivar la cuenta de {{ cuenta.nombre }}</DialogTitle>
                    <DialogDescription>
                        Deja de entrar ahora mismo: se cierran sus sesiones abiertas y, si tenía una
                        invitación pendiente, el enlace deja de servir. No se borra nada; lo que hizo
                        sigue a su nombre.
                    </DialogDescription>
                </DialogHeader>

                <CampoTextarea
                    v-model="desactivacion.motivo"
                    nombre="motivo"
                    etiqueta="Motivo"
                    :filas="2"
                    :error="desactivacion.errors.motivo"
                    ayuda="Opcional. «Baja en la empresa», «fin de la auditoría». Es lo primero que se pregunta seis meses después."
                />

                <DialogFooter>
                    <Button variant="outline" @click="desactivando = false">Cancelar</Button>
                    <Button :disabled="desactivacion.processing" @click="desactivar">Desactivar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
