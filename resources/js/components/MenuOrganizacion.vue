<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Link } from '@inertiajs/vue3';
import { BuildingIcon, ShieldCheckIcon } from '@lucide/vue';

/**
 * El desplegable de la organización activa, al pie del sidebar.
 *
 * Es un componente y no un trozo del layout porque lo abren dos mandos —la fila
 * del sidebar desplegado y el cuadrado del riel— y cada uno lo quiere salir por
 * un lado distinto.
 */
withDefaults(
    defineProps<{
        organizacion: { nombre: string; logo: string | null } | null;
        /** Si la sesión puede abrir la ficha del tenant. */
        gestionar: boolean;
        side?: 'top' | 'right';
    }>(),
    { side: 'top' },
);
</script>

<template>
    <DropdownMenuContent :side="side" :align="side === 'top' ? 'start' : 'end'" :side-offset="side === 'right' ? 14 : 6" class="w-60">
        <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">Organización activa</DropdownMenuLabel>
        <DropdownMenuItem v-if="organizacion" disabled>
            <!--
                El logo del cliente donde estaba el escudo. Statera se queda
                arriba del panel: esto es co-branding y no marca blanca, que
                sigue fuera de alcance. `DESIGN.md` §2 pide que no se compongan
                en la misma pieza, y aquí los separa el alto del sidebar entero.
            -->
            <img v-if="organizacion.logo" :src="organizacion.logo" alt="" class="h-5 w-auto max-w-[5rem] object-contain" />
            <ShieldCheckIcon v-else class="size-4 text-primary" />
            {{ organizacion.nombre }}
        </DropdownMenuItem>
        <DropdownMenuItem v-else disabled>Sin contexto de organización</DropdownMenuItem>

        <!--
            La ficha del tenant no está en `lib/navegacion.ts` —ese fichero es el
            mapa de MÓDULOS y esto no lo es—, así que su puerta es este
            desplegable, que es donde ya se mira para preguntarse de qué
            organización hablamos.
        -->
        <template v-if="organizacion && gestionar">
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link href="/organizacion">
                    <BuildingIcon class="size-4" />
                    La ficha de la organización
                </Link>
            </DropdownMenuItem>
        </template>
    </DropdownMenuContent>
</template>
