<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import { Link } from '@inertiajs/vue3';

type Correspondencia = App.Http.Resources.Implantacion.Correspondencia;

/**
 * Los requisitos de otros marcos que cubren lo mismo que éste.
 *
 * Es el problema que resuelve el producto: hoy esto son dos hojas de cálculo
 * que nadie sincroniza. Una cobertura parcial se dice, no se insinúa: dar por
 * buena una equivalencia que no lo es es la forma más rápida de dar por
 * implantado algo que no lo está.
 */
defineProps<{ correspondencias: Correspondencia[] }>();
</script>

<template>
    <div v-if="correspondencias.length > 0" class="space-y-4">
        <article v-for="correspondencia in correspondencias" :key="correspondencia.requisitoId" class="rounded-xl border p-4">
            <header class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2">
                        <span class="cifra text-sm font-medium">{{ correspondencia.codigo }}</span>
                        <CeldaBadge
                            v-if="correspondencia.marco"
                            :valor="{ valor: correspondencia.marco, etiqueta: correspondencia.marco, tono: 'marco' }"
                        />
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ correspondencia.titulo }}</p>
                </div>

                <span
                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                        correspondencia.cubreDelTodo
                            ? 'bg-accent text-accent-foreground'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    {{ correspondencia.tipo }}
                </span>
            </header>

            <p v-if="correspondencia.nota" class="mt-2 text-sm">{{ correspondencia.nota }}</p>

            <ul v-if="correspondencia.implantaciones.length > 0" class="mt-3 space-y-1.5">
                <li
                    v-for="estado in correspondencia.implantaciones"
                    :key="estado.implantacionId"
                    class="flex flex-wrap items-center gap-2 text-sm"
                >
                    <Link
                        :href="`/implantaciones/${estado.implantacionId}`"
                        class="cifra underline-offset-4 hover:underline"
                    >
                        {{ estado.sistema }}
                    </Link>
                    <CeldaBadge
                        :valor="{ valor: estado.estado, etiqueta: estado.estadoEtiqueta, tono: estado.estado }"
                    />
                </li>
            </ul>

            <p v-else class="mt-3 text-sm text-muted-foreground">
                Ningún sistema de la organización tiene todavía este requisito en su alcance.
            </p>
        </article>
    </div>

    <EstadoVacio
        v-else
        titulo="Sin correspondencias"
        descripcion="El catálogo no declara ningún requisito de otro marco que cubra lo mismo que éste."
    />
</template>
