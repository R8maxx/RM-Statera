<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { PencilIcon } from '@lucide/vue';

defineProps<{
    tipos: {
        valor: string;
        etiqueta: string;
        corta: string;
        secciones: number;
        personalizadas: number;
        documentos: number;
    }[];
}>();
</script>

<template>
    <AppLayout titulo="Plantillas de documento">
        <CabeceraPagina
            titulo="Plantillas de documento"
            descripcion="Los textos con los que arrancan los documentos de la organización: la introducción, la metodología y las notas de cada tabla. Lo que no se toque sale con el texto que trae Statera."
        />

        <div class="grid gap-4 md:grid-cols-2">
            <Card v-for="tipo in tipos" :key="tipo.valor">
                <CardHeader>
                    <CardTitle>{{ tipo.etiqueta }}</CardTitle>
                    <CardDescription>
                        {{ tipo.personalizadas }} de {{ tipo.secciones }} textos personalizados ·
                        {{ tipo.documentos }}
                        {{ tipo.documentos === 1 ? 'documento creado' : 'documentos creados' }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button as-child variant="outline">
                        <Link :href="`/plantillas-documento/${tipo.valor}`">
                            <PencilIcon class="size-4" />
                            Editar los textos
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
