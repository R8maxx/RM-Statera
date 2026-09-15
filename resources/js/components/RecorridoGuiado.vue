<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { useRecorrido } from '@/composables/useRecorrido';
import type { LadoRecorrido } from '@/lib/recorridos';
import { ArrowLeftIcon, ArrowRightIcon, XIcon } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * El recorrido guiado sobre la propia interfaz.
 *
 * **Sin librería, y no por deporte.** Intro.js o Shepherd traen su propio
 * lenguaje visual —sus sombras, sus radios, sus botones— y aquí lo que se pinta
 * tiene que salir de los tokens como todo lo demás; adaptarlas cuesta más que
 * las ciento cincuenta líneas que ocupa esto. Es el mismo reparto que ya
 * decidió que la balanza, la rejilla del calendario y las gráficas se
 * escribieran a mano.
 *
 * **El foco es un recorte, no un dibujo.** Un único elemento colocado sobre el
 * objetivo con una sombra de extensión enorme oscurece todo lo que queda fuera;
 * lo de dentro es el hueco. Una máscara SVG o cuatro divs formando un marco
 * hacen lo mismo con más piezas que mantener.
 *
 * **No bloquea la página, a propósito.** El velo no intercepta el puntero: una
 * sombra no es alcanzable por el ratón, así que todo lo de debajo se sigue
 * pudiendo pulsar. Un recorrido que secuestra la interfaz obliga a terminarlo
 * para poder trabajar, y eso es exactamente lo que enseña a cerrar las cosas sin
 * leerlas. Lo que sí se sostiene es el foco del teclado dentro del panel
 * mientras está abierto, porque con el foco suelto no hay forma de saber qué
 * hace la flecha derecha. `Esc` sale siempre y en cualquier paso.
 */

const { abierto, indice, paso, total, esUltimo, cerrar, avanzar, retroceder } = useRecorrido();
const { reducido } = useMovimientoReducido();

/** El rectángulo del objetivo, en coordenadas de ventana. `null` = sin ancla viva. */
const marco = ref<{ top: number; left: number; width: number; height: number } | null>(null);
const panel = ref<HTMLElement | null>(null);
const anchoVentana = ref(0);
const altoVentana = ref(0);

/** Quién tenía el foco antes de abrir, para devolvérselo al cerrar. */
let focoPrevio: HTMLElement | null = null;

/** Aire alrededor del elemento señalado. Cuatro píxeles, como la retícula. */
const AIRE = 8;

/** Por debajo de esto el panel se ancla abajo y deja de perseguir al objetivo. */
const ESTRECHO = 640;

const esEstrecho = computed(() => anchoVentana.value < ESTRECHO);

/** Las anclas vivas de un paso, en el orden que declaró. */
const candidatas = (): HTMLElement[] => {
    const actual = paso.value;

    if (!actual) {
        return [];
    }

    return actual.anclas
        .map((ancla) => document.querySelector<HTMLElement>(`[data-recorrido="${ancla}"]`))
        .filter((elemento): elemento is HTMLElement => {
            /*
             * Un elemento con área cero está en el DOM pero no en la pantalla
             * —el sidebar plegado, una tarjeta dentro de un `v-if` que aún no ha
             * pintado—. Señalarlo dibujaría un foco de un píxel en una esquina.
             */
            if (!elemento) {
                return false;
            }

            const caja = elemento.getBoundingClientRect();

            return caja.width > 0 && caja.height > 0;
        });
};

/** Si la caja cabe entera en la ventana, sin contar con desplazar nada. */
const estaALaVista = (elemento: HTMLElement): boolean => {
    const caja = elemento.getBoundingClientRect();

    return caja.top >= 0 && caja.bottom <= window.innerHeight;
};

const medir = (elemento: HTMLElement | null): void => {
    if (!elemento) {
        marco.value = null;

        return;
    }

    const caja = elemento.getBoundingClientRect();

    marco.value = {
        top: caja.top - AIRE,
        left: caja.left - AIRE,
        width: caja.width + AIRE * 2,
        height: caja.height + AIRE * 2,
    };
};

/**
 * Cuál de las anclas se señala.
 *
 * **Gana la primera que YA esté a la vista**, aunque el paso prefiriese otra.
 * Es lo que evita que el recorrido arrastre la página bajo el cursor a cada
 * paso: entre señalar la tarjeta de Sistemas que está mil ochocientos píxeles
 * más abajo y señalar «Sistemas» en el lateral, que está delante, la segunda
 * enseña lo mismo y no mueve el suelo. Sólo se desplaza cuando ninguna está a la
 * vista, y entonces se desplaza una vez.
 *
 * Si no queda ninguna viva, el paso se pinta centrado y sin foco: el mismo
 * recorrido tiene que servir en una organización vacía y en una con seis meses
 * dentro, y los elementos de la primera no están en la segunda.
 */
const localizar = (): void => {
    // La ventana se mide aquí y en ningún otro sitio: `localizar` es lo que
    // corre al abrir, al cambiar de paso, al redimensionar y al desplazar.
    anchoVentana.value = window.innerWidth;
    altoVentana.value = window.innerHeight;

    const vivas = candidatas();

    if (vivas.length === 0) {
        marco.value = null;

        return;
    }

    medir(vivas.find(estaALaVista) ?? vivas[0]);
};

const acercar = async (): Promise<void> => {
    anchoVentana.value = window.innerWidth;
    altoVentana.value = window.innerHeight;

    const vivas = candidatas();

    if (vivas.length === 0) {
        marco.value = null;

        return;
    }

    if (vivas.some(estaALaVista)) {
        localizar();

        return;
    }

    vivas[0].scrollIntoView({ block: 'center', behavior: reducido.value ? 'auto' : 'smooth' });

    await nextTick();
    localizar();

    // El desplazamiento suave termina después: se vuelve a medir al asentar.
    if (!reducido.value) {
        window.setTimeout(localizar, 320);
    }
};

/**
 * Dónde se coloca el panel.
 *
 * El lado que pide el paso es una preferencia, no una orden: si ahí no cabe, se
 * usa el contrario, y al final todo se recorta contra la ventana. Un panel que
 * se sale por el borde derecho es peor que uno que no está donde se pidió.
 */
const ANCHO_PANEL = 380;
const ALTO_ESTIMADO = 260;
const MARGEN = 16;

const posicion = computed<Record<string, string>>((): Record<string, string> => {
    if (esEstrecho.value || !marco.value) {
        return {};
    }

    const caja = marco.value;
    const lado: LadoRecorrido = elegirLado(caja, paso.value?.lado ?? 'abajo');

    let top: number;
    let left: number;

    if (lado === 'abajo') {
        top = caja.top + caja.height + MARGEN;
        left = caja.left + caja.width / 2 - ANCHO_PANEL / 2;
    } else if (lado === 'arriba') {
        top = caja.top - ALTO_ESTIMADO - MARGEN;
        left = caja.left + caja.width / 2 - ANCHO_PANEL / 2;
    } else if (lado === 'derecha') {
        top = caja.top + caja.height / 2 - ALTO_ESTIMADO / 2;
        left = caja.left + caja.width + MARGEN;
    } else {
        top = caja.top + caja.height / 2 - ALTO_ESTIMADO / 2;
        left = caja.left - ANCHO_PANEL - MARGEN;
    }

    return {
        top: `${recortar(top, MARGEN, altoVentana.value - ALTO_ESTIMADO - MARGEN)}px`,
        left: `${recortar(left, MARGEN, anchoVentana.value - ANCHO_PANEL - MARGEN)}px`,
    };
});

const recortar = (valor: number, minimo: number, maximo: number): number =>
    Math.max(minimo, Math.min(valor, Math.max(minimo, maximo)));

const elegirLado = (
    caja: { top: number; left: number; width: number; height: number },
    preferido: LadoRecorrido,
): LadoRecorrido => {
    const cabe: Record<LadoRecorrido, boolean> = {
        abajo: altoVentana.value - (caja.top + caja.height) > ALTO_ESTIMADO + MARGEN * 2,
        arriba: caja.top > ALTO_ESTIMADO + MARGEN * 2,
        derecha: anchoVentana.value - (caja.left + caja.width) > ANCHO_PANEL + MARGEN * 2,
        izquierda: caja.left > ANCHO_PANEL + MARGEN * 2,
    };

    if (cabe[preferido]) {
        return preferido;
    }

    const contrario: Record<LadoRecorrido, LadoRecorrido> = {
        arriba: 'abajo',
        abajo: 'arriba',
        izquierda: 'derecha',
        derecha: 'izquierda',
    };

    if (cabe[contrario[preferido]]) {
        return contrario[preferido];
    }

    return (['abajo', 'arriba', 'derecha', 'izquierda'] as const).find((lado) => cabe[lado]) ?? 'abajo';
};

/*
 * El foco se sostiene dentro del panel. Sin esto, la primera pulsación de
 * tabulador se va a la página de debajo y a partir de ahí las flechas dejan de
 * significar nada, porque el manejador cuelga del panel.
 */
const enfocables = (): HTMLElement[] =>
    Array.from(panel.value?.querySelectorAll<HTMLElement>('button:not([disabled])') ?? []);

const alTabular = (evento: KeyboardEvent): void => {
    const lista = enfocables();

    if (lista.length === 0) {
        return;
    }

    const primero = lista[0];
    const ultimo = lista[lista.length - 1];
    const activo = document.activeElement;

    if (evento.shiftKey && activo === primero) {
        evento.preventDefault();
        ultimo.focus();
    } else if (!evento.shiftKey && activo === ultimo) {
        evento.preventDefault();
        primero.focus();
    }
};

const alPulsar = (evento: KeyboardEvent): void => {
    if (evento.key === 'Escape') {
        evento.preventDefault();
        cerrar();
    } else if (evento.key === 'ArrowRight') {
        evento.preventDefault();
        avanzar();
    } else if (evento.key === 'ArrowLeft') {
        evento.preventDefault();
        retroceder();
    } else if (evento.key === 'Tab') {
        alTabular(evento);
    }
};

const alRedimensionar = (): void => localizar();

watch(abierto, async (esta) => {
    if (esta) {
        focoPrevio = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        window.addEventListener('resize', alRedimensionar);
        window.addEventListener('scroll', alRedimensionar, { passive: true });
        await acercar();
        await nextTick();
        enfocables()[0]?.focus();

        return;
    }

    window.removeEventListener('resize', alRedimensionar);
    window.removeEventListener('scroll', alRedimensionar);
    marco.value = null;

    /*
     * Cuando el recorrido lo abrió el menú, se vuelve al menú. Cuando arrancó
     * solo al entrar, no había foco previo y dejarlo en `body` manda al teclado
     * al principio de todo: se aterriza en el contenido, que es donde estaba
     * mirando quien acaba de cerrarlo.
     */
    const destino = focoPrevio ?? document.getElementById('contenido');
    destino?.focus();
    focoPrevio = null;
});

watch(indice, async () => {
    if (!abierto.value) {
        return;
    }

    await acercar();
    await nextTick();
    enfocables()[0]?.focus();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', alRedimensionar);
    window.removeEventListener('scroll', alRedimensionar);
});

const transicion = computed(() =>
    reducido.value ? 'none' : 'top 220ms var(--ease-marca), left 220ms var(--ease-marca), width 220ms var(--ease-marca), height 220ms var(--ease-marca)',
);
</script>

<template>
    <Teleport to="body">
        <div v-if="abierto && paso" class="pointer-events-none fixed inset-0 z-[60]">
            <!--
                El foco. Cuando hay ancla es un recorte; cuando no la hay, el
                mismo elemento se estira a toda la ventana y hace de velo liso,
                que es lo que corresponde a un paso que no señala nada concreto.
            -->
            <div
                class="absolute rounded-lg"
                :style="
                    marco
                        ? {
                              top: `${marco.top}px`,
                              left: `${marco.left}px`,
                              width: `${marco.width}px`,
                              height: `${marco.height}px`,
                              /*
                               * El anillo va DENTRO de esta misma sombra y no en
                               * un `ring-2` de Tailwind: `ring` también se pinta
                               * con `box-shadow`, así que la sombra en línea lo
                               * borraba entero y el foco se quedaba sin borde.
                               * No se veía en el código; se vio en la pantalla.
                               */
                              /*
                               * La extensión va en píxeles y no en `100vmax`:
                               * una unidad de ventana habría que revisarla en
                               * cada cambio de tamaño, y aquí sólo hace falta
                               * un número lo bastante grande para tapar
                               * cualquier pantalla desde cualquier recorte.
                               */
                              boxShadow: '0 0 0 2px var(--primary), 0 0 0 9999px var(--velo)',
                              transition: transicion,
                          }
                        : {
                              inset: '0',
                              boxShadow: 'none',
                              backgroundColor: 'var(--velo)',
                          }
                "
            />

            <!-- ── El panel del paso ──────────────────────────────────────── -->
            <div
                ref="panel"
                role="dialog"
                :aria-labelledby="`recorrido-titulo-${paso.clave}`"
                :aria-describedby="`recorrido-cuerpo-${paso.clave}`"
                tabindex="-1"
                class="pointer-events-auto fixed rounded-xl border bg-card p-5 shadow-sombra-3
                       max-sm:inset-x-4 max-sm:bottom-4 sm:w-[380px]"
                :style="posicion"
                :class="!esEstrecho && !marco && 'sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2'"
                @keydown="alPulsar"
            >
                <!-- El contador se anuncia al cambiar de paso; el título solo no
                     dice si quedan dos pasos o siete. -->
                <p class="cifra text-xs text-muted-foreground" aria-live="polite">
                    Paso {{ indice + 1 }} de {{ total }}
                </p>

                <h2 :id="`recorrido-titulo-${paso.clave}`" class="mt-1.5 text-base font-semibold tracking-tight">
                    {{ paso.titulo }}
                </h2>

                <p :id="`recorrido-cuerpo-${paso.clave}`" class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    {{ paso.cuerpo }}
                </p>

                <div class="mt-5 flex items-center gap-2">
                    <!-- El progreso se lee de un vistazo y no gasta una línea de
                         texto. Es decorativo para quien ve, así que el lector de
                         pantalla ya tiene el contador de arriba. -->
                    <div class="flex flex-1 items-center gap-1" aria-hidden="true">
                        <span
                            v-for="(_, numero) in total"
                            :key="numero"
                            class="h-1 flex-1 rounded-full transition-colors"
                            :class="numero <= indice ? 'bg-primary' : 'bg-border'"
                        />
                    </div>

                    <Button
                        v-if="indice > 0"
                        variant="ghost"
                        size="sm"
                        aria-label="Paso anterior"
                        @click="retroceder"
                    >
                        <ArrowLeftIcon />
                        Atrás
                    </Button>

                    <Button size="sm" @click="avanzar">
                        {{ esUltimo ? 'Entendido' : 'Siguiente' }}
                        <ArrowRightIcon v-if="!esUltimo" />
                    </Button>
                </div>

                <!--
                    Salir está siempre a la vista y no escondido detrás de un
                    aspa de 12 px en una esquina: quien ya conoce la herramienta
                    tiene que poder irse en el primer paso sin buscar nada.
                -->
                <Button
                    variant="ghost"
                    size="icon-sm"
                    class="absolute top-3 right-3 text-muted-foreground"
                    aria-label="Salir del recorrido"
                    @click="cerrar"
                >
                    <XIcon />
                </Button>
            </div>
        </div>
    </Teleport>
</template>
