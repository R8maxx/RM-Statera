<script setup lang="ts">
import Logotipo from '@/components/Logotipo.vue';
import SelectorTema from '@/components/SelectorTema.vue';
import { Button } from '@/components/ui/button';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import { MotionConfig, motion } from 'motion-v';
import { computed } from 'vue';

/**
 * La pantalla de error.
 *
 * Antes no existía: un 404 devolvía la página de Symfony en blanco y negro, que
 * no dice a dónde ir ni se parece a la herramienta. Y un 404 aquí es más
 * frecuente de lo normal, porque el aislamiento multi-tenant responde 404 —no
 * 403— cuando alguien pide un recurso de otra organización: decir «existe pero
 * no es tuyo» ya sería filtrar información.
 */
const props = defineProps<{ estado: number }>();

const pagina = usePage();
const autenticado = computed(() => pagina.props.auth?.usuario != null);

const { variantesEntrada } = useMovimientoReducido();

const textos: Record<number, { titulo: string; explicacion: string }> = {
    403: {
        titulo: 'No tienes permiso para esto',
        explicacion:
            'Tu cuenta existe y la sesión es válida, pero tu rol no alcanza a esta pantalla. Si necesitas entrar, pídeselo al responsable de seguridad de tu organización.',
    },
    404: {
        titulo: 'Aquí no hay nada',
        explicacion:
            'La dirección no corresponde a ninguna pantalla, o el registro que buscas no pertenece a tu organización. Comprueba el enlace si te lo ha pasado alguien.',
    },
    419: {
        titulo: 'La sesión ha caducado',
        explicacion:
            'Has estado demasiado tiempo sin actividad y hemos cerrado la sesión. Vuelve a entrar; lo que estuvieras escribiendo no se ha guardado.',
    },
    429: {
        titulo: 'Demasiados intentos',
        explicacion: 'Espera un minuto antes de volver a probar.',
    },
    500: {
        titulo: 'Algo se ha roto por nuestro lado',
        explicacion:
            'El error queda registrado con su traza. Si te bloquea, avisa a quien administre la instalación con la hora aproximada.',
    },
    503: {
        titulo: 'Statera está en mantenimiento',
        explicacion: 'Volvemos en unos minutos. Los datos no se tocan durante el mantenimiento.',
    },
};

const texto = computed(
    () =>
        textos[props.estado] ?? {
            titulo: 'Algo no ha ido bien',
            explicacion: 'La petición no se ha podido completar.',
        },
);

const destino = computed(() =>
    autenticado.value
        ? { href: '/panel', etiqueta: 'Volver al panel' }
        : { href: '/login', etiqueta: 'Ir a la pantalla de acceso' },
);

/** El historial no se toca desde la plantilla: `window` no está en su ámbito. */
const volver = (): void => window.history.back();

/** Sin historial previo el botón no lleva a ninguna parte, así que no sale. */
const hayHistorial = typeof window !== 'undefined' && window.history.length > 1;
</script>

<template>
    <Head :title="texto.titulo" />

    <MotionConfig reduced-motion="user">
        <div class="flex min-h-[100dvh] flex-col px-6 sm:px-10">
            <header class="flex h-16 shrink-0 items-center justify-between">
                <Link :href="destino.href" class="rounded-md" aria-label="Statera, inicio">
                    <Logotipo />
                </Link>
                <SelectorTema />
            </header>

            <motion.main
                :variants="variantesEntrada"
                initial="oculto"
                animate="visible"
                class="mx-auto w-full max-w-lg pt-[12vh] pb-20"
            >
                <p class="cifra text-sm font-medium text-primary">Error {{ estado }}</p>

                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-balance">{{ texto.titulo }}</h1>

                <p class="mt-4 text-sm leading-relaxed text-muted-foreground text-pretty">
                    {{ texto.explicacion }}
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <Link :href="destino.href">
                        <Button class="gap-1.5">
                            <ArrowLeftIcon class="size-4" />
                            {{ destino.etiqueta }}
                        </Button>
                    </Link>

                    <button
                        v-if="hayHistorial"
                        type="button"
                        class="rounded px-1 text-sm text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline"
                        @click="volver"
                    >
                        Volver a la página anterior
                    </button>
                </div>
            </motion.main>
        </div>
    </MotionConfig>
</template>
