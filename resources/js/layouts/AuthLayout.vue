<script setup lang="ts">
import BalanzaPixeles from '@/components/BalanzaPixeles.vue';
import Logotipo from '@/components/Logotipo.vue';
import SelectorTema from '@/components/SelectorTema.vue';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { Head, Link } from '@inertiajs/vue3';
import { MotionConfig, motion } from 'motion-v';

/**
 * La entrada a Statera.
 *
 * Estructura tomada de los accesos que funcionan bien (el de Cloudflare, entre
 * otros): formulario estrecho a la izquierda y panel de marca sólido a la
 * derecha. Cuatro decisiones que parecen menores y no lo son:
 *
 * - **El formulario se ancla arriba, no se centra en vertical.** Con centrado,
 *   aparecer un aviso de error mueve todos los campos hacia abajo y hay que
 *   volver a buscar dónde estaba el cursor. Anclado, los campos no se mueven.
 * - **El selector de tema está en la columna del formulario, no en el panel.**
 *   El panel desaparece por debajo de `lg`, y en un móvil también se entra de
 *   noche.
 * - **Logotipo, título, campos, ayuda y pie forman una sola pila y comparten
 *   borde izquierdo.** El logotipo estaba pegado al borde del navegador y el
 *   formulario centrado en una columna de casi mil píxeles: sin ningún eje en
 *   común, se leían como dos cosas sueltas flotando en el mismo hueco. El pie
 *   anclado abajo cierra la composición, que es lo que le faltaba.
 * - **El símbolo no se repite.** El panel llevaba un `Logotipo` de 36 px justo
 *   encima de la balanza que gira: la misma figura dos veces en la misma
 *   superficie, y la pequeña sólo le restaba fuerza a la grande.
 *
 * La ayuda del `slot` «pie» se queda pegada al formulario y no baja con el pie
 * de página: es contextual —«el enlace caduca a los sesenta minutos»— y lejos
 * de los campos no la lee nadie.
 *
 * El panel es de color sólido en los dos temas a propósito: es una superficie
 * de marca, como lo sería una fotografía, no una sección de la página que se
 * haya quedado sin invertir. Encima gira la balanza de la marca en píxeles: el
 * único bucle ambiente de todo el producto, y el porqué está escrito en
 * `lib/motion.ts` junto a sus tiempos.
 *
 * En el panel, la balanza se queda con todo el hueco que la copia no usa y la
 * copia se ancla al pie. El grano vive en la esquina inferior izquierda, lejos
 * de ella, porque dos retículas de puntos juntas se estorban.
 *
 * El tinte de la columna del formulario es la única concesión decorativa de la
 * pantalla, y va acotada: sale de `--primary`, no de un hex ni de la escala,
 * para que funcione en los dos temas sin duplicar la regla.
 */
defineProps<{ titulo: string; descripcion?: string }>();

const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();
const escalonado = variantesEscalonado(0.06);

/** Lo que cubre la herramienta, con la referencia exacta de cada norma. */
const marcos = [
    { norma: 'ISO/IEC 27001:2022', detalle: 'Sistema de gestión de la seguridad de la información' },
    { norma: 'ENS', detalle: 'Real Decreto 311/2022, de 3 de mayo' },
];
</script>

<template>
    <Head :title="titulo" />

    <MotionConfig reduced-motion="user">
        <div class="grid min-h-[100dvh] lg:grid-cols-[1fr_32rem] xl:grid-cols-[1fr_42rem]">
            <!-- ── Formulario ─────────────────────────────────────────────── -->
            <div class="relative flex flex-col px-6 sm:px-10">
                <!--
                    Un halo de marca detrás del formulario, al 7 %. Una columna
                    de color liso con un formulario estrecho en medio se ve
                    vacía por muy bien repartida que esté; esto da profundidad
                    sin meter una imagen ni robarle contraste a los campos.
                -->
                <div
                    class="pointer-events-none absolute inset-0"
                    aria-hidden="true"
                    style="
                        background: radial-gradient(
                            58% 42% at 50% 20%,
                            color-mix(in oklab, var(--primary) 7%, transparent),
                            transparent 70%
                        );
                    "
                />

                <header class="relative flex h-16 shrink-0 items-center justify-end">
                    <SelectorTema />
                </header>

                <motion.main
                    :variants="escalonado"
                    initial="oculto"
                    animate="visible"
                    class="relative mx-auto w-full max-w-[26rem] flex-1 pt-[6vh] pb-12"
                >
                    <motion.div :variants="variantesEntrada">
                        <Link href="/login" class="inline-flex rounded-md" aria-label="Statera, inicio">
                            <Logotipo />
                        </Link>
                    </motion.div>

                    <motion.div :variants="variantesEntrada" class="mt-10">
                        <h1 class="text-2xl font-semibold tracking-tight text-balance">{{ titulo }}</h1>
                        <p v-if="descripcion" class="mt-2 text-sm text-muted-foreground text-pretty">
                            {{ descripcion }}
                        </p>
                    </motion.div>

                    <motion.div :variants="variantesEntrada" class="mt-8">
                        <slot />
                    </motion.div>

                    <motion.div
                        v-if="$slots.pie"
                        :variants="variantesEntrada"
                        class="mt-8 border-t pt-5 text-xs text-muted-foreground text-pretty"
                    >
                        <slot name="pie" />
                    </motion.div>
                </motion.main>

                <!--
                    El respaldo vive aquí y no en el panel: el panel no existe
                    por debajo de `lg`, y ponerlo en los dos sitios lo enseña
                    dos veces en la misma pantalla. Mismo ancho que la pila para
                    que caiga en su eje, no en el centro de la columna.
                -->
                <footer class="relative mx-auto w-full max-w-[26rem] pb-8 text-xs text-muted-foreground">
                    Statera, un producto de RM Technology.
                </footer>
            </div>

            <!-- ── Panel de marca ─────────────────────────────────────────── -->
            <aside class="relative hidden flex-col overflow-hidden bg-marca-800 p-12 lg:flex">
                <!--
                    Textura de puntos que se desvanece hacia los bordes. Una
                    superficie de color plano de treinta centímetros de ancho se
                    ve barata; esto le da grano sin meter una imagen que haya
                    que descargar antes de poder entrar. Ahora vive en la
                    esquina inferior izquierda y a menos intensidad: donde
                    estaba compite con la balanza y las dos retículas de puntos
                    se estorban.
                -->
                <div
                    class="pointer-events-none absolute inset-0 opacity-30"
                    aria-hidden="true"
                    style="
                        background-image: radial-gradient(currentColor 1px, transparent 1px);
                        background-size: 22px 22px;
                        color: color-mix(in oklab, var(--marca-300) 55%, transparent);
                        mask-image: radial-gradient(52% 26% at 4% 97%, #000 0%, transparent 70%);
                    "
                />

                <!--
                    La balanza ocupa el hueco que deja la copia, y de ahí sale
                    su tamaño. Antes iba en absoluto con anchos y altos en `rem`
                    por punto de ruptura, ajustados a ojo contra el navegador:
                    no crecía en una pantalla grande, no encogía en una baja y
                    cada cambio del reparto de la rejilla obligaba a volver a
                    medirlo. Con `flex-1` se adapta sola a cualquier ventana y
                    ya no hace falta máscara, porque no puede invadir la copia.
                -->
                <div class="pointer-events-none relative mb-10 min-h-0 flex-1" aria-hidden="true">
                    <BalanzaPixeles />
                </div>

                <div class="relative">
                    <!--
                        El filete de acento de DESIGN.md §6: 3 px por 40, en
                        violeta. Uno por bloque y nunca dos en la misma
                        pantalla; aquí es el único violeta fuera de la balanza.
                    -->
                    <span class="mb-7 block h-[3px] w-10 bg-violeta-400" aria-hidden="true" />

                    <p class="max-w-md text-3xl leading-[1.15] font-semibold tracking-tight text-marca-50 text-balance">
                        El ciclo completo de dos marcos, registrado una sola vez.
                    </p>

                    <p class="mt-4 max-w-sm text-sm text-marca-200 text-pretty">
                        Cada evidencia, cada tarea y cada documento se registran una vez y cuentan en todos los
                        marcos donde apliquen.
                    </p>

                    <dl class="mt-10 space-y-5 border-t border-marca-600/50 pt-8">
                        <div v-for="marco in marcos" :key="marco.norma" class="flex flex-col gap-0.5">
                            <dt class="cifra text-sm font-medium text-marca-100">{{ marco.norma }}</dt>
                            <dd class="text-sm text-marca-300">{{ marco.detalle }}</dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </div>
    </MotionConfig>
</template>
