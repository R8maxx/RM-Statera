<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Objetivo {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    recursos: string | null;
    esComprometido: boolean;
    responsable_id: number | null;
    fecha_objetivo: string | null;
}

const props = defineProps<{
    objetivo: Objetivo | null;
    sugerencia: { codigo: string } | null;
    responsables: Opcion[];
    indicadoresDisponibles: Opcion[];
}>();

const edicion = props.objetivo !== null;

const valor = computed(() => ({
    codigo: props.objetivo?.codigo ?? props.sugerencia?.codigo ?? '',
    titulo: props.objetivo?.titulo ?? '',
}));

/*
 * La fecha es opcional al escribir y obligatoria en cuanto el objetivo está
 * aprobado: un borrador se apunta el día que se tiene la idea, y un compromiso
 * tiene que decir para cuándo porque la 6.2 lo pide por escrito. El formulario
 * lo dice en la ayuda en vez de esperar al error.
 */
const plazoObligatorio = computed(() => props.objetivo?.esComprometido ?? false);
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar objetivo' : 'Nuevo objetivo'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${objetivo?.codigo}` : 'Nuevo objetivo de seguridad'"
            descripcion="La cláusula 6.2 pide un objetivo medible y, con él, qué se hará, con qué recursos, quién responde, para cuándo y cómo se evaluarán los resultados."
            :action="edicion ? `/objetivos/${objetivo?.id}` : '/objetivos'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Proponer objetivo'"
            :url-cancelar="edicion ? `/objetivos/${objetivo?.id}` : '/objetivos'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="A qué se compromete la organización">
                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="valor.codigo"
                    :error="errors.codigo"
                    requerido
                    autofocus
                    ayuda="Único dentro de la organización. Se propone el siguiente del año."
                />

                <CampoTexto
                    nombre="titulo"
                    etiqueta="Objetivo"
                    :valor-inicial="valor.titulo"
                    :error="errors.titulo"
                    requerido
                    ayuda="Enunciado en una línea, y medible: «reducir a 15 días la aplicación de parches críticos»."
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="3"
                    :valor-inicial="objetivo?.descripcion ?? undefined"
                    :error="errors.descripcion"
                    ayuda="Por qué este objetivo y con qué política o riesgo enlaza."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Cómo se va a conseguir">
                <CampoTextarea
                    nombre="recursos"
                    etiqueta="Recursos"
                    :filas="3"
                    :valor-inicial="objetivo?.recursos ?? undefined"
                    :error="errors.recursos"
                    ayuda="Qué hace falta y de dónde sale. No es el coste de las actuaciones: hay objetivos que se cumplen con horas de gente que ya está."
                />

                <CampoSelect
                    nombre="responsable_id"
                    etiqueta="Responsable"
                    :opciones="responsables"
                    :valor-inicial="objetivo?.responsable_id ? String(objetivo.responsable_id) : undefined"
                    :error="errors.responsable_id"
                    ayuda="Quién responde de conseguirlo."
                />

                <CampoTexto
                    nombre="fecha_objetivo"
                    etiqueta="Fecha objetivo"
                    tipo="date"
                    :valor-inicial="objetivo?.fecha_objetivo ?? undefined"
                    :error="errors.fecha_objetivo"
                    :requerido="plazoObligatorio"
                    :ayuda="
                        plazoObligatorio
                            ? 'Obligatoria: este objetivo ya está aprobado, y un compromiso sin plazo no es un compromiso.'
                            : 'Para cuándo. Se puede dejar vacía mientras el objetivo esté propuesto; aprobarlo exige ponerla.'
                    "
                />
            </SeccionFormulario>

            <SeccionFormulario
                v-if="!edicion"
                titulo="Cómo se evaluarán los resultados"
                plegable
            >
                <p class="text-sm text-muted-foreground">
                    Los indicadores que evalúan el objetivo se vinculan desde su ficha, y no se crean
                    aquí: un indicador tiene periodicidad, responsable y método propios, y se define
                    en
                    <a href="/indicadores" class="underline underline-offset-4">Indicadores</a>.
                    Hay {{ indicadoresDisponibles.length }} disponibles.
                </p>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
