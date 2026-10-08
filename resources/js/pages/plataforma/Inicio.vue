<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * La casa de quien administra la plataforma (punto 53).
 *
 * Cada tarjeta es una pregunta con acción detrás, y lleva a la lista que la
 * contesta. Las que están a cero se quedan en gris: un día tranquilo se ve de
 * un vistazo. El rojo, sólo para lo que va mal de verdad: clientes en sólo
 * lectura e invitaciones caducadas, que dejan a alguien sin poder trabajar.
 */
interface Cifras {
    clientes: number;
    vencenPronto: number;
    enGracia: number;
    enSoloLectura: number;
    sobreSuPlan: number;
    deBaja: number;
    soporteAbierto: number;
    invitacionesCaducadas: number;
    /** Nula para quien no puede rescatar cuentas: ni se calcula ni viaja. */
    rescatesPendientes: number | null;
}

const props = defineProps<{ cifras: Cifras }>();

const pagina = usePage();
const puede = (capacidad: string): boolean => pagina.props.auth.permisos.includes(`plataforma.${capacidad}`);

interface Tarjeta {
    clave: keyof Cifras;
    titulo: string;
    explicacion: string;
    href: string;
    roja?: boolean;
    capacidad?: string;
}

const tarjetas: Tarjeta[] = [
    { clave: 'vencenPronto', titulo: 'Vencen en 30 días', explicacion: 'Suscripciones que hay que renovar este mes.', href: '/plataforma/organizaciones?filter[vence_pronto]=1' },
    { clave: 'enGracia', titulo: 'En periodo de gracia', explicacion: 'Vencidas: trabajan con aviso hasta que acabe la gracia.', href: '/plataforma/organizaciones?filter[en_gracia]=1' },
    { clave: 'enSoloLectura', titulo: 'En sólo lectura', explicacion: 'Ya no pueden escribir hasta que se renueve.', href: '/plataforma/organizaciones?filter[solo_lectura]=1', roja: true },
    { clave: 'sobreSuPlan', titulo: 'Con más cuentas que su plan', explicacion: 'Nadie se ha desactivado, pero no pueden añadir otra.', href: '/plataforma/organizaciones?filter[sobre_su_plan]=1' },
    { clave: 'invitacionesCaducadas', titulo: 'Invitaciones caducadas', explicacion: 'Cuentas que no llegaron a entrar. Se reenvían desde la ficha del cliente.', href: '/plataforma/organizaciones', roja: true },
    { clave: 'soporteAbierto', titulo: 'Con soporte abierto', explicacion: 'Clientes que han abierto la puerta a la plataforma.', href: '/plataforma/organizaciones?filter[soporte_abierto]=1' },
    { clave: 'rescatesPendientes', titulo: 'Rescates pendientes', explicacion: 'Esperan a una segunda persona de Administración.', href: '/plataforma/solicitudes', capacidad: 'cuentas.rescatar' },
    { clave: 'deBaja', titulo: 'De baja', explicacion: 'Sin acceso; todo lo suyo se conserva.', href: '/plataforma/organizaciones?filter[de_baja]=1' },
];

const visibles = computed(() =>
    tarjetas.filter((tarjeta) => (!tarjeta.capacidad || puede(tarjeta.capacidad)) && props.cifras[tarjeta.clave] !== null),
);
const valor = (tarjeta: Tarjeta): number => props.cifras[tarjeta.clave] ?? 0;
</script>

<template>
    <AppLayout titulo="Plataforma">
        <CabeceraPagina
            titulo="Plataforma"
            :descripcion="`${cifras.clientes} ${cifras.clientes === 1 ? 'cliente activo' : 'clientes activos'}. Lo que requiere atención hoy.`"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Link v-for="tarjeta in visibles" :key="tarjeta.clave" :href="tarjeta.href" class="rounded-xl focus-visible:outline-2">
                <Card class="h-full transition-colors hover:bg-muted/40" :class="valor(tarjeta) === 0 && 'opacity-70'">
                    <CardContent class="space-y-1.5">
                        <p class="text-[13px] font-medium text-muted-foreground">{{ tarjeta.titulo }}</p>
                        <Cifra
                            class="text-3xl font-semibold"
                            :class="valor(tarjeta) > 0 && tarjeta.roja ? 'text-destructive' : 'text-foreground'"
                            :valor="valor(tarjeta)"
                        />
                        <p class="text-[13px] text-muted-foreground">{{ tarjeta.explicacion }}</p>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </AppLayout>
</template>
