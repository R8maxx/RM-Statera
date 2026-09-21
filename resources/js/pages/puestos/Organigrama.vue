<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { Link } from '@inertiajs/vue3';
import { CornerDownRightIcon, UserIcon } from '@lucide/vue';
import { motion } from 'motion-v';

/**
 * El organigrama: un solo árbol con dos lecturas.
 *
 * Cada nodo es un **puesto** y lleva dentro **quién lo ocupa**, así que el
 * organigrama de puestos y el de personas son la misma pantalla. Con la
 * jerarquía en el puesto —y no en la persona—, que alguien entre o se vaya no lo
 * mueve, que es lo que hace que un organigrama de personas se quede viejo a las
 * dos semanas.
 *
 * **Lista sangrada y no diagrama de cajas**, que es lo que ya decidió
 * `GrafoDependencias` y por lo mismo: a 375 px un diagrama de nodos se lee peor
 * que la misma cadena en una lista, y DESIGN.md no admite scroll horizontal.
 * Queda declarado que el diagrama de cajas no está.
 */
interface Ocupante {
    id: number;
    nombre: string;
}

interface Nodo {
    id: number;
    codigo: string;
    titulo: string;
    profundidad: number;
    caracterizado: boolean;
    ocupantes: Ocupante[];
}

const props = defineProps<{
    nodos: Nodo[];
    sueltos: number;
    total: number;
    puedeGestionar: boolean;
}>();

/*
 * El escalonado lo declara el PADRE y los hijos sólo nombran su variante, que es
 * el patrón de `TiraIndicadores` y el único que funciona aquí: `motion.main` del
 * layout ya anima con etiquetas de variante —«oculto»/«visible»—, y esas
 * etiquetas **se heredan hasta los hijos**. Un `motion.li` que declare
 * `initial`/`animate` como objetos entra en conflicto con la etiqueta heredada y
 * se queda congelado en su estado inicial: los nodos salen en el DOM con
 * `opacity: 0` y la pantalla parece tener un solo puesto. Costó un rato y no se
 * ve leyendo el componente.
 */
const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();

const escalonado = variantesEscalonado(0.04);
</script>

<template>
    <AppLayout titulo="Organigrama">
        <CabeceraPagina
            titulo="Organigrama"
            descripcion="Quién depende de quién, por puesto y con quien lo ocupa al lado. La jerarquía vive en el puesto, así que no se mueve porque alguien entre o se vaya."
        >
            <template #acciones>
                <Link href="/puestos">
                    <Button variant="outline">Ver la tabla</Button>
                </Link>
            </template>
        </CabeceraPagina>

        <Card>
            <CardContent>
                <EstadoVacio
                    v-if="nodos.length === 0"
                    titulo="Todavía no hay puestos"
                    descripcion="El organigrama sale del catálogo de puestos: en cuanto haya uno, aparece aquí."
                />

                <motion.ul
                    v-else
                    :variants="escalonado"
                    initial="oculto"
                    animate="visible"
                    class="divide-y divide-border"
                >
                    <motion.li
                        v-for="nodo in nodos"
                        :key="nodo.id"
                        :variants="variantesEntrada"
                        class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0"
                    >
                        <div
                            class="flex min-w-0 flex-1 gap-2"
                            :style="{ paddingInlineStart: `${nodo.profundidad * 1.25}rem` }"
                        >
                            <CornerDownRightIcon
                                v-if="nodo.profundidad > 0"
                                class="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />

                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="`/puestos/${nodo.id}`"
                                        class="text-sm font-medium underline-offset-4 hover:underline"
                                    >
                                        <span class="cifra text-muted-foreground">{{ nodo.codigo }}</span>
                                        {{ nodo.titulo }}
                                    </Link>
                                </p>

                                <!--
                                    Quien lo ocupa va debajo del puesto y no al
                                    lado: un puesto lo pueden ocupar varias
                                    personas, y en una línea a 375 px eso se
                                    convierte en un párrafo.
                                -->
                                <p
                                    v-if="nodo.ocupantes.length > 0"
                                    class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                                >
                                    <UserIcon class="size-3 shrink-0" aria-hidden="true" />
                                    <Link
                                        v-for="ocupante in nodo.ocupantes"
                                        :key="ocupante.id"
                                        :href="`/personas/${ocupante.id}`"
                                        class="underline-offset-4 hover:underline"
                                    >
                                        {{ ocupante.nombre }}
                                    </Link>
                                </p>
                                <p v-else class="mt-0.5 text-xs text-muted-foreground">Sin ocupar</p>
                            </div>
                        </div>
                    </motion.li>
                </motion.ul>
            </CardContent>
        </Card>

        <!--
            Lo que la herramienta no hace, dicho donde se ve. Varias raíces no
            es un error —una organización puede tener dos ramas sin un puesto
            común por encima—, pero si son muchas suele significar que falta
            colgarlas de alguien.
        -->
        <p v-if="nodos.length > 0" class="text-xs text-muted-foreground">
            <template v-if="sueltos > 1">
                Hay <span class="cifra">{{ sueltos }}</span> puestos que no dependen de ninguno.
                Statera no exige un único puesto raíz ni comprueba que el organigrama esté
                completo: registra lo que se declare.
            </template>
            <template v-else>
                Statera no comprueba que el organigrama esté completo ni que quien ocupa un
                puesto reúna la competencia que ese puesto pide.
            </template>
        </p>
    </AppLayout>
</template>
