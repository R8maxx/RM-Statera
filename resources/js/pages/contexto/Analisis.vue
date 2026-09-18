<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ConmutadorContexto from '@/components/contexto/ConmutadorContexto.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';

/**
 * El historial de revisiones del contexto.
 *
 * **Es la razón entera de que el módulo lleve análisis versionados.** La cláusula
 * 9.3 pide «cambios de contexto» como entrada obligatoria de la revisión por la
 * dirección, y sin esta pantalla la respuesta sería releer dos DAFO enteros y
 * compararlos a ojo.
 *
 * Las altas y las bajas salen directamente de las filas —cada cuestión sabe qué
 * análisis la dio de alta—, no de comparar dos instantáneas. Por eso es exacto y
 * barato: una cuestión no «aparece» entre dos revisiones, la da de alta una
 * concreta y eso está escrito.
 */

interface Analisis {
    id: number;
    numero: number | null;
    etiqueta: string;
    fechaAnalisis: string;
    estado: { valor: string; etiqueta: string; tono: string; icono: string };
    climaPertinente: boolean | null;
    aprobadoPor: string | null;
    aprobadoEn: string | null;
    altas: number;
    bajas: number;
}

defineProps<{
    analisis: Analisis[];
    hayBorrador: boolean;
}>();
</script>

<template>
    <AppLayout titulo="Revisiones del contexto">
        <CabeceraPagina
            titulo="Revisiones del contexto"
            descripcion="Qué entró y qué salió en cada revisión. Es la entrada de «cambios de contexto» que pide la cláusula 9.3."
        >
            <template #acciones>
                <ConmutadorContexto vista="analisis" />
            </template>
        </CabeceraPagina>

        <p v-if="hayBorrador" class="mb-4 text-sm text-muted-foreground">
            Hay una revisión abierta sin firmar. Lo que se escriba en el DAFO y en las partes
            interesadas se anota en ella hasta que alguien la apruebe.
        </p>

        <EstadoVacio
            v-if="analisis.length === 0"
            titulo="Todavía no hay ninguna revisión"
            descripcion="La primera se abre sola al registrar la primera cuestión del DAFO."
            :accion="{ etiqueta: 'Ir al contexto', href: '/contexto' }"
        />

        <ol v-else class="space-y-3">
            <li v-for="fila in analisis" :key="fila.id">
                <Card>
                    <CardContent class="flex flex-wrap items-center justify-between gap-4 py-4">
                        <div class="min-w-0">
                            <Link
                                :href="`/contexto/analisis/${fila.id}`"
                                class="flex flex-wrap items-center gap-2 font-medium hover:underline"
                            >
                                {{ fila.etiqueta }}
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="tono(fila.estado.tono).badge"
                                >
                                    <IconoTipo :nombre="fila.estado.icono" />
                                    {{ fila.estado.etiqueta }}
                                </span>
                            </Link>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Analizado el {{ fila.fechaAnalisis }}<template v-if="fila.aprobadoPor">, aprobado por {{ fila.aprobadoPor }}</template>.
                                <template v-if="fila.climaPertinente === null">
                                    Sin contestar a la pregunta del cambio climático.
                                </template>
                            </p>
                        </div>

                        <!--
                            Las dos cifras que contesta esta pantalla. Sin tono: ni
                            dar de alta ni retirar es bueno o malo, es lo que pasó.
                        -->
                        <dl class="flex shrink-0 gap-6 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">Altas</dt>
                                <dd class="cifra">{{ fila.altas }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Bajas</dt>
                                <dd class="cifra">{{ fila.bajas }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </li>
        </ol>
    </AppLayout>
</template>
