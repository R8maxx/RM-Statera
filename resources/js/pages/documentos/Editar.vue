<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import VistaImpresion from '@/components/documento/VistaImpresion.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePoll } from '@inertiajs/vue3';
import { FileTextIcon } from '@lucide/vue';
import { computed, defineAsyncComponent, onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import { toast } from 'vue-sonner';

/**
 * El documento entero, a página completa.
 *
 * Pantalla propia y no una pestaña de la ficha: la ficha lleva un `usePoll` cada
 * tres segundos mientras se genera un borrador, y un editor largo conviviendo
 * con recargas parciales es pedir un conflicto de estado sucio.
 *
 * El editor se carga con `defineAsyncComponent`, así que ProseMirror y las
 * tablas van en su propio chunk: quien nunca edita un documento no los paga.
 */
const EditorCuerpo = defineAsyncComponent(() => import('@/components/documento/EditorCuerpo.vue'));

const props = defineProps<{
    documento: { id: number; codigo: string; titulo: string; tipoEtiqueta: string };
    cuerpo: Record<string, unknown>;
    editadoEn: string | null;
    actualizadoEn: string | null;
    geometria: {
        ancho: number;
        alto: number;
        margenSuperior: number;
        margenInferior: number;
        margenLateral: number;
    };
    margenes: {
        organizacion: string;
        codigo: string;
        titulo: string;
        clasificacion: string;
        version: string;
        fecha: string;
    };
    borrador: {
        id: number;
        etiqueta: string;
        estado: string;
        enCurso: boolean;
        visible: boolean;
        error: string | null;
    } | null;
}>();

/* `shallowRef`: el árbol del documento es grande y no hay que hacerlo reactivo
   nodo a nodo — sólo interesa la referencia que se envía al guardar. */
const cuerpo = shallowRef<Record<string, unknown>>(props.cuerpo);
const sucio = ref(false);
const guardando = ref(false);

/**
 * El árbol ya normalizado por Tiptap, al cargar. Es lo que se enviará al
 * guardar, pero **no** es una edición: marcarlo sucio aquí hacía que «Ver el
 * PDF» guardase siempre, y ese guardado sella `editado_en`.
 */
function normalizado(arbol: Record<string, unknown>): void {
    cuerpo.value = arbol;
}

function cambio(nuevo: Record<string, unknown>): void {
    cuerpo.value = nuevo;
    sucio.value = true;
}

function guardar(alTerminar?: () => void): void {
    guardando.value = true;

    router.put(
        `/documentos/${props.documento.id}/cuerpo`,
        /*
         * El cuerpo es un árbol anidado y los tipos de Inertia describen un
         * `FormData` plano. Va como JSON en el cuerpo de la petición —el
         * `Content-Type` lo pone el cliente—, así que la aserción es sobre la
         * firma, no sobre lo que viaja.
         */
        { cuerpo: cuerpo.value, actualizado_en: props.actualizadoEn } as unknown as Record<string, never>,
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                sucio.value = false;
                alTerminar?.();
            },
            onFinish: () => {
                guardando.value = false;
            },
        },
    );
}

/* ---------------------------------------------------------------- Ver el PDF */

/**
 * «Ver el PDF» imprime **lo guardado**, no lo que hay en pantalla.
 *
 * Por eso guarda primero si hace falta: generar desde el editor sin guardar
 * enseñaría un PDF que no es el del documento, y es exactamente la clase de
 * discrepancia que hace desconfiar de la herramienta entera.
 */
const vistaAbierta = ref(false);
const esperandoPdf = ref(false);
const sello = ref(0);

const enCurso = computed(() => props.borrador?.enCurso ?? false);

function verElPdf(): void {
    if (sucio.value) {
        guardar(() => generar());

        return;
    }

    generar();
}

function generar(): void {
    esperandoPdf.value = true;

    router.post(
        `/documentos/${props.documento.id}/generar`,
        {},
        { preserveScroll: true, preserveState: true, only: ['borrador'] },
    );
}

/*
 * El mismo poll acotado de la ficha, y por los mismos motivos: el worker corre
 * en otro proceso, así que no puede mandar un flash, y `Inertia::defer()`
 * resuelve en UNA petición de seguimiento sin reintentar.
 *
 * `keepAlive: false` lo para con la pestaña en segundo plano.
 */
const { start, stop } = usePoll(3000, { only: ['borrador'] }, { keepAlive: false, autoStart: false });

/** Y se rinde a los dos minutos: un poll infinito contra una cola atascada es un bucle caliente. */
const LIMITE_MS = 120_000;
const rendido = ref(false);
let temporizador: number | null = null;

function pararTodo(): void {
    stop();

    if (temporizador !== null) {
        window.clearTimeout(temporizador);
        temporizador = null;
    }
}

watch(
    enCurso,
    (vivo, anterior) => {
        if (vivo) {
            rendido.value = false;
            start();
            temporizador = window.setTimeout(() => {
                rendido.value = true;
                esperandoPdf.value = false;
                stop();
                toast.warning('La generación está tardando. Compruébalo desde la ficha del documento.');
            }, LIMITE_MS);

            return;
        }

        pararTodo();

        if (anterior !== true || !esperandoPdf.value) {
            return;
        }

        esperandoPdf.value = false;

        if (props.borrador?.estado === 'generada') {
            sello.value += 1;
            vistaAbierta.value = true;

            return;
        }

        if (props.borrador?.estado === 'fallida') {
            toast.error('La generación falló.');
        }
    },
    { immediate: true },
);

onBeforeUnmount(pararTodo);

const etiquetaPdf = computed(() => {
    if (guardando.value && esperandoPdf.value) {
        return 'Guardando…';
    }

    return esperandoPdf.value || enCurso.value ? 'Generando…' : 'Ver el PDF';
});
</script>

<template>
    <AppLayout :titulo="`Editar ${documento.codigo}`">
        <CabeceraPagina
            :titulo="documento.titulo"
            :descripcion="`${documento.tipoEtiqueta} · ${documento.codigo}. Se edita el documento entero. Los apartados que salen del registro llevan su distintivo: cambiarlos queda declarado en el propio documento.`"
        >
            <template #acciones>
                <Button variant="outline" as-child>
                    <a :href="`/documentos/${documento.id}`">Volver a la ficha</a>
                </Button>
                <!--
                    Un solo botón de color lleno por vista (DESIGN.md §9): aquí
                    la acción que manda es guardar lo que se está escribiendo,
                    así que «Ver el PDF» va en secundaria.
                -->
                <Button
                    variant="outline"
                    :disabled="esperandoPdf || enCurso || guardando"
                    @click="verElPdf"
                >
                    <FileTextIcon class="size-4" />
                    {{ etiquetaPdf }}
                </Button>
                <Button :disabled="guardando" @click="guardar()">
                    {{ guardando ? 'Guardando…' : 'Guardar documento' }}
                </Button>
            </template>
        </CabeceraPagina>

        <p v-if="editadoEn" class="mb-4 text-sm text-muted-foreground">
            Este documento ya se ha editado a mano, y lo dice en su portada y en sus limitaciones.
        </p>

        <EditorCuerpo
            :cuerpo="cuerpo"
            :geometria="geometria"
            :margenes="margenes"
            @normalizado="normalizado"
            @cambio="cambio"
        />

        <p class="mt-3 text-sm text-muted-foreground">
            <span v-if="sucio">Hay cambios sin guardar.</span>
            <span v-else>Todo guardado.</span>
        </p>

        <VistaImpresion
            v-model:abierto="vistaAbierta"
            :documento-id="documento.id"
            :version-id="borrador?.visible ? borrador.id : null"
            :sello="sello"
        />
    </AppLayout>
</template>
