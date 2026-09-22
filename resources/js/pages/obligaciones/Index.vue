<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarCheckIcon } from '@lucide/vue';

type Proponible = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    baseLegal: string | null;
    marco: string | null;
    cadencia: string;
};

/**
 * El registro de obligaciones periódicas: § 4.16.
 *
 * **Sin acciones masivas.** Marcar veinte compromisos como cumplidos de golpe
 * escribiría veinte filas de histórico que nadie ha mirado, y cada cumplimiento
 * lleva su fecha, su prueba y su nota. Mismo argumento que en indicadores.
 *
 * **«Asumir las del catálogo» sólo aparece con la tabla vacía.** Sin esa salida
 * el registro se queda vacío para siempre: nadie declara a mano seis obligaciones
 * que ya se sabe de memoria, y un registro vacío es lo mismo que no tener el
 * módulo. Con filas dentro estorba, porque a partir de ahí lo que toca es
 * mirarlas una a una.
 */
const props = defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    alertas: App.Http.Resources.Panel.Indicador[];
    pendientes: App.Http.Resources.Panel.Indicador[];
    total: number;
    sinAsumir: Proponible[];
}>();

const formulario = useForm({});

function asumirTodas(): void {
    formulario.post('/obligaciones/predefinidas', { preserveScroll: true });
}
</script>

<template>
    <AppLayout :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        >
            <template #acciones>
                <Link href="/calendario">
                    <Button variant="outline" size="sm">Ver en el calendario</Button>
                </Link>
            </template>
        </CabeceraPagina>

        <TiraIndicadores
            v-if="total > 0"
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="obligaciones vigentes"
            :filtros="props.meta.filtros"
        />

        <!--
            La salida del registro vacío. Enumera lo que el catálogo propone
            **para esta organización** —filtrado por marco, por las banderas del
            ENS y por la categoría derivada de sus sistemas—, no las siete que
            hay cargadas.
        -->
        <EstadoVacio
            v-if="total === 0 && sinAsumir.length > 0"
            :icono="CalendarCheckIcon"
            titulo="Todavía no hay ninguna obligación asumida"
            :descripcion="`El catálogo propone ${sinAsumir.length} para esta organización. Se pueden asumir de una vez y ajustar después desde cuándo corre el reloj en cada una.`"
        >
            <Button :disabled="formulario.processing" @click="asumirTodas">
                Asumir las {{ sinAsumir.length }} del catálogo
            </Button>
            <Link href="/obligaciones/crear">
                <Button variant="outline">Declarar una propia</Button>
            </Link>
        </EstadoVacio>

        <DataTable v-else :recurso="recurso" :filas="filas" :meta="meta" />

        <!--
            Con filas dentro, lo que queda por asumir se dice en una línea y no
            en una tarjeta: es un recordatorio, no la acción principal de la
            pantalla.
        -->
        <p v-if="total > 0 && sinAsumir.length > 0" class="mt-4 text-xs text-muted-foreground">
            El catálogo propone {{ sinAsumir.length }} obligación(es) más para esta organización.
            <Link href="/obligaciones/crear" class="underline underline-offset-2">Revisarlas</Link>.
        </p>
    </AppLayout>
</template>
