<script setup lang="ts">
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/command';
import { usePaletaComandos } from '@/composables/usePaletaComandos';
import { useTema, type PreferenciaTema } from '@/composables/useTema';
import { navegacion } from '@/lib/navegacion';
import { router } from '@inertiajs/vue3';
import { MonitorIcon, MoonIcon, PlusIcon, SunIcon } from '@lucide/vue';
import { onKeyStroke, useMagicKeys, whenever } from '@vueuse/core';

/**
 * La paleta de comandos, con `⌘K`.
 *
 * Es la mayor ganancia de comodidad para quien vive dentro de la herramienta:
 * con diecinueve módulos, alcanzar cualquiera con dos pulsaciones y sin soltar
 * el teclado ahorra más tiempo que cualquier otra cosa de este rediseño.
 *
 * Lee el mismo mapa que el sidebar (`lib/navegacion.ts`), así que un módulo
 * nuevo aparece aquí sin tocar este fichero.
 *
 * De momento navega y ejecuta acciones. Buscar sistemas por nombre necesita un
 * extremo de servidor que todavía no existe; cuando entre el inventario de
 * activos se añade aquí un grupo más.
 */
const { abierta, abrir } = usePaletaComandos();
const { fijar } = useTema();

const { meta_k, ctrl_k } = useMagicKeys({
    passive: false,
    onEventFired(evento) {
        if (evento.key === 'k' && (evento.metaKey || evento.ctrlKey)) {
            evento.preventDefault();
        }
    },
});

whenever(meta_k, abrir);
whenever(ctrl_k, abrir);

/* `/` abre la paleta salvo si ya se está escribiendo en algún sitio. */
onKeyStroke('/', (evento) => {
    const activo = document.activeElement;
    const escribiendo =
        activo instanceof HTMLInputElement ||
        activo instanceof HTMLTextAreaElement ||
        (activo instanceof HTMLElement && activo.isContentEditable);

    if (!escribiendo) {
        evento.preventDefault();
        abrir();
    }
});

function ir(href: string): void {
    abierta.value = false;
    router.visit(href);
}

function tema(valor: PreferenciaTema): void {
    abierta.value = false;
    fijar(valor);
}

const temas: { valor: PreferenciaTema; etiqueta: string; icono: typeof SunIcon }[] = [
    { valor: 'claro', etiqueta: 'Tema claro', icono: SunIcon },
    { valor: 'oscuro', etiqueta: 'Tema oscuro', icono: MoonIcon },
    { valor: 'sistema', etiqueta: 'Tema del sistema', icono: MonitorIcon },
];
</script>

<template>
    <CommandDialog
        v-model:open="abierta"
        title="Buscar"
        description="Salta a un módulo o ejecuta una acción."
        class="max-w-xl"
    >
        <CommandInput placeholder="Buscar un módulo o una acción…" />

        <CommandList class="max-h-[22rem]">
            <CommandEmpty>Nada coincide con eso.</CommandEmpty>

            <CommandGroup v-for="grupo in navegacion" :key="grupo.titulo" :heading="grupo.titulo">
                <CommandItem
                    v-for="entrada in grupo.entradas"
                    :key="entrada.href"
                    :value="entrada.href"
                    @select="ir(entrada.href)"
                >
                    <component :is="entrada.icono" class="text-muted-foreground" />
                    {{ entrada.titulo }}
                    <!-- Los sinónimos entran en el índice de búsqueda, que se
                         construye con el texto del elemento, sin verse. -->
                    <span v-if="entrada.alias" class="sr-only">{{ entrada.alias.join(' ') }}</span>
                </CommandItem>
            </CommandGroup>

            <CommandSeparator />

            <CommandGroup heading="Acciones">
                <CommandItem value="crear-sistema" @select="ir('/sistemas/crear')">
                    <PlusIcon class="text-muted-foreground" />
                    Nuevo sistema
                    <span class="sr-only">alta crear alcance</span>
                </CommandItem>

                <CommandItem
                    v-for="opcion in temas"
                    :key="opcion.valor"
                    :value="`tema-${opcion.valor}`"
                    @select="tema(opcion.valor)"
                >
                    <component :is="opcion.icono" class="text-muted-foreground" />
                    {{ opcion.etiqueta }}
                    <span class="sr-only">apariencia modo color</span>
                </CommandItem>
            </CommandGroup>
        </CommandList>

        <div class="flex items-center justify-end gap-3 border-t px-3 py-2 text-[11px] text-muted-foreground">
            <span class="flex items-center gap-1">
                <CommandShortcut class="cifra">↑↓</CommandShortcut>
                navegar
            </span>
            <span class="flex items-center gap-1">
                <CommandShortcut class="cifra">↵</CommandShortcut>
                abrir
            </span>
            <span class="flex items-center gap-1">
                <CommandShortcut class="cifra">esc</CommandShortcut>
                cerrar
            </span>
        </div>
    </CommandDialog>
</template>
