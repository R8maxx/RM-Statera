<script setup lang="ts">
defineProps<{ mensaje?: string | string[] | null }>();
</script>

<template>
    <p v-if="mensaje" class="text-sm font-medium text-destructive">
        {{ Array.isArray(mensaje) ? mensaje.join(' ') : mensaje }}
    </p>
</template>
