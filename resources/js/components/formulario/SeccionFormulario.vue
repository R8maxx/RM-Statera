<script setup lang="ts">
/**
 * Una sección de un formulario, con su explicación al lado.
 *
 * En cumplimiento, la mitad de los campos no se entienden por su etiqueta: nadie
 * sabe qué espera «exclusiones justificadas» hasta que alguien le dice que es lo
 * primero que un auditor rechaza cuando está vacío. Esa explicación vive aquí, a
 * la izquierda y permanente, en vez de en un texto de ayuda de once píxeles
 * debajo de cada campo.
 *
 * Por debajo de `lg` se apila: la explicación arriba y los campos debajo.
 */
defineProps<{ titulo: string; ayuda?: string }>();
</script>

<template>
    <section class="grid gap-x-8 gap-y-4 border-t pt-6 first:border-t-0 first:pt-0 lg:grid-cols-[15rem_1fr]">
        <div class="lg:pt-0.5">
            <h2 class="text-sm font-medium">{{ titulo }}</h2>
            <p v-if="ayuda" class="mt-1.5 text-sm text-muted-foreground">{{ ayuda }}</p>
        </div>

        <div class="grid content-start gap-5">
            <slot />
        </div>
    </section>
</template>
