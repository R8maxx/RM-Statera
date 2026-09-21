<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { computed } from 'vue';

/**
 * La cara de una cuenta, con las iniciales de respaldo.
 *
 * Existe para que las iniciales se calculen **una vez**: las necesitan el menú
 * de cuenta de la cabecera y la pantalla de «Mi cuenta», y estaban escritas en
 * el layout.
 *
 * El respaldo cubre dos casos y no uno: no hay foto, y **la hay y no carga**.
 * Lo segundo lo resuelve `AvatarImage` de Reka por su cuenta —conmuta al
 * `AvatarFallback` cuando la imagen falla—, que es justo lo que no hace un
 * `<img>` a secas: ahí lo que se ve es el icono de imagen rota del navegador.
 * Y pasa de verdad: la URL firmada del almacén caduca a los cinco minutos, así
 * que una pestaña abierta desde ayer puede encontrarse con una petición que ya
 * no vale hasta que la ruta le dé una firma nueva.
 *
 * El respaldo va en el teal de marca y no en el `bg-muted` que trae shadcn,
 * porque es el círculo que ya llevaba la cabecera y no hay motivo para
 * cambiarlo al añadir la foto.
 */
const props = withDefaults(
    defineProps<{
        nombre?: string | null;
        /** La URL de la foto, o nulo si no hay. La sirve `User::urlFoto()`. */
        foto?: string | null;
        tamano?: 'sm' | 'default' | 'lg';
        /** Para los tamaños que no están en la escala de shadcn, como el de la ficha. */
        clase?: string;
    }>(),
    { tamano: 'default' },
);

const iniciales = computed(() =>
    (props.nombre ?? '?')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((parte) => parte.charAt(0).toUpperCase())
        .join('') || '?',
);
</script>

<template>
    <Avatar :size="tamano" :class="clase">
        <AvatarImage v-if="foto" :src="foto" :alt="`Foto de ${nombre ?? 'la cuenta'}`" />
        <AvatarFallback class="bg-primary font-semibold text-primary-foreground">
            {{ iniciales }}
        </AvatarFallback>
    </Avatar>
</template>
