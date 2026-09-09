<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import { Link } from '@inertiajs/vue3';
import { PaperclipIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed } from 'vue';

interface Vinculo {
    implantacionId: number;
    codigo: string;
    titulo: string;
    marco: string | null;
    sistema: string;
    estado: string;
    estadoEtiqueta: string;
    nota: string | null;
}

const props = defineProps<{
    evidencia: {
        id: number;
        titulo: string;
        tipo: string;
        tipoEtiqueta: string;
        descripcion: string | null;
        esFichero: boolean;
        nombre_fichero: string | null;
        mime: string | null;
        tamano: number | null;
        hash_sha256: string | null;
        url_externa: string | null;
        fecha_obtencion: string;
        fecha_caducidad: string | null;
        periodicidad_renovacion: string | null;
        haCaducado: boolean;
        responsable: string | null;
    };
    vinculos: Vinculo[];
    marcos: string[];
}>();

const { variantesEntrada } = useMovimientoReducido();

const fecha = (valor: string | null): string => (valor ? formatoFecha.format(new Date(valor)) : '—');

const tamano = computed(() => {
    if (props.evidencia.tamano === null) {
        return null;
    }

    const mb = props.evidencia.tamano / 1024 / 1024;

    return mb >= 1
        ? `${mb.toFixed(1).replace('.', ',')} MB`
        : `${Math.max(Math.round(props.evidencia.tamano / 1024), 1)} KB`;
});

const vigencia = computed(() => {
    if (props.evidencia.fecha_caducidad === null) {
        return { etiqueta: 'Sin caducidad', tono: 'no_iniciado' };
    }

    return props.evidencia.haCaducado
        ? { etiqueta: 'Caducada', tono: 'caducada' }
        : { etiqueta: 'Vigente', tono: 'implantado' };
});
</script>

<template>
    <AppLayout :titulo="evidencia.titulo">
        <CabeceraPagina :titulo="evidencia.titulo" :descripcion="evidencia.descripcion">
            <template #acciones>
                <Link :href="`/evidencias/${evidencia.id}/editar`">
                    <Button variant="outline">Editar</Button>
                </Link>
                <a v-if="evidencia.esFichero" :href="`/evidencias/${evidencia.id}/descargar`">
                    <Button>Descargar</Button>
                </a>
                <a v-else-if="evidencia.url_externa" :href="evidencia.url_externa" target="_blank" rel="noopener">
                    <Button>Abrir el enlace</Button>
                </a>
            </template>
        </CabeceraPagina>

        <motion.div
            :variants="variantesEntrada"
            initial="oculto"
            animate="visible"
            class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Qué prueba</CardTitle>
                    <CardDescription>
                        <template v-if="marcos.length > 1">
                            Esta evidencia cuenta en {{ marcos.length }} marcos a la vez. Registrarla una vez y que
                            valga en todos es exactamente el trabajo que la herramienta ahorra.
                        </template>
                        <template v-else>
                            Los requisitos que esta evidencia demuestra, de cualquier marco.
                        </template>
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <EstadoVacio
                        v-if="vinculos.length === 0"
                        :icono="PaperclipIcon"
                        titulo="Todavía no prueba nada"
                        descripcion="Una evidencia sin vincular no cuenta en ningún marco. Se vincula desde la ficha del requisito que demuestra."
                        :accion="{ etiqueta: 'Ir a implantaciones', href: '/implantaciones' }"
                    />

                    <ul v-else class="divide-y divide-border">
                        <li v-for="vinculo in vinculos" :key="vinculo.implantacionId" class="py-3 first:pt-0 last:pb-0">
                            <Link
                                :href="`/implantaciones/${vinculo.implantacionId}`"
                                class="group flex flex-wrap items-center gap-x-3 gap-y-1.5"
                            >
                                <span class="cifra text-sm font-medium group-hover:underline">{{ vinculo.codigo }}</span>
                                <CeldaBadge
                                    v-if="vinculo.marco"
                                    :valor="{ valor: vinculo.marco, etiqueta: vinculo.marco, tono: 'marco' }"
                                />
                                <CeldaBadge
                                    :valor="{
                                        valor: vinculo.estado,
                                        etiqueta: vinculo.estadoEtiqueta,
                                        tono: vinculo.estado,
                                    }"
                                />
                                <span class="cifra text-xs text-muted-foreground">{{ vinculo.sistema }}</span>
                            </Link>

                            <p class="mt-1 text-sm text-muted-foreground">{{ vinculo.titulo }}</p>
                            <p v-if="vinculo.nota" class="mt-1 text-sm">{{ vinculo.nota }}</p>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>La prueba</CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Tipo</span>
                            <span>{{ evidencia.tipoEtiqueta }}</span>
                        </div>

                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Obtenida</span>
                            <span>{{ fecha(evidencia.fecha_obtencion) }}</span>
                        </div>

                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Vigencia</span>
                            <CeldaBadge
                                :valor="{
                                    valor: evidencia.fecha_caducidad,
                                    etiqueta: vigencia.etiqueta,
                                    tono: vigencia.tono,
                                }"
                            />
                        </div>

                        <div v-if="evidencia.fecha_caducidad" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Caduca</span>
                            <span>{{ fecha(evidencia.fecha_caducidad) }}</span>
                        </div>

                        <div v-if="evidencia.responsable" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Responsable</span>
                            <span class="text-right">{{ evidencia.responsable }}</span>
                        </div>

                        <div v-if="tamano" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Tamaño</span>
                            <span>{{ tamano }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!--
                    La huella entera, no un prefijo: es lo que se contrasta con
                    el fichero que se le entrega al auditor, y para eso hay que
                    poder copiarla completa.
                -->
                <Card v-if="evidencia.hash_sha256">
                    <CardHeader>
                        <CardTitle>Integridad</CardTitle>
                        <CardDescription>
                            SHA-256 calculado al recibir el fichero. Contrastarlo demuestra que el fichero es el
                            que se obtuvo aquel día y no otro.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <p class="cifra text-xs break-all text-muted-foreground">{{ evidencia.hash_sha256 }}</p>
                        <p v-if="evidencia.nombre_fichero" class="mt-2 text-sm">{{ evidencia.nombre_fichero }}</p>
                    </CardContent>
                </Card>
            </div>
        </motion.div>
    </AppLayout>
</template>
