<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { conOpcionVacia, type Opcion } from '@/lib/formularios';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Proponible {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    baseLegal: string | null;
    marco: string | null;
    cadencia: string;
}

/**
 * Lo que el catálogo propone a esta organización y nadie ha asumido.
 *
 * **Propone, no obliga.** La lista ya viene filtrada por marco, por las banderas
 * del ENS y por la categoría derivada de los sistemas, así que aquí no sale nada
 * que no le toque; lo que falta es que alguien lo acepte, con la fecha desde la
 * que corre el reloj — que es lo único que la herramienta no puede saber.
 *
 * **La base legal va impresa y no escondida en una ayuda**: es lo que separa esta
 * lista de una de buenas intenciones, y es lo primero que se comprueba.
 *
 * Los tres controles son los de la casa —`CampoTexto` y `CampoSelect`— y no
 * `<input>`/`<select>` en crudo, que es como nacieron. Aquello se saltaba lo que
 * `DESIGN.md` § 9 fija para un campo —`label`, `aria-describedby`, `aria-invalid`,
 * el anillo de foco de marca— y, sobre todo, **no pintaba ni un error**:
 * `asumir()` valida que la fecha no sea futura y que los dos ids existan, así que
 * con una fecha de mañana se pulsaba «Asumir» y no pasaba nada visible.
 */
defineProps<{
    obligaciones: Proponible[];
    sistemas: Opcion[];
    responsables: Opcion[];
}>();

const abierta = ref<number | null>(null);

const formulario = useForm({
    computa_desde: new Date().toISOString().slice(0, 10),
    sistema_id: '',
    responsable_id: '',
    periodicidad_meses: '' as string | number,
});

/*
 * Cada fila arranca limpia. El formulario es uno solo —abrir dos a la vez sería
 * un cuestionario, no una lista— y sin esto lo tecleado en la anterior seguía
 * puesto al abrir la siguiente, sin que nada lo dijera.
 */
watch(abierta, (id) => {
    if (id !== null) {
        formulario.reset();
        formulario.clearErrors();
    }
});

function asumir(id: number): void {
    formulario.post(`/obligaciones/asumir/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            abierta.value = null;
        },
    });
}
</script>

<template>
    <Card class="mb-6">
        <CardHeader>
            <CardTitle>Del catálogo</CardTitle>
            <CardDescription>
                Lo periódico que los marcos de esta organización exigen. Asumir una copia su
                cadencia; lo único que hay que decir es desde cuándo se cuenta.
            </CardDescription>
        </CardHeader>

        <CardContent>
            <ul class="divide-y">
                <li v-for="obligacion in obligaciones" :key="obligacion.id" class="py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ obligacion.nombre }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                <span class="cifra">{{ obligacion.codigo }}</span>
                                · {{ obligacion.cadencia }}
                                <template v-if="obligacion.baseLegal"> · {{ obligacion.baseLegal }}</template>
                            </p>
                            <p v-if="obligacion.descripcion" class="mt-1 max-w-prose text-sm text-muted-foreground">
                                {{ obligacion.descripcion }}
                            </p>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            :aria-expanded="abierta === obligacion.id"
                            @click="abierta = abierta === obligacion.id ? null : obligacion.id"
                        >
                            Asumir
                        </Button>
                    </div>

                    <!--
                        Los tres datos que la herramienta no puede deducir. Aparecen
                        al pulsar y no siempre: siete formularios abiertos a la vez
                        convierten una lista en un cuestionario.
                    -->
                    <div v-if="abierta === obligacion.id" class="mt-3 grid gap-4 sm:grid-cols-4">
                        <CampoTexto
                            v-model="formulario.computa_desde"
                            nombre="computa_desde"
                            etiqueta="Desde cuándo se cuenta"
                            tipo="date"
                            :error="formulario.errors.computa_desde"
                            requerido
                        />

                        <CampoSelect
                            v-model="formulario.responsable_id"
                            nombre="responsable_id"
                            etiqueta="Responsable"
                            :opciones="conOpcionVacia(responsables, 'Sin asignar')"
                            :error="formulario.errors.responsable_id"
                        />

                        <CampoSelect
                            v-model="formulario.sistema_id"
                            nombre="sistema_id"
                            etiqueta="Sistema"
                            :opciones="conOpcionVacia(sistemas, 'La organización entera')"
                            :error="formulario.errors.sistema_id"
                        />

                        <!--
                            La cadencia del catálogo es la **sugerida**: quien asume
                            puede ser más estricto que el mínimo legal. En blanco se
                            copia la del catálogo, que es lo normal.
                        -->
                        <CampoTexto
                            v-model="formulario.periodicidad_meses"
                            nombre="periodicidad_meses"
                            etiqueta="Cadencia (meses)"
                            tipo="number"
                            :error="formulario.errors.periodicidad_meses"
                            :placeholder="String(obligacion.cadencia)"
                            ayuda="En blanco, la del catálogo."
                        />

                        <div class="sm:col-span-4">
                            <Button
                                variant="outline"
                                :disabled="formulario.processing"
                                @click="asumir(obligacion.id)"
                            >
                                Asumir «{{ obligacion.nombre }}»
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
