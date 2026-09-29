<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2Icon, ChevronRightIcon } from '@lucide/vue';
import { computed } from 'vue';

export interface FilaRegistro {
    clave: string;
    etiqueta: string;
    valor: number;
    /** La lista exacta que cuenta la cifra: el mismo scope, así que dicen lo mismo. */
    href: string;
    /** Sólo lo que ya va mal: DESIGN.md § 3 reserva el rojo a eso y a nada más. */
    alerta?: boolean;
}

/**
 * Un registro del panel en una tarjeta de tercio: la cifra que lo resume y lo
 * que pide acción, en filas que llevan a su lista.
 *
 * **Una forma para los nueve registros de las dos vistas secundarias.** Cada
 * módulo tenía su tarjeta a todo el ancho, cada una con su descripción larga, y
 * la vista del ciclo eran seis bloques del mismo peso apilados. Ahora el módulo
 * decide qué cuenta y qué es rojo —eso sigue en su componente, con su porqué— y
 * esto decide cómo se ve, una vez.
 *
 * **Las filas a cero no se pintan**: una línea que dice «0 fuera de plazo»
 * enseña a no leer la línea. Si no queda ninguna, una sola frase lo dice, que es
 * un estado vacío de verdad y no una columna de ceros.
 */
const props = withDefaults(
    defineProps<{
        titulo: string;
        href: string;
        cifra: number;
        /** Lo que cuenta la cifra: «abiertas», «vivas de». */
        unidad: string;
        /** El denominador, al lado siempre que lo haya: 2 sobre 3 no es 2 sobre 120. */
        de?: number | null;
        filas?: FilaRegistro[];
        vacio?: string;
    }>(),
    { de: null, filas: () => [], vacio: 'Nada pendiente.' },
);

const visibles = computed(() => props.filas.filter((fila) => fila.valor > 0));
</script>

<template>
    <Card class="gap-4">
        <CardHeader>
            <CardTitle>{{ titulo }}</CardTitle>
            <CardAction>
                <Link
                    :href="href"
                    class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                    :aria-label="`Ver ${titulo.toLowerCase()}`"
                >
                    Ver
                    <ChevronRightIcon class="size-4" aria-hidden="true" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="flex flex-1 flex-col gap-4">
            <p class="flex items-baseline gap-2">
                <Cifra class="text-3xl font-semibold tracking-tight" :valor="cifra" />
                <span class="text-sm text-muted-foreground">
                    {{ unidad }}
                    <template v-if="de !== null">de <Cifra class="cifra" :valor="de" /></template>
                </span>
            </p>

            <slot />

            <ul v-if="visibles.length > 0" class="mt-auto text-sm">
                <li v-for="fila in visibles" :key="fila.clave" class="border-t">
                    <Link
                        :href="fila.href"
                        class="-mx-2 flex min-h-10 items-center gap-2 rounded-md px-2 transition-colors hover:bg-fila-hover"
                    >
                        <span class="min-w-0 flex-1" :class="fila.alerta && 'text-destructive'">{{ fila.etiqueta }}</span>
                        <Cifra class="cifra font-medium" :class="fila.alerta && 'text-destructive'" :valor="fila.valor" />
                    </Link>
                </li>
            </ul>

            <p v-else class="mt-auto flex items-center gap-2 border-t pt-3 text-sm text-muted-foreground">
                <CheckCircle2Icon class="size-4 shrink-0 text-estado-implantado" aria-hidden="true" />
                {{ vacio }}
            </p>
        </CardContent>
    </Card>
</template>
