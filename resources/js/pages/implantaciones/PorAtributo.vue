<script setup lang="ts">
import BarraSegmentada from '@/components/BarraSegmentada.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Fila {
    valor: string;
    etiqueta: string;
    total: number;
    implantadas: number;
    segmentos: { clave: string; etiqueta: string; valor: number }[];
}

/**
 * Lo exigible agrupado por un atributo de la ISO 27002: § 4.4.
 *
 * Una fila por valor, con su reparto por estado en la misma barra que el panel.
 * **Las filas no suman el total**: un control lleva a menudo varios valores de la
 * misma dimensión y cuenta en cada uno. Cada fila lleva a la tabla filtrada por
 * ese valor, que es donde se trabaja.
 */
const props = defineProps<{
    dimensiones: Opcion[];
    dimension: string | null;
    sistema: string | null;
    sistemas: Opcion[];
    filas: Fila[];
}>();

const dimensionElegida = ref(props.dimension ?? undefined);
const sistemaElegido = ref(props.sistema ?? SIN_VALOR);

function cambiar(): void {
    router.get(
        '/implantaciones/atributos',
        {
            dimension: dimensionElegida.value,
            ...(sistemaElegido.value !== SIN_VALOR ? { sistema: sistemaElegido.value } : {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function enlace(fila: Fila): string {
    const filtros = new URLSearchParams();
    filtros.append(`filter[atributo_${props.dimension}][]`, fila.valor);

    if (props.sistema) {
        filtros.append('filter[sistema_id]', props.sistema);
    }

    return `/implantaciones?${filtros.toString()}`;
}

function porcentaje(fila: Fila): number {
    return fila.total === 0 ? 0 : Math.round((fila.implantadas / fila.total) * 100);
}
</script>

<template>
    <AppLayout titulo="Implantaciones por atributo">
        <CabeceraPagina
            titulo="Por atributo de la ISO 27002"
            descripcion="Lo exigible agrupado por uno de los cinco atributos de la ISO 27002. Cada fila lleva a la tabla filtrada por ese valor."
        >
            <template #acciones>
                <Button as-child variant="outline">
                    <Link href="/implantaciones">Volver a la tabla</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <EstadoVacio
            v-if="dimensiones.length === 0"
            titulo="El catálogo no trae atributos"
            descripcion="Los atributos salen del vocabulario de la ISO 27002 en el catálogo. Importa el catálogo para tenerlos."
        />

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2">
                <CampoSelect
                    v-model="dimensionElegida"
                    nombre="dimension"
                    etiqueta="Agrupar por"
                    :opciones="dimensiones"
                    @update:model-value="cambiar"
                />
                <CampoSelect
                    v-model="sistemaElegido"
                    nombre="sistema"
                    etiqueta="Sistema"
                    :opciones="conOpcionVacia(sistemas, 'Todos los sistemas')"
                    @update:model-value="cambiar"
                />
            </div>

            <p class="text-[13px] text-muted-foreground">
                Sobre lo exigible. Un control con varios valores de la misma dimensión cuenta en cada uno, así que
                las filas no suman el total.
            </p>

            <ul class="divide-y rounded-xl border">
                <li
                    v-for="fila in filas"
                    :key="fila.valor"
                    class="grid gap-x-6 gap-y-2 px-4 py-3 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_8rem] sm:items-center"
                >
                    <Link :href="enlace(fila)" class="font-medium underline-offset-4 hover:underline">
                        {{ fila.etiqueta }}
                    </Link>

                    <BarraSegmentada v-if="fila.total > 0" :segmentos="fila.segmentos" alto="fino" />
                    <span v-else class="text-[13px] text-muted-foreground">Ningún control exigible con este valor.</span>

                    <span class="text-[13px] text-muted-foreground sm:text-right">
                        <Cifra class="font-medium text-foreground" :valor="fila.implantadas" />
                        de {{ fila.total }}
                        <template v-if="fila.total > 0"> · {{ porcentaje(fila) }} %</template>
                    </span>
                </li>
            </ul>
        </template>
    </AppLayout>
</template>
