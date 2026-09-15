<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRightIcon } from '@lucide/vue';

type ValorEtiquetado = App.Http.Resources.Definicion.ValorEtiquetado;

export interface RiesgoDelActivo {
    id: number;
    codigo: string;
    titulo: string;
    amenaza: string;
    intrinseco: ValorEtiquetado | null;
    residual: ValorEtiquetado | null;
    decision: ValorEtiquetado | null;
    sinRespaldo: boolean;
    revisionVencida: boolean;
}

/**
 * A qué está expuesto este activo.
 *
 * El espejo de «Sobre qué pesa» de la ficha del riesgo, y hace falta en las dos
 * direcciones: allí se pregunta qué activos pone en juego un riesgo, y aquí qué
 * riesgos arrastra un activo. Sin esto el inventario dice cuánto vale una cosa y
 * qué se cae con ella, pero no contra qué hay que protegerla.
 *
 * **Las dos cifras se enseñan juntas**, igual que en la tabla de riesgos y por lo
 * mismo: sólo el residual esconde de qué se partía, y sólo el intrínseco hace
 * parecer que no se ha hecho nada.
 *
 * Los dos avisos —residual sin respaldo y reevaluación vencida— van como badge y
 * no como `Aviso`: en una lista de riesgos, cinco bloques de alerta apilados
 * dejan de leerse como alertas. El detalle, con su explicación entera, está en la
 * ficha del riesgo, que es donde se arregla.
 */
defineProps<{
    riesgos: RiesgoDelActivo[];
    activoId: number;
}>();

const sinValorar: ValorEtiquetado = { valor: null, etiqueta: 'Sin valorar', tono: 'no_iniciado' };
</script>

<template>
    <ul v-if="riesgos.length > 0" class="divide-y divide-border">
        <li v-for="riesgo in riesgos" :key="riesgo.id" class="py-3 first:pt-0">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <Link
                        :href="`/riesgos/${riesgo.id}`"
                        class="text-sm font-medium underline-offset-4 hover:underline"
                    >
                        <span class="cifra text-muted-foreground">{{ riesgo.codigo }}</span>
                        {{ riesgo.titulo }}
                    </Link>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ riesgo.amenaza }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <CeldaBadge :valor="riesgo.intrinseco ?? sinValorar" />

                    <!--
                        La flecha sólo cuando hay un «después» que enseñar, y con
                        su texto detrás: leídos seguidos, dos badges sin nada en
                        medio son dos niveles sueltos y no un antes y un después.
                    -->
                    <template v-if="riesgo.residual">
                        <ArrowRightIcon class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <span class="sr-only">queda en</span>
                        <CeldaBadge :valor="riesgo.residual" />
                    </template>

                    <CeldaBadge v-if="riesgo.decision" :valor="riesgo.decision" />
                </div>
            </div>

            <div v-if="riesgo.sinRespaldo || riesgo.revisionVencida" class="mt-2 flex flex-wrap gap-1.5">
                <CeldaBadge
                    v-if="riesgo.sinRespaldo"
                    :valor="{
                        valor: null,
                        etiqueta: 'Residual sin respaldo',
                        tono: 'caducada',
                        icono: 'TriangleAlert',
                    }"
                />
                <CeldaBadge
                    v-if="riesgo.revisionVencida"
                    :valor="{ valor: null, etiqueta: 'Reevaluación vencida', tono: 'caducada' }"
                />
            </div>
        </li>
    </ul>

    <p v-else class="text-sm text-muted-foreground">
        No hay ningún riesgo registrado sobre este activo. Los riesgos se registran en
        <Link href="/riesgos" class="underline underline-offset-4">el análisis de riesgos</Link> y se
        vinculan a los activos sobre los que pesan.
    </p>

    <p v-if="riesgos.length > 0" class="pt-3 text-xs text-muted-foreground">
        <Link :href="`/riesgos?filter[activo]=${activoId}`" class="underline underline-offset-4">
            Ver estos riesgos en el registro
        </Link>
    </p>
</template>
