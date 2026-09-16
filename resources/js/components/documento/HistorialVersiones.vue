<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import EstadoVacio from '@/components/EstadoVacio.vue';
import HuellaRevelada from '@/components/documento/HuellaRevelada.vue';
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
    /** Quién firmó y cuándo. Es lo que el auditor busca en el historial. */
    aprobadaPor?: string | null;
    aprobadaEn?: string | null;
    /** `aprobado` mientras sea la vigente; `obsoleto` en cuanto la sustituyan. */
    estado?: string;
    estadoEtiqueta?: string;
    estadoTono?: string;
    estadoIcono?: string;
}

const props = defineProps<{ versiones: Version[]; documentoId: number }>();

const copiada = ref<number | null>(null);

/*
 * ── Cuál es la que se acaba de emitir ──────────────────────────────────────
 *
 * Emitir recarga la página, así que el componente no recuerda qué había antes.
 * El módulo sí, mientras la aplicación viva, y es el mismo recurso que usa el
 * calendario para saber de qué lado viene el mes.
 *
 * Quien llega al historial escribiendo la URL no ve escribirse ninguna huella, y
 * es correcto: no acaba de emitir nada. El gesto celebra un acto, no una visita.
 */
const yaVistas = new Set<number>();
const primeraVisita = yaVistas.size === 0;

const recienEmitida = props.versiones.find((version) => !yaVistas.has(version.id))?.id ?? null;

for (const version of props.versiones) {
    yaVistas.add(version.id);
}

/* En la primera carga no hay nada «reciente»: está todo el historial. */
const aRevelar = primeraVisita ? null : recienEmitida;

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

    <!--
        Una versión emitida entra por arriba y empuja a las anteriores. Esto es
        el registro de lo que se ha ENTREGADO, y que la fila nueva se vea llegar
        a él es la diferencia entre «ha pasado algo» y «ahí hay una fila más».
        No se anima al cargar la página: sólo cuando la lista cambia.
    -->
    <TransitionGroup v-else tag="ul" name="version" class="relative divide-y divide-border">
        <li v-for="version in versiones" :key="version.id" class="flex flex-col gap-2 py-4">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="cifra text-base font-semibold">{{ version.etiqueta }}</span>

                <!--
                    La vigente se distingue de las jubiladas: en una lista de
                    seis entregas, «cuál es la que está en vigor» es la primera
                    pregunta y la fecha no la contesta sola.
                -->
                <CeldaBadge
                    v-if="version.estado"
                    :valor="{
                        valor: version.estado,
                        etiqueta: version.estadoEtiqueta ?? '',
                        tono: version.estadoTono,
                        icono: version.estadoIcono,
                    }"
                />

                <span class="text-sm text-muted-foreground">{{ fecha(version.emitida) }}</span>

                <!--
                    Quién firmó, no quién pulsó «Generar». Desde el § 4.5 son dos
                    personas distintas y la que importa aquí es la que aprobó.
                -->
                <span v-if="version.aprobadaPor" class="text-sm text-muted-foreground">
                    · Aprobada por {{ version.aprobadaPor }}
                </span>
                <span v-else-if="version.quien" class="text-sm text-muted-foreground">
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
                <HuellaRevelada :huella="version.huella" :revelar="version.id === aRevelar" />
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
    </TransitionGroup>
</template>
