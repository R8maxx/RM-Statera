<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useForm } from '@inertiajs/vue3';
import { CheckIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Quién ha leído la versión vigente, y a quién le falta.
 *
 * Es el acuse que piden la cláusula 7.3 de ISO y `org.2` del ENS: la diferencia
 * entre «la política está publicada» y «la política se conoce», que es la única
 * de las dos que la norma exige.
 *
 * **La cifra va con su denominador**, como todas las del producto: «4 de 12» y no
 * «4». Y los pendientes se enseñan por su nombre, porque una lista de nombres es
 * lo único con lo que alguien puede hacer algo hoy.
 */
const props = defineProps<{
    documentoId: number;
    versionId: number;
    acuse: {
        total: number;
        acusados: number;
        pendientes: string[];
        lectores: { nombre: string; fecha: string }[];
        yaAcusado: boolean;
    };
}>();

const confirmar = useForm({});

const completo = computed(() => props.acuse.pendientes.length === 0);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Acuse de lectura</CardTitle>
            <CardDescription>
                De la versión vigente, y sólo de ella: quien acusó recibo de la v3 no ha leído la v4.
            </CardDescription>
        </CardHeader>

        <CardContent class="flex flex-col gap-4">
            <p class="text-sm">
                <span class="cifra">{{ acuse.acusados }}</span>
                de
                <span class="cifra">{{ acuse.total }}</span>
                {{ acuse.total === 1 ? 'persona ha' : 'personas han' }} confirmado la lectura.
            </p>

            <!--
                Un estado vacío de verdad, no una lista de cero: si no falta
                nadie, se dice en una línea y no se ocupa media tarjeta.
            -->
            <p v-if="completo" class="text-sm text-estado-implantado">
                No queda nadie por leerla.
            </p>

            <div v-else class="text-sm">
                <div class="text-muted-foreground">Pendientes</div>
                <div>{{ acuse.pendientes.join(', ') }}</div>
            </div>

            <ul v-if="acuse.lectores.length > 0" class="flex flex-col gap-1 text-sm text-muted-foreground">
                <li v-for="lector in acuse.lectores" :key="lector.nombre">
                    {{ lector.nombre }} · {{ lector.fecha }}
                </li>
            </ul>

            <p v-if="acuse.yaAcusado" class="flex items-center gap-2 text-sm text-estado-implantado">
                <CheckIcon class="size-4" />
                Has confirmado que la has leído.
            </p>

            <Button
                v-else
                :disabled="confirmar.processing"
                class="self-start"
                @click="confirmar.post(
                    `/documentos/${documentoId}/versiones/${versionId}/acuse`,
                    { preserveScroll: true },
                )"
            >
                He leído esta versión
            </Button>
        </CardContent>
    </Card>
</template>
