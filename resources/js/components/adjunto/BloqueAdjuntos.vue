<script setup lang="ts">
import EstadoVacio from '@/components/EstadoVacio.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/vue3';
import { PaperclipIcon } from '@lucide/vue';
import { ref } from 'vue';

/**
 * Los documentos que cuelgan de un registro.
 *
 * **No son evidencias**, y el texto de la tarjeta lo dice: una evidencia prueba
 * un requisito y lleva caducidad y responsable; esto es el título de un curso o
 * el contrato firmado. Pedir cuatro campos de caducidad para subir un PDF es
 * cómo se consigue que no se suba.
 *
 * Se sube con `useForm` y un `File`: Inertia detecta el fichero y cambia solo a
 * `FormData`, sin `forceFormData` ni `axios` —que ya no es dependencia—.
 */
export interface Adjunto {
    id: number;
    titulo: string;
    nota: string | null;
    nombre_fichero: string;
    mime: string;
    tamano: number;
    subidoPor: string | null;
    fecha: string | null;
    url: string;
}

const props = defineProps<{
    adjuntos: Adjunto[];
    /** Base de las rutas: `/personas/12/adjuntos`. */
    base: string;
    puedeGestionar: boolean;
    vacio: string;
}>();

const subiendo = ref(false);

const form = useForm<{ fichero: File | null; titulo: string; nota: string }>({
    fichero: null,
    titulo: '',
    nota: '',
});

function abrir(): void {
    form.reset();
    form.clearErrors();
    subiendo.value = true;
}

/**
 * El título se propone con el nombre del fichero, sin la extensión.
 *
 * No se impone: quien sube «escaneo_0012.pdf» quiere escribir «Título de ESO»,
 * y quien sube «Certificado ISO 27001.pdf» no quiere escribir nada. Sólo se
 * rellena si está vacío, para no pisar lo que ya se haya tecleado.
 */
function alElegir(evento: Event): void {
    const fichero = (evento.target as HTMLInputElement).files?.[0] ?? null;

    form.fichero = fichero;

    if (fichero !== null && form.titulo.trim() === '') {
        form.titulo = fichero.name.replace(/\.[^.]+$/, '');
    }
}

function subir(): void {
    form.post(props.base, {
        preserveScroll: true,
        onSuccess: () => {
            subiendo.value = false;
            form.reset();
        },
    });
}

function borrar(id: number): void {
    router.delete(`${props.base}/${id}`, { preserveScroll: true });
}

/** Legible sin decimales de más: un adjunto de 3 MB no necesita tres cifras. */
function peso(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kb = bytes / 1024;

    return kb < 1024 ? `${Math.round(kb)} kB` : `${(kb / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <div class="space-y-3">
        <EstadoVacio v-if="adjuntos.length === 0" :icono="PaperclipIcon" titulo="Sin documentos" :descripcion="vacio" />

        <ul v-else class="divide-y divide-border">
            <li
                v-for="adjunto in adjuntos"
                :key="adjunto.id"
                class="flex flex-wrap items-start justify-between gap-3 py-2 text-sm"
            >
                <div class="min-w-0">
                    <a
                        :href="`${adjunto.url}/descargar`"
                        class="font-medium underline-offset-4 hover:underline"
                    >{{ adjunto.titulo }}</a>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        <span class="cifra">{{ adjunto.nombre_fichero }}</span>
                        · {{ peso(adjunto.tamano) }}
                        <template v-if="adjunto.fecha"> · {{ adjunto.fecha }}</template>
                        <template v-if="adjunto.subidoPor"> · {{ adjunto.subidoPor }}</template>
                    </p>
                    <p v-if="adjunto.nota" class="mt-1">{{ adjunto.nota }}</p>
                </div>

                <Button v-if="puedeGestionar" variant="ghost" size="sm" @click="borrar(adjunto.id)">
                    Borrar
                </Button>
            </li>
        </ul>

        <Button v-if="puedeGestionar" variant="outline" size="sm" @click="abrir">Subir un documento</Button>

        <Dialog v-model:open="subiendo">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Subir un documento</DialogTitle>
                    <DialogDescription>
                        Documentación de este registro: un título, un contrato firmado, una hoja de
                        firmas escaneada. Si lo que subes prueba el cumplimiento de una medida, lo
                        que va es una evidencia, que lleva caducidad y responsable.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="adjunto-fichero">Documento</Label>
                        <Input id="adjunto-fichero" type="file" @change="alElegir" />
                        <p v-if="form.errors.fichero" class="text-sm text-destructive">
                            {{ form.errors.fichero }}
                        </p>
                        <p class="text-xs text-muted-foreground">Hasta 50 MB. Cualquier formato.</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="adjunto-titulo">Título</Label>
                        <Input id="adjunto-titulo" v-model="form.titulo" />
                        <p v-if="form.errors.titulo" class="text-sm text-destructive">
                            {{ form.errors.titulo }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Con qué se va a buscar. Se propone el nombre del fichero.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="adjunto-nota">Nota</Label>
                        <Textarea id="adjunto-nota" v-model="form.nota" :rows="2" />
                        <p v-if="form.errors.nota" class="text-sm text-destructive">
                            {{ form.errors.nota }}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="subiendo = false">Cancelar</Button>
                    <Button :disabled="form.processing" @click="subir">
                        {{ form.processing ? 'Subiendo…' : 'Subir' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
