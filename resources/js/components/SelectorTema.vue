<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTema } from '@/composables/useTema';
import { MonitorIcon, MoonIcon, SunIcon } from '@lucide/vue';

/**
 * El selector de tema, extraído del `AppLayout`.
 *
 * Estaba solo dentro de la aplicación, así que en las pantallas de acceso no
 * había forma de cambiarlo: quien entra de noche con el sistema en claro se
 * come el fogonazo y no puede hacer nada. Ahora vive en los dos sitios.
 */
const { preferencia, esOscuro, fijar } = useTema();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon-sm" aria-label="Cambiar el tema">
                <SunIcon v-if="esOscuro" />
                <MoonIcon v-else />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuCheckboxItem :model-value="preferencia === 'claro'" @select="fijar('claro')">
                <SunIcon class="size-4" />
                Claro
            </DropdownMenuCheckboxItem>
            <DropdownMenuCheckboxItem :model-value="preferencia === 'oscuro'" @select="fijar('oscuro')">
                <MoonIcon class="size-4" />
                Oscuro
            </DropdownMenuCheckboxItem>
            <DropdownMenuCheckboxItem :model-value="preferencia === 'sistema'" @select="fijar('sistema')">
                <MonitorIcon class="size-4" />
                El del sistema
            </DropdownMenuCheckboxItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
