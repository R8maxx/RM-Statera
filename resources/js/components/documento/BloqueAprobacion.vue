<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { FileXIcon, SendIcon, StampIcon } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

/**
 * Qué se puede hacer con el borrador ahora mismo: mandarlo a revisión o firmarlo.
 *
 * **Aprobar es lo que emite.** No hay un botón de «emitir» en ninguna parte: la
 * firma de la dirección es lo que numera la versión, congela el PDF y lo mueve al
 * prefijo con Object Lock. El motivo no es de flujo, es físico —la portada se
 * congela al generar—, y si la firma llegara después no podría salir impresa en
 * el documento que se entrega.
 *
 * **Vive dentro de la tarjeta del borrador**, bajo una regla, y no en una
 * tarjeta propia: lo que se firma es el borrador que hay justo encima, y el
 * estado —el badge— ya lo dice la cabecera de esa tarjeta.
 *
 * **Este bloque no autoriza nada.** `puedeAprobar` decide qué se pinta; quien
 * decide qué se permite es la ruta con su `can:`, y detrás el propio dominio.
 */
interface Version {
    id: number;
    estado: string;
    aprobadaPor: string | null;
    motivoRechazo: string | null;
    descargable: boolean;
}

const props = defineProps<{
    documentoId: number;
    version: Version;
    puedeAprobar: boolean;
    /** Cuántas entregas hay ya: decide si el motivo es obligatorio. */
    entregas: number;
    /** La etiqueta que llevará al emitirse: `v3`. */
    siguiente: string;
}>();

const revision = useForm({ motivo: '' });
const aprobacion = useForm({ nota: '' });
const rechazo = useForm({ motivo: '' });

const esBorrador = computed(() => props.version.estado === 'borrador' || props.version.estado === 'rechazado');
const enRevision = computed(() => props.version.estado === 'en_revision');
/** Firmada y todavía sin número: el trabajo de emisión está en marcha. */
const emitiendo = computed(() => enRevision.value && props.version.aprobadaPor !== null);

/*
 * El rechazo se abre a petición. Con los dos formularios a la vista, la tarjeta
 * tenía dos campos y dos botones que competían con la firma, que es lo único
 * que esta pantalla pide hacer (DESIGN.md §1, un solo elemento fuerte).
 */
const rechazando = ref(false);
const campoRechazo = ref<InstanceType<typeof Input> | null>(null);

async function abrirRechazo(): Promise<void> {
    rechazando.value = true;
    await nextTick();
    (campoRechazo.value?.$el as HTMLInputElement | undefined)?.focus();
}

const ruta = (accion: string) => `/documentos/${props.documentoId}/versiones/${props.version.id}/${accion}`;
</script>

<template>
    <div class="flex flex-col gap-4 border-t border-border pt-6">
        <!--
            El motivo del rechazo se enseña arriba y entero: quien retome el
            documento dentro de tres semanas necesita saber qué cambiar, y
            esconderlo en un histórico es lo mismo que no escribirlo.
        -->
        <p
            v-if="version.motivoRechazo && esBorrador"
            class="border-l-2 border-estado-no-aplica pl-3 text-sm text-secondary-foreground"
        >
            <span class="font-medium">Rechazado:</span> {{ version.motivoRechazo }}
        </p>

        <!-- Mandar a revisión: el final de escribir, no el principio de entregar. -->
        <form
            v-if="esBorrador && version.descargable"
            class="flex flex-col gap-4"
            @submit.prevent="revision.post(`/documentos/${documentoId}/revision`, { preserveScroll: true })"
        >
            <div class="flex flex-col gap-1">
                <h3 class="text-base font-semibold tracking-[-0.01em]">Enviar a revisión</h3>
                <p class="max-w-2xl text-sm text-pretty text-muted-foreground">
                    Cuando el borrador se haya leído entero. Mientras está en revisión se puede seguir
                    regenerando.
                </p>
            </div>
            <div class="flex max-w-xl flex-col gap-2">
                <Label for="motivo-revision">
                    Motivo de la versión
                    <span v-if="entregas > 0" class="text-primary" aria-hidden="true">*</span>
                </Label>
                <Input
                    id="motivo-revision"
                    v-model="revision.motivo"
                    placeholder="Se añade el control A.5.7 tras el análisis de riesgos"
                    :aria-invalid="revision.errors.motivo ? true : undefined"
                />
                <p class="text-xs text-muted-foreground">
                    «¿Por qué hay una {{ siguiente }}?» es la primera pregunta del auditor, y
                    contestarla dentro de seis meses no lo hace nadie.
                </p>
                <p v-if="revision.errors.motivo" class="text-sm text-destructive">
                    {{ revision.errors.motivo }}
                </p>
            </div>
            <Button type="submit" :disabled="revision.processing" class="self-start">
                <SendIcon class="size-4" />
                Enviar a revisión
            </Button>
        </form>

        <p v-else-if="esBorrador" class="text-sm text-muted-foreground">
            Genera el borrador antes de mandarlo a revisión: no se firma lo que nadie ha podido leer.
        </p>

        <p v-if="emitiendo" class="text-sm text-muted-foreground">
            Firmado por {{ version.aprobadaPor }}. Se está emitiendo como la
            <span class="cifra">{{ siguiente }}</span> con la firma en portada.
        </p>

        <template v-else-if="enRevision">
            <p v-if="!puedeAprobar" class="text-sm text-muted-foreground">
                A la espera de la firma de la dirección. Hace falta el permiso de aprobar documentos para
                firmarlo.
            </p>

            <template v-else>
                <form
                    class="flex flex-col gap-4"
                    @submit.prevent="aprobacion.post(ruta('aprobar'), { preserveScroll: true })"
                >
                    <div class="flex flex-col gap-1">
                        <h3 class="text-base font-semibold tracking-[-0.01em]">Firma de la dirección</h3>
                        <p class="max-w-2xl text-sm text-pretty text-muted-foreground">
                            Firmar es lo que entrega el documento: el PDF se regenera con la firma en
                            portada, pasa a ser la <span class="cifra">{{ siguiente }}</span> y ya no se
                            puede modificar ni regenerar.
                        </p>
                    </div>
                    <div class="flex max-w-xl flex-col gap-2">
                        <Label for="nota-aprobacion">Nota de aprobación</Label>
                        <Input
                            id="nota-aprobacion"
                            v-model="aprobacion.nota"
                            placeholder="Aprobada en el comité de seguridad del 3 de marzo"
                            aria-describedby="ayuda-nota-aprobacion"
                        />
                        <p id="ayuda-nota-aprobacion" class="text-xs text-muted-foreground">
                            Opcional. Sale impresa junto a la firma.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <!--
                            La variante `acento` de DESIGN.md, reservada a los
                            flujos de revisión y auditoría, y el único botón de
                            color de la pantalla mientras hay algo que firmar.
                            Rechazar va en tinte suave y apartado a la derecha,
                            para que no compitan; y sin rojo, porque que la
                            dirección tumbe una versión es una decisión
                            legítima, no un error.
                        -->
                        <Button type="submit" variant="acento" size="lg" :disabled="aprobacion.processing">
                            <StampIcon class="size-4" />
                            Aprobar y emitir la {{ siguiente }}
                        </Button>
                        <Button
                            v-if="!rechazando"
                            type="button"
                            variant="ghost"
                            class="ms-auto bg-estado-no-aplica-suave text-estado-no-aplica"
                            @click="abrirRechazo"
                        >
                            <FileXIcon class="size-4" />
                            Rechazar con motivo
                        </Button>
                    </div>
                </form>

                <form
                    v-if="rechazando"
                    class="flex flex-col gap-2 rounded-xl bg-superficie p-4"
                    @submit.prevent="rechazo.post(ruta('rechazar'), { preserveScroll: true })"
                >
                    <Label for="motivo-rechazo">
                        Motivo del rechazo
                        <span class="text-primary" aria-hidden="true">*</span>
                        <span class="sr-only">(obligatorio)</span>
                    </Label>
                    <Input
                        id="motivo-rechazo"
                        ref="campoRechazo"
                        v-model="rechazo.motivo"
                        placeholder="Falta el apartado de responsabilidades"
                        aria-required="true"
                        :aria-invalid="rechazo.errors.motivo ? true : undefined"
                    />
                    <p class="text-xs text-muted-foreground">
                        Quien lo redactó lo verá encima del borrador al volver a él.
                    </p>
                    <p v-if="rechazo.errors.motivo" class="text-sm text-destructive">
                        {{ rechazo.errors.motivo }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            type="submit"
                            variant="ghost"
                            :disabled="rechazo.processing"
                            class="bg-estado-no-aplica-suave text-estado-no-aplica"
                        >
                            <FileXIcon class="size-4" />
                            Rechazar
                        </Button>
                        <Button type="button" variant="ghost" @click="rechazando = false">Cancelar</Button>
                    </div>
                </form>
            </template>
        </template>
    </div>
</template>
