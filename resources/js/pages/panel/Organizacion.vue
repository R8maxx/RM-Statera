<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import CabeceraPanel from '@/components/panel/CabeceraPanel.vue';
import ListaAcciones, { type Accion } from '@/components/panel/ListaAcciones.vue';
import ResumenInventarioPanelCard from '@/components/activo/ResumenInventarioPanel.vue';
import ResumenPersonasPanel from '@/components/persona/ResumenPersonasPanel.vue';
import ResumenProveedoresPanel from '@/components/proveedor/ResumenProveedoresPanel.vue';
import { Card } from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible } from '@/lib/celdas';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';
import { CloudSunIcon, CompassIcon, LinkIcon, UsersIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed } from 'vue';

/**
 * «¿De qué estamos hablando?» — la tercera vista del panel.
 *
 * **El elemento fuerte es el contexto**, porque enmarca a los otros tres: el
 * alcance sale de él, y de ahí salen la plantilla, los proveedores y el
 * inventario. La tarjeta lleva las cuestiones en los cuatro cuadrantes del DAFO
 * —con su tono y su icono, porque un cuadrante vacío dice algo— y, al lado, lo
 * que queda por atar de las cláusulas 4.1 y 4.2.
 *
 * Detrás, personas y proveedores en tarjetas de mitad, y el inventario a todo el
 * ancho: sus repartos —por tipo, por ciclo de vida, cobertura de cifrado y
 * copia— viven aquí y no en `/activos`, que es donde se viene a trabajar.
 *
 * **Es la que menos se abre, y por eso es la tercera.** Una plantilla se mueve
 * por altas y bajas y un DAFO se revisa una vez al año.
 */
const props = defineProps<{
    vistas: App.Http.Resources.Panel.VistaPanel[];
    contexto: App.Http.Resources.Panel.ResumenContextoPanel | null;
    personas: App.Http.Resources.Panel.ResumenPersonasPanel | null;
    inventario: App.Http.Resources.Panel.ResumenInventarioPanel;
    proveedores: App.Http.Resources.Panel.ResumenProveedoresPanel | null;
}>();

const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();
const escalonado = variantesEscalonado(0.05);

const hayContexto = computed(() => (props.contexto?.cuestiones ?? 0) > 0);
const hayPersonas = computed(() => (props.personas?.total ?? 0) > 0);
const hayProveedores = computed(() => (props.proveedores?.total ?? 0) > 0);

/* Ver la nota de `Ciclo.vue`: una pestaña en blanco parece rota. */
const vacia = computed(
    () => !hayContexto.value && !hayPersonas.value && props.inventario.vigentes === 0 && !hayProveedores.value,
);

const antiguedad = computed(() => {
    const meses = props.contexto?.mesesDesdeElAnalisis ?? null;

    if (meses === null) {
        return null;
    }

    if (meses < 1) {
        return 'este mes';
    }

    return meses === 1 ? 'hace un mes' : `hace ${meses} meses`;
});

const conRiesgo = computed(() => Math.max((props.contexto?.cuestiones ?? 0) - (props.contexto?.sinRiesgo ?? 0), 0));

/*
 * Lo que queda por atar. Nada de esto es rojo: una cuestión sin riesgo o un
 * requisito sin cubrir es la distancia que queda, no algo que ya va mal
 * (DESIGN.md § 3). El cambio climático —la enmienda de 2024 a la 4.1— sale sólo
 * mientras nadie lo ha evaluado, que es el único estado que pide algo.
 */
const acciones = computed<Accion[]>(() => {
    const contexto = props.contexto;

    if (contexto === null) {
        return [];
    }

    return [
        {
            clave: 'sin_riesgo',
            etiqueta: 'Cuestiones sin riesgo vinculado',
            detalle: 'Una cuestión que no llega a riesgo no llega al plan.',
            valor: contexto.sinRiesgo,
            href: '/contexto/cuestiones?filter[sin_riesgo]=1',
            icono: LinkIcon,
        },
        {
            clave: 'obligacion_sin_cubrir',
            etiqueta: 'Requisitos de partes interesadas sin cubrir',
            detalle: `${contexto.requisitosQueObligan} obligan, de ${contexto.partes} partes interesadas.`,
            valor: contexto.obligacionesSinCubrir,
            href: '/partes-interesadas?filter[obligacion_sin_cubrir]=1',
            icono: UsersIcon,
        },
        {
            clave: 'clima',
            etiqueta: 'Cambio climático sin evaluar',
            detalle: 'La enmienda de 2024 a la cláusula 4.1 pide decidir si es pertinente.',
            valor: contexto.climaPertinente === null ? 1 : 0,
            href: '/contexto',
            icono: CloudSunIcon,
        },
    ];
});
</script>

<template>
    <AppLayout titulo="La organización">
        <CabeceraPanel :vistas="vistas" />

        <motion.div :variants="escalonado" initial="oculto" animate="visible" class="space-y-8">
            <motion.section v-if="vacia" :variants="variantesEntrada">
                <EstadoVacio
                    :icono="CompassIcon"
                    titulo="Todavía no hay contexto registrado"
                    descripcion="Aquí aparecen el análisis del entorno, la plantilla con sus roles ENS, los proveedores y el inventario de activos. La cláusula 4.1 pide empezar por determinar las cuestiones internas y externas: es de donde salen los riesgos."
                    :accion="{ etiqueta: 'Empezar por el contexto', href: '/contexto' }"
                />
            </motion.section>

            <!-- ── El elemento fuerte: el contexto ────────────────────────── -->
            <motion.section v-if="contexto && hayContexto" :variants="variantesEntrada">
                <Card class="gap-0 overflow-hidden py-0 lg:flex-row">
                    <div class="min-w-0 flex-1 space-y-6 p-6 sm:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold tracking-[-0.01em]">Contexto de la organización</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    <template v-if="contexto.analisisVigente">
                                        {{ contexto.analisisVigente }}
                                        <template v-if="contexto.fechaAnalisis">
                                            · aprobado el {{ fechaLegible(contexto.fechaAnalisis) }}, {{ antiguedad }}
                                        </template>
                                    </template>
                                    <template v-else>Sin análisis aprobado todavía.</template>
                                    <template v-if="contexto.hayBorrador"> · hay un borrador en preparación</template>
                                </p>
                            </div>
                            <Link href="/contexto" class="shrink-0 text-sm font-medium text-primary underline-offset-4 hover:underline">
                                Abrir el análisis
                            </Link>
                        </div>

                        <div class="flex flex-wrap items-end gap-x-5 gap-y-2">
                            <Cifra
                                class="cifra text-6xl leading-none font-semibold tracking-[-0.03em] sm:text-7xl"
                                :valor="contexto.cuestiones"
                            />
                            <div class="pb-1.5">
                                <p class="font-semibold">
                                    {{ contexto.cuestiones === 1 ? 'cuestión interna o externa' : 'cuestiones internas y externas' }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    <span class="cifra">{{ conRiesgo }}</span> ya con un riesgo vinculado
                                </p>
                            </div>
                        </div>

                        <!--
                            Los cuatro cuadrantes, vacíos incluidos: un DAFO sin
                            ninguna oportunidad es una organización que sólo ha
                            mirado lo que le puede salir mal, y esconder el
                            cuadrante esconde justo eso. Tono e icono del dominio.
                        -->
                        <ul class="grid gap-3 sm:grid-cols-2">
                            <li v-for="tramo in contexto.porTipo" :key="tramo.clave">
                                <Link
                                    :href="tramo.filtro ? `/contexto/cuestiones?${tramo.filtro}` : '/contexto/cuestiones'"
                                    class="flex min-h-14 items-center gap-3 rounded-lg px-4 py-3 transition-opacity hover:opacity-85"
                                    :class="tono(tramo.tono).badge"
                                >
                                    <IconoTipo :nombre="tramo.icono" clase="size-5 shrink-0" />
                                    <span class="min-w-0 flex-1 text-sm font-semibold">{{ tramo.etiqueta }}</span>
                                    <Cifra class="cifra text-xl font-semibold" :valor="tramo.valor" />
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div class="border-t p-6 sm:p-8 lg:w-[26rem] lg:shrink-0 lg:border-t-0 lg:border-l">
                        <ListaAcciones
                            titulo="Lo que queda por atar"
                            descripcion="Cláusulas 4.1 y 4.2 de ISO/IEC 27001:2022."
                            :acciones="acciones"
                            vacio="Cada cuestión tiene su riesgo y cada requisito está cubierto."
                        />
                    </div>
                </Card>
            </motion.section>

            <!-- ── Personas y proveedores ─────────────────────────────────── -->
            <motion.section
                v-if="(personas && hayPersonas) || (proveedores && hayProveedores)"
                :variants="variantesEntrada"
                class="grid gap-6 lg:grid-cols-2 [&>*]:h-full"
            >
                <ResumenPersonasPanel v-if="personas && hayPersonas" :resumen="personas" />
                <!-- Detrás de la plantilla: de quién depende lo que hay dentro. -->
                <ResumenProveedoresPanel v-if="proveedores && hayProveedores" :resumen="proveedores" />
            </motion.section>

            <!-- ── Inventario ─────────────────────────────────────────────── -->
            <!-- Donde aterriza todo lo anterior: sobre estos activos se aplican
                 las medidas que el contexto enmarca y la plantilla sostiene. -->
            <motion.section v-if="inventario.vigentes > 0" :variants="variantesEntrada">
                <ResumenInventarioPanelCard :inventario="inventario" />
            </motion.section>
        </motion.div>
    </AppLayout>
</template>
