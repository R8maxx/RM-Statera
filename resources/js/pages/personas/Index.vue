<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * El registro de personas: § 4.8, cláusula 5.3 y `mp.per.*`.
 *
 * **Una sola alerta, y es la salida sin cerrar.** Alguien que se fue con la
 * checklist de baja a medias es un acceso que puede seguir vivo. No estar formado
 * no va en rojo: es la distancia que queda, y un indicador que castiga por apuntar
 * lo que falta enseña a no apuntarlo.
 */
interface Cobertura {
    designados: number;
    exigibles: number;
    faltan: { sistema: string; rol: string }[];
}

const props = defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    alertas: App.Http.Resources.Panel.Indicador[];
    pendientes: App.Http.Resources.Panel.Indicador[];
    cobertura: Cobertura;
    total: number;
}>();

/**
 * Los huecos, con tope y su «y N más».
 *
 * Son cinco roles por sistema: con diez sistemas sin designar, la lista entera
 * son cincuenta chips y la tabla de personas se va de la pantalla. Mismo tope
 * que las columnas del tablero de tareas y los días del calendario.
 */
const TOPE_HUECOS = 8;

const huecosALaVista = computed(() => props.cobertura.faltan.slice(0, TOPE_HUECOS));

const huecosDeMas = computed(() => Math.max(0, props.cobertura.faltan.length - TOPE_HUECOS));
</script>

<template>
    <AppLayout :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <TiraIndicadores
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="personas registradas"
            :filtros="props.meta.filtros"
        />

        <!--
            La cobertura del 5.3. Va en su propia tarjeta y no como un indicador
            más porque no se cuenta sobre personas: se cuenta sobre la pareja
            (sistema, rol), que es otro denominador. Un indicador con el
            denominador de al lado equivocado es peor que ninguno.
        -->
        <Card size="sm">
            <CardHeader>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <CardTitle>Roles ENS designados</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        <Cifra class="font-semibold text-foreground" :valor="cobertura.designados" />
                        de {{ cobertura.exigibles }} nombramientos vigentes
                    </p>
                </div>
            </CardHeader>

            <CardContent class="space-y-3">
                <p v-if="cobertura.faltan.length === 0" class="text-sm text-muted-foreground">
                    Todos los sistemas tienen designados los cinco roles que el ENS exige.
                </p>
                <ul v-else class="flex flex-wrap gap-2">
                    <li
                        v-for="hueco in huecosALaVista"
                        :key="`${hueco.sistema}-${hueco.rol}`"
                        class="rounded-full px-2.5 py-0.5 text-xs text-muted-foreground ring-1 ring-foreground/10"
                    >
                        <span class="cifra">{{ hueco.sistema }}</span> · {{ hueco.rol }}
                    </li>
                    <li v-if="huecosDeMas > 0" class="px-1 py-0.5 text-xs text-muted-foreground">
                        y {{ huecosDeMas }} más
                    </li>
                </ul>

                <p class="text-xs text-muted-foreground">
                    Un nombramiento se registra desde la ficha de la persona. Statera no comprueba
                    que esté firmado ni que quien lo recibe tenga la competencia que pide
                    <span class="cifra">mp.per.1</span>.
                </p>
            </CardContent>
        </Card>

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />

        <p class="text-xs text-muted-foreground">
            Estas personas no son cuentas de Statera: la mayoría de una plantilla no entra nunca en
            la herramienta. Los responsables de activos, tareas y evidencias siguen siendo cuentas,
            y <Link href="/perfil" class="underline underline-offset-4">el perfil</Link> es donde se
            gestiona la propia.
        </p>
    </AppLayout>
</template>
