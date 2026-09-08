<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ titulo: string; descripcion?: string }>();
</script>

<template>
    <Head :title="titulo" />

    <div class="flex min-h-screen flex-col items-center justify-center gap-6 bg-muted/40 px-4 py-12">
        <header class="text-center">
            <p class="text-3xl font-semibold tracking-tight">Statera</p>
            <p class="mt-1 text-xs text-muted-foreground">un producto de RM Technology</p>
        </header>

        <main class="w-full max-w-sm rounded-lg border bg-card p-6 shadow-sm">
            <h1 class="text-lg font-semibold tracking-tight">{{ titulo }}</h1>
            <p v-if="descripcion" class="mt-1 text-sm text-muted-foreground">{{ descripcion }}</p>

            <div class="mt-6">
                <slot />
            </div>
        </main>

        <p class="max-w-sm text-center text-xs text-muted-foreground">
            Gestor de cumplimiento ISO/IEC 27001:2022 y ENS (RD 311/2022).
        </p>
    </div>
</template>
