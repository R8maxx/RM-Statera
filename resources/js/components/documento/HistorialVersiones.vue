<script setup lang="ts">
import { Button } from '@/components/ui/button';
import EstadoVacio from '@/components/EstadoVacio.vue';
import { formatoFechaHora } from '@/lib/celdas';
import { CheckIcon, CopyIcon, DownloadIcon, FileTextIcon, HistoryIcon } from '@lucide/vue';
import { ref } from 'vue';

export interface Version {
    id: number;
    numero: number | null;
    etiqueta: string;
    huella: string | null;
    tamano: number | null;
    motivo: string | null;
    quien: string | null;
    emitida: string | null;
}

const props = defineProps<{ versiones: Version[]; documentoId: number }>();

const copiada = ref<number | null>(null);

/**
 * La huella entera, no un prefijo: es lo que se contrasta con el fichero que se
 * le entrega al auditor, y media huella no contrasta nada.
 */
async function copiar(version: Version): Promise<void> {
    if (!version.huella) {
        return;
    }

    try {
        await navigator.clipboard.writeText(version.huella);
        copiada.value = version.id;
        window.setTimeout(() => (copiada.value = null), 2000);
    } catch {
        // Sin portapapeles —contexto no seguro, permiso denegado— la huella
        // sigue estando a la vista y se puede seleccionar a mano.
    }
}

const kb = (bytes: number | null): string => (bytes === null ? '—' : `${Math.round(bytes / 1024)} kB`);
const fecha = (valor: string | null): string =>
    valor ? formatoFechaHora.format(new Date(valor)) : '—';
</script>

<template>
    <EstadoVacio
        v-if="versiones.length === 0"
        :icono="HistoryIcon"
        titulo="Todavía no se ha entregado ninguna versión"
        descripcion="Genera un borrador, revísalo y emítelo. Sólo a partir de ahí queda registrado de forma inmutable."
    />

    <ul v-else class="divide-y divide-border">
        <li v-for="version in versiones" :key="version.id" class="flex flex-col gap-2 py-4">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="cifra text-base font-semibold">{{ version.etiqueta }}</span>
                <span class="text-sm text-muted-foreground">{{ fecha(version.emitida) }}</span>
                <span v-if="version.quien" class="text-sm text-muted-foreground">
                    · {{ version.quien }}
                </span>
                <span class="text-sm text-muted-foreground">· {{ kb(version.tamano) }}</span>

                <div class="ms-auto flex gap-2">
                    <!--
                        El Word es copia de trabajo, no la entrega: el PDF/A es
                        el que lleva la huella. Por eso va en `ghost`, detrás.
                    -->
                    <Button as-child variant="ghost" size="sm">
                        <a :href="`/documentos/${documentoId}/versiones/${version.id}/word`">
                            <FileTextIcon class="size-4" />
                            Word
                        </a>
                    </Button>

                    <Button as-child variant="outline" size="sm">
                        <a :href="`/documentos/${documentoId}/versiones/${version.id}/descargar`">
                            <DownloadIcon class="size-4" />
                            Descargar PDF
                        </a>
                    </Button>
                </div>
            </div>

            <p v-if="version.motivo" class="text-sm">{{ version.motivo }}</p>

            <div v-if="version.huella" class="flex items-start gap-2">
                <code class="cifra min-w-0 flex-1 break-all rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
                    {{ version.huella }}
                </code>
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-label="`Copiar la huella SHA-256 de ${version.etiqueta}`"
                    @click="copiar(version)"
                >
                    <CheckIcon v-if="copiada === version.id" class="size-4 text-estado-implantado" />
                    <CopyIcon v-else class="size-4" />
                </Button>
            </div>
        </li>
    </ul>
</template>
