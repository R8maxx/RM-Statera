<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El catálogo de puestos: § 4.8 y la caracterización de `mp.per.1`.
 *
 * **Sin tira de indicadores y sin un solo rojo.** Nada de aquí va mal de verdad:
 * un puesto sin caracterizar es la distancia que queda —`mp.per.1` está en
 * `no_aplica` en categoría básica— y una vacante es una decisión, no un
 * incumplimiento. Las dos cifras van en una línea con su enlace al filtro, que
 * es lo que `TiraIndicadores` hace cuando no hay nada abierto.
 */
defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    total: number;
    sinCaracterizar: number;
    vacantes: number;
}>();
</script>

<template>
    <AppLayout ancho="completo" :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <Card v-if="total > 0" size="sm">
            <CardContent class="flex flex-wrap items-baseline gap-x-6 gap-y-2 text-sm">
                <p class="text-muted-foreground">
                    <Cifra class="font-semibold text-foreground" :valor="total" />
                    {{ total === 1 ? 'puesto registrado' : 'puestos registrados' }}
                </p>
                <!-- Sólo lo que no está a cero, como en `TiraIndicadores`: un
                     «0 sin ocupar» enseña a no leer la línea. -->
                <p v-if="sinCaracterizar > 0" class="text-muted-foreground">
                    <Cifra class="font-semibold text-foreground" :valor="sinCaracterizar" />
                    sin decir qué competencia piden
                    <span class="cifra">(mp.per.1)</span>
                </p>
                <p v-if="vacantes > 0" class="text-muted-foreground">
                    <Cifra class="font-semibold text-foreground" :valor="vacantes" />
                    sin ocupar
                </p>
            </CardContent>
        </Card>

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
