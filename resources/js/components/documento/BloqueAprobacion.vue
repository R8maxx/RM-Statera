<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { StampIcon, FileXIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Quién firma el documento, y qué se puede hacer con él ahora mismo.
 *
 * **Aprobar es lo que emite.** No hay un botón de «emitir» en ninguna parte: la
 * firma de la dirección es lo que numera la versión, congela el PDF y lo mueve al
 * prefijo con Object Lock. El motivo no es de flujo, es físico —la portada se
 * congela al generar—, y si la firma llegara después no podría salir impresa en
 * el documento que se entrega.
 *
 * **Este bloque no autoriza nada.** `puedeAprobar` decide qué se pinta; quien
 * decide qué se permite es la ruta con su `can:`, y detrás el propio dominio.
 */
interface Version {
    id: number;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    aprobadaPor: string | null;
    aprobadaEn: string | null;
    notaAprobacion: string | null;
    motivoRechazo: string | null;
    proximaRevision: string | null;
    descargable: boolean;
}

const props = defineProps<{
    documentoId: number;
    version: Version;
    puedeAprobar: boolean;
    /** Cuántas entregas hay ya: decide si el motivo es obligatorio. */
    entregas: number;
}>();

const revision = useForm({ motivo: '' });
const aprobacion = useForm({ nota: '' });
const rechazo = useForm({ motivo: '' });

const esBorrador = computed(() => props.version.estado === 'borrador' || props.version.estado === 'rechazado');
const enRevision = computed(() => props.version.estado === 'en_revision');

const ruta = (accion: string) => `/documentos/${props.documentoId}/versiones/${props.version.id}/${accion}`;
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Aprobación</CardTitle>
            <CardDescription>
                La firma de la dirección es lo que entrega el documento: numera la versión, congela
                el PDF y lo deja fuera del alcance de cualquier cambio.
            </CardDescription>
        </CardHeader>

        <CardContent class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <CeldaBadge
                    :valor="{
                        valor: version.estado,
                        etiqueta: version.estadoEtiqueta,
                        tono: version.estadoTono,
                        icono: version.estadoIcono,
                    }"
                />
                <span v-if="version.aprobadaPor" class="text-sm text-muted-foreground">
                    Aprobada por {{ version.aprobadaPor }} el {{ version.aprobadaEn }}
                </span>
            </div>

            <p v-if="version.notaAprobacion" class="text-sm text-muted-foreground">
                {{ version.notaAprobacion }}
            </p>

            <!--
                El motivo del rechazo se enseña arriba y entero: quien retome el
                documento dentro de tres semanas necesita saber qué cambiar, y
                esconderlo en un histórico es lo mismo que no escribirlo.
            -->
            <p v-if="version.motivoRechazo" class="text-sm text-estado-en-progreso">
                Rechazada: {{ version.motivoRechazo }}
            </p>

            <p v-if="version.proximaRevision" class="text-sm text-muted-foreground">
                Próxima revisión: <span class="cifra">{{ version.proximaRevision }}</span>
            </p>

            <!-- Mandar a revisión: el final de escribir, no el principio de entregar. -->
            <form
                v-if="esBorrador && version.descargable"
                class="flex flex-col gap-2 border-t border-border pt-4"
                @submit.prevent="revision.post(`/documentos/${documentoId}/revision`, { preserveScroll: true })"
            >
                <Label for="motivo-revision">
                    Motivo de la versión
                    <span v-if="entregas > 0" class="text-primary" aria-hidden="true">*</span>
                </Label>
                <Input
                    id="motivo-revision"
                    v-model="revision.motivo"
                    placeholder="Se añade el control A.5.7 tras el análisis de riesgos"
                />
                <p class="text-sm text-muted-foreground">
                    «¿Por qué hay una v{{ entregas + 1 }}?» es la primera pregunta del auditor, y
                    contestarla dentro de seis meses no lo hace nadie.
                </p>
                <p v-if="revision.errors.motivo" class="text-sm text-destructive">
                    {{ revision.errors.motivo }}
                </p>
                <Button type="submit" :disabled="revision.processing" class="self-start">
                    Enviar a revisión
                </Button>
            </form>

            <p v-else-if="esBorrador" class="text-sm text-muted-foreground">
                Genera el borrador antes de mandarlo a revisión: no se firma lo que nadie ha podido leer.
            </p>

            <template v-if="enRevision">
                <p v-if="!puedeAprobar" class="text-sm text-muted-foreground">
                    A la espera de aprobación. Hace falta el permiso de aprobar documentos para firmarla.
                </p>

                <div v-else class="flex flex-col gap-4 border-t border-border pt-4">
                    <form
                        class="flex flex-col gap-2"
                        @submit.prevent="aprobacion.post(ruta('aprobar'), { preserveScroll: true })"
                    >
                        <Label for="nota-aprobacion">Nota de aprobación</Label>
                        <Input
                            id="nota-aprobacion"
                            v-model="aprobacion.nota"
                            placeholder="Aprobada en el comité de seguridad del 3 de marzo"
                        />
                        <p class="text-sm text-muted-foreground">
                            Al aprobar se regenera el PDF con la firma en portada y se entrega. A
                            partir de ahí no se puede modificar ni regenerar.
                        </p>
                        <!--
                            La variante `acento` de DESIGN.md, reservada a los
                            flujos de revisión y auditoría. Aquí es el único
                            botón de color de la tarjeta: rechazar va en tinte
                            suave, como un badge, para que no compitan.
                        -->
                        <Button
                            type="submit"
                            variant="acento"
                            :disabled="aprobacion.processing"
                            class="self-start"
                        >
                            <StampIcon class="size-4" />
                            Aprobar y entregar
                        </Button>
                    </form>

                    <form
                        class="flex flex-col gap-2"
                        @submit.prevent="rechazo.post(ruta('rechazar'), { preserveScroll: true })"
                    >
                        <Label for="motivo-rechazo">
                            Motivo del rechazo
                            <span class="text-primary" aria-hidden="true">*</span>
                        </Label>
                        <Input
                            id="motivo-rechazo"
                            v-model="rechazo.motivo"
                            placeholder="Falta el apartado de responsabilidades"
                        />
                        <p v-if="rechazo.errors.motivo" class="text-sm text-destructive">
                            {{ rechazo.errors.motivo }}
                        </p>
                        <Button
                            type="submit"
                            variant="ghost"
                            :disabled="rechazo.processing"
                            class="self-start bg-estado-no-aplica-suave text-estado-no-aplica"
                        >
                            <FileXIcon class="size-4" />
                            Rechazar
                        </Button>
                    </form>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
