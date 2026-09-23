<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { RepeatIcon } from '@lucide/vue';
import { computed } from 'vue';

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
    puedeGestionar: boolean;
}>();

const formulario = useForm({});

/*
 * **El vacío es el de la tabla, no el de los vigentes.**
 *
 * Era `total === 0`, y `total` cuenta sólo los activos: con todos los compromisos
 * retirados, la pantalla escondía filas que existen y decía que no había ninguna.
 */
const vacio = computed(() => props.meta.total === 0 && props.sinAsumir.length > 0);

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
        <!--
            Los dos botones van **tras el permiso**, como la acción general que el
            `DataTable` deja de pintar cuando la tabla está vacía. Sin la guarda,
            quien sólo tiene `obligaciones.ver` los veía y se llevaba un 403: el
            servidor decide, y la pantalla no puede ofrecer una puerta cerrada.
        -->
        <EstadoVacio
            v-if="vacio"
            :icono="RepeatIcon"
            titulo="Todavía no hay ninguna obligación asumida"
            :descripcion="
                puedeGestionar
                    ? `El catálogo propone ${sinAsumir.length} para esta organización. Se pueden asumir de una vez y ajustar después desde cuándo corre el reloj en cada una.`
                    : `El catálogo propone ${sinAsumir.length} para esta organización, y todavía no las ha asumido nadie. Quien las declara es el responsable de seguridad.`
            "
        >
            <template v-if="puedeGestionar">
                <Button :disabled="formulario.processing" @click="asumirTodas">
                    Asumir las {{ sinAsumir.length }} del catálogo
                </Button>
                <Button as-child variant="outline">
                    <Link href="/obligaciones/crear">Declarar una propia</Link>
                </Button>
            </template>
        </EstadoVacio>

        <DataTable v-else :recurso="recurso" :filas="filas" :meta="meta" />

        <!--
            Con filas dentro, lo que queda por asumir se dice en una línea y no
            en una tarjeta: es un recordatorio, no la acción principal de la
            pantalla.
        -->
        <p v-if="!vacio && sinAsumir.length > 0" class="mt-4 text-xs text-muted-foreground">
            El catálogo propone
            {{ sinAsumir.length === 1 ? 'una obligación más' : `${sinAsumir.length} obligaciones más` }}
            para esta organización.
            <Link v-if="puedeGestionar" href="/obligaciones/crear" class="underline underline-offset-2">
                Revisarlas
            </Link>
        </p>
    </AppLayout>
</template>
