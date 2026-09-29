<script setup lang="ts">
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CopiarHuella from '@/components/documento/CopiarHuella.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DownloadIcon, FileTextIcon, HistoryIcon } from '@lucide/vue';

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

/**
 * Lo que se ha ENTREGADO, una fila por versión.
 *
 * Tabla estática (`ui/table`) y no lista: son pocas filas fijas que se comparan
 * en vertical —qué versión, quién la firmó, cuándo, por qué—, que es para lo que
 * está una columna. La huella va abreviada, con la entera para el lector de
 * pantalla y en el botón de copiar; la de la vigente se enseña completa en la
 * ficha.
 */
defineProps<{ versiones: Version[]; documentoId: number }>();

const kb = (bytes: number | null): string => (bytes === null ? '—' : `${Math.round(bytes / 1024)} kB`);

/** La fecha en ISO, como toda fecha de una tabla (DESIGN.md §9), sin la hora. */
const fechaIso = (valor: string | null | undefined): string => (valor ? valor.slice(0, 10) : '—');

const abreviada = (huella: string): string => `${huella.slice(0, 8)}…${huella.slice(-8)}`;
</script>

<template>
    <Card v-if="versiones.length === 0">
        <CardContent>
            <EstadoVacio
                :icono="HistoryIcon"
                titulo="Sin versiones emitidas todavía"
                descripcion="La primera aparece aquí, con su huella SHA-256, cuando la dirección firme un borrador."
            />
        </CardContent>
    </Card>

    <Table v-else>
        <TableHeader>
            <TableRow class="hover:bg-transparent">
                <TableHead class="ps-6">Versión</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead class="text-right">Firmada</TableHead>
                <TableHead>Por</TableHead>
                <TableHead>Motivo</TableHead>
                <TableHead>SHA-256</TableHead>
                <TableHead class="text-right">Tamaño</TableHead>
                <TableHead class="pe-6"><span class="sr-only">Descargas</span></TableHead>
            </TableRow>
        </TableHeader>

        <!--
            Una versión emitida entra por arriba y empuja a las anteriores. Que
            la fila nueva se vea llegar es la diferencia entre «ha pasado algo»
            y «ahí hay una fila más». No se anima al cargar la página: sólo
            cuando la lista cambia.
        -->
        <TransitionGroup tag="tbody" name="version" data-slot="table-body" class="[&_tr:last-child]:border-0">
            <TableRow v-for="version in versiones" :key="version.id">
                <TableCell class="cifra ps-6 font-medium">{{ version.etiqueta }}</TableCell>
                <TableCell>
                    <!--
                        La vigente se distingue de las jubiladas: «cuál es la que
                        está en vigor» es la primera pregunta y la fecha no la
                        contesta sola.
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
                </TableCell>
                <TableCell class="cifra text-right text-[13px]">
                    {{ fechaIso(version.aprobadaEn ?? version.emitida) }}
                </TableCell>
                <!--
                    Quién firmó, no quién pulsó «Generar». Desde el § 4.5 son dos
                    personas distintas y la que importa aquí es la que aprobó.
                    Las versiones archivadas antes del flujo no tienen firmante,
                    y no se les fabrica uno.
                -->
                <TableCell>
                    <span v-if="version.aprobadaPor">{{ version.aprobadaPor }}</span>
                    <span v-else class="text-muted-foreground">Sin firma registrada</span>
                </TableCell>
                <TableCell class="max-w-80 whitespace-normal text-secondary-foreground">
                    {{ version.motivo ?? '—' }}
                </TableCell>
                <TableCell>
                    <div v-if="version.huella" class="flex items-center gap-1">
                        <span class="cifra text-[13px] text-secondary-foreground">
                            <span aria-hidden="true">{{ abreviada(version.huella) }}</span>
                            <span class="sr-only">{{ version.huella }}</span>
                        </span>
                        <CopiarHuella :huella="version.huella" :etiqueta="version.etiqueta" />
                    </div>
                    <span v-else class="text-muted-foreground">—</span>
                </TableCell>
                <TableCell class="cifra text-right text-[13px]">{{ kb(version.tamano) }}</TableCell>
                <TableCell class="pe-6">
                    <div class="flex justify-end gap-1">
                        <!--
                            El Word es copia de trabajo, no la entrega: el PDF/A
                            es el que lleva la huella. Por eso va detrás.
                        -->
                        <Button as-child variant="ghost" size="sm">
                            <a
                                :href="`/documentos/${documentoId}/versiones/${version.id}/word`"
                                :aria-label="`Word de la ${version.etiqueta}`"
                            >
                                <FileTextIcon class="size-4" />
                                Word
                            </a>
                        </Button>
                        <Button as-child variant="outline" size="sm">
                            <a
                                :href="`/documentos/${documentoId}/versiones/${version.id}/descargar`"
                                :aria-label="`Descargar el PDF de la ${version.etiqueta}`"
                            >
                                <DownloadIcon class="size-4" />
                                PDF
                            </a>
                        </Button>
                    </div>
                </TableCell>
            </TableRow>
        </TransitionGroup>
    </Table>
</template>
