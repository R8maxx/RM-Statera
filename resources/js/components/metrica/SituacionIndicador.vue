<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import { computed } from 'vue';

/**
 * La franja de arriba de la ficha de un indicador: dónde está hoy.
 *
 * Es el elemento fuerte de la pantalla (DESIGN.md § 1), y por eso la cifra es
 * grande y lo demás está en calma. **La cifra va siempre con su denominador**
 * —«33 de 52»— porque un porcentaje suelto es exactamente lo que § 1 prohíbe.
 *
 * Todo llega escrito del servidor (`Domain\Metrica\Situacion`): la distancia de
 * un porcentaje son puntos y la de unos euros son euros, y componerlo aquí sería
 * la segunda copia de `UnidadIndicador::escribir()`.
 */
const props = defineProps<{
    situacion: App.Http.Resources.Metrica.SituacionIndicador;
    unidad: string;
}>();

/**
 * La cifra cuenta, como en el panel, porque resume: es la lectura de la ficha,
 * no una celda. Los euros no, porque su formato lo escribe `Coste` y NumberFlow
 * no sabe de él; se quedan escritos.
 */
const contable = computed(() => {
    switch (props.unidad) {
        case 'porcentaje':
            return { decimales: 0, sufijo: ' %' };
        case 'recuento':
            return { decimales: 0, sufijo: '' };
        case 'dias':
            return { decimales: 1, sufijo: ' d' };
        default:
            return null;
    }
});
</script>

<template>
    <section
        aria-label="Situación del indicador"
        class="grid rounded-xl border border-border bg-card md:grid-cols-[minmax(0,1.1fr)_minmax(0,1.2fr)_minmax(0,1fr)]"
    >
        <div class="flex flex-col gap-1.5 p-6">
            <span class="text-[13px] font-medium text-muted-foreground">Último periodo medido · {{ situacion.periodo }}</span>
            <span class="cifra text-5xl leading-tight font-bold tracking-tight">
                <Cifra
                    v-if="contable"
                    :valor="situacion.valor"
                    :decimales="contable.decimales"
                    :sufijo="contable.sufijo"
                />
                <template v-else>{{ situacion.valorEscrito }}</template>
            </span>
            <span v-if="situacion.fraccion" class="text-sm text-secondary-foreground tabular-nums">
                {{ situacion.fraccion }}
            </span>
        </div>

        <div class="flex flex-col gap-3 border-t border-border p-6 md:border-t-0 md:border-l">
            <span class="text-[13px] font-medium text-muted-foreground">
                <template v-if="situacion.objetivoEscrito">
                    Frente al objetivo <span class="cifra">{{ situacion.objetivoEscrito }}</span>
                </template>
                <template v-else>Sin objetivo declarado</template>
            </span>

            <!--
                La barra sólo en porcentaje, que tiene techo natural. El relleno y
                el marcador se mueven con `transform` —viajan, no reaparecen— y la
                marca del objetivo no se mueve nunca: es la referencia.
            -->
            <div v-if="situacion.posicion !== null" class="relative h-11 max-w-80" aria-hidden="true">
                <div class="absolute inset-x-0 top-4.5 h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full origin-left bg-primary transition-transform duration-(--duracion-lenta) ease-en-pantalla"
                        :style="{ transform: `scaleX(${situacion.posicion})` }"
                    />
                </div>
                <div
                    class="absolute inset-x-0 top-0 transition-transform duration-(--duracion-lenta) ease-en-pantalla"
                    :style="{ transform: `translateX(${situacion.posicion * 100}%)` }"
                >
                    <svg width="12" height="12" viewBox="0 0 12 12" class="-ml-1.5 fill-primary">
                        <path d="M1 1h10L6 9z" />
                    </svg>
                </div>
                <template v-if="situacion.posicionObjetivo !== null">
                    <div
                        class="absolute top-3 h-5 w-0.5 -translate-x-1/2 rounded-full bg-foreground"
                        :style="{ left: `${situacion.posicionObjetivo * 100}%` }"
                    />
                </template>
                <span class="cifra absolute top-8 left-0 text-xs text-muted-foreground">0</span>
                <span class="cifra absolute top-8 right-0 text-xs text-muted-foreground">100</span>
            </div>

            <span
                v-if="situacion.distancia"
                class="text-base font-semibold"
                :class="{ 'text-estado-implantado': situacion.alcanzado }"
            >
                {{ situacion.distancia }}
            </span>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-border p-6 md:border-t-0 md:border-l">
            <span class="text-[13px] font-medium text-muted-foreground">Desde el periodo anterior</span>
            <template v-if="situacion.variacion">
                <span class="cifra text-3xl leading-9 font-semibold">{{ situacion.variacion }}</span>
                <span class="text-sm text-secondary-foreground">{{ situacion.anterior }}</span>
            </template>
            <span v-else class="text-sm text-muted-foreground">
                Es la primera medición: con una sola no hay tendencia.
            </span>
        </div>
    </section>
</template>
