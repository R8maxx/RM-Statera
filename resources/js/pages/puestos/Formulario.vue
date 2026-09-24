<script setup lang="ts">
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia } from '@/lib/formularios';
import { computed } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Puesto {
    id: number;
    codigo: string;
    titulo: string;
    reporta_a_id: number | null;
    mision: string | null;
    funciones: string | null;
    competencias: string | null;
}

const props = defineProps<{
    puesto: Puesto | null;
    sugerencia: { codigo: string } | null;
    superiores: Opcion[];
}>();

const edicion = props.puesto !== null;

const codigo = computed(() => props.puesto?.codigo ?? props.sugerencia?.codigo ?? '');

const opcionesSuperior = computed(() => conOpcionVacia(props.superiores, 'No depende de ninguno'));
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar puesto' : 'Nuevo puesto'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${puesto?.titulo}` : 'Nuevo puesto'"
            descripcion="El puesto de trabajo, no el rol ENS: los cinco del Anexo II se designan por sistema y desde la ficha de la persona."
            :action="edicion ? `/puestos/${puesto?.id}` : '/puestos'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Crear puesto'"
            :url-cancelar="edicion ? `/puestos/${puesto?.id}` : '/puestos'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué puesto es">
                <FilaCampos codigo>
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="codigo"
                        :error="errors.codigo"
                        requerido
                        autofocus
                        ayuda="Único dentro de la organización. Se propone el siguiente, pero si ya hay una nomenclatura propia, es la que vale."
                    />

                    <CampoTexto
                        nombre="titulo"
                        etiqueta="Puesto"
                        :valor-inicial="puesto?.titulo ?? undefined"
                        :error="errors.titulo"
                        requerido
                    />
                </FilaCampos>

                <CampoSelect
                    nombre="reporta_a_id"
                    etiqueta="Depende de"
                    :opciones="opcionesSuperior"
                    :valor-inicial="puesto?.reporta_a_id ? String(puesto.reporta_a_id) : undefined"
                    :error="errors.reporta_a_id"
                    ayuda="De aquí sale el organigrama. En blanco si es uno de los puestos de arriba del todo. No se puede cerrar un bucle: si el puesto elegido ya depende de éste, se rechaza."
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="La caracterización"
                ayuda="Es lo que mp.per.1 llama «caracterización del puesto de trabajo». En categoría básica no es exigible, pero es lo que contesta «¿quién puede ocupar esto?» cuando hay que cubrirlo."
            >
                <CampoTextarea
                    nombre="mision"
                    etiqueta="Misión"
                    :filas="2"
                    :valor-inicial="puesto?.mision ?? undefined"
                    :error="errors.mision"
                    ayuda="Para qué existe el puesto, en una o dos frases."
                />

                <CampoTextarea
                    nombre="funciones"
                    etiqueta="Funciones"
                    :filas="4"
                    :valor-inicial="puesto?.funciones ?? undefined"
                    :error="errors.funciones"
                    ayuda="Qué hace quien lo ocupa."
                />

                <CampoTextarea
                    nombre="competencias"
                    etiqueta="Competencias y requisitos"
                    :filas="4"
                    :valor-inicial="puesto?.competencias ?? undefined"
                    :error="errors.competencias"
                    ayuda="Titulación, experiencia o habilitación que se le pide. Es el campo que decide si el puesto figura como caracterizado."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
