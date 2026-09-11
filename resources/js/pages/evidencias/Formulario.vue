<script setup lang="ts">
import CampoFichero from '@/components/formulario/CampoFichero.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { ref } from 'vue';

interface Evidencia {
    id: number;
    titulo: string;
    tipo: string;
    descripcion: string | null;
    esFichero: boolean;
    nombre_fichero: string | null;
    url_externa: string | null;
    fecha_obtencion: string;
    fecha_caducidad: string | null;
    periodicidad_renovacion: string | null;
    responsable_id: number | null;
}

const props = defineProps<{
    evidencia: Evidencia | null;
    tipos: Opcion[];
    periodicidades: Opcion[];
    responsables: Opcion[];
}>();

const edicion = props.evidencia !== null;

const responsables = conOpcionVacia(props.responsables, 'Sin responsable');
const periodicidades = conOpcionVacia(props.periodicidades, 'No se renueva');

const tipo = ref(props.evidencia?.tipo ?? 'captura');
const responsable = ref(
    props.evidencia?.responsable_id ? String(props.evidencia.responsable_id) : SIN_VALOR,
);
const periodicidad = ref(props.evidencia?.periodicidad_renovacion ?? SIN_VALOR);
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar ${evidencia!.titulo}` : 'Nueva evidencia'">
        <FormularioRecurso
            :titulo="edicion ? 'Editar evidencia' : 'Nueva evidencia'"
            descripcion="Se registra una vez y cuenta en todos los marcos donde aplique. Los requisitos que prueba se vinculan después, desde su ficha o desde la de cada requisito."
            :action="edicion ? `/evidencias/${evidencia!.id}` : '/evidencias'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar cambios' : 'Registrar evidencia'"
            :url-cancelar="edicion ? `/evidencias/${evidencia!.id}` : '/evidencias'"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="Identificación"
                ayuda="El título es lo que se lee en la Declaración de Aplicabilidad y en la lista de pruebas que se le entrega al auditor: conviene que diga qué demuestra, no dónde estaba el fichero."
                plegable
            >
                <CampoTexto
                    nombre="titulo"
                    etiqueta="Título"
                    :valor-inicial="evidencia?.titulo"
                    :error="errors.titulo"
                    requerido
                    autofocus
                />

                <CampoSelect
                    v-model="tipo"
                    nombre="tipo"
                    etiqueta="Tipo"
                    :opciones="tipos"
                    :error="errors.tipo"
                    requerido
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :valor-inicial="evidencia?.descripcion ?? ''"
                    :error="errors.descripcion"
                    :filas="3"
                    ayuda="Qué se ve en la prueba y en qué condiciones se obtuvo."
                />
            </SeccionFormulario>

            <SeccionFormulario
                v-if="!edicion"
                titulo="La prueba"
                ayuda="Hace falta uno de los dos, y sólo uno: sin ninguno no prueba nada, y con los dos no se sabe cuál es la prueba. Por eso ninguno de los dos lleva marca de obligatorio: lo obligatorio es el par."
                plegable
            >
                <CampoFichero
                    nombre="fichero"
                    etiqueta="Fichero"
                    :error="errors.fichero"
                    ayuda="Hasta 50 MB. Se guarda con su huella SHA-256, que es lo que permite demostrar que no ha cambiado desde que se obtuvo."
                />

                <CampoTexto
                    nombre="url_externa"
                    etiqueta="Enlace"
                    tipo="url"
                    :error="errors.url_externa"
                    placeholder="https://…"
                    ayuda="Para lo que vive fuera: el panel de un proveedor, un registro de un servicio en la nube."
                />
            </SeccionFormulario>

            <SeccionFormulario
                v-else
                titulo="La prueba"
                ayuda="El fichero de una evidencia no se reemplaza: si la prueba cambia, se registra otra. El almacén lleva Object Lock precisamente para que nadie pueda cambiar bajo un registro que ya se entregó."
                plegable
            >
                <p v-if="evidencia!.esFichero" class="cifra text-sm">{{ evidencia!.nombre_fichero }}</p>

                <CampoTexto
                    v-else
                    nombre="url_externa"
                    etiqueta="Enlace"
                    tipo="url"
                    :valor-inicial="evidencia!.url_externa ?? ''"
                    :error="errors.url_externa"
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Vigencia"
                ayuda="Una prueba de hace tres años no prueba lo de hoy. Si eliges una periodicidad y dejas la caducidad en blanco, la fecha se calcula sola."
                plegable
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoTexto
                        nombre="fecha_obtencion"
                        etiqueta="Fecha de obtención"
                        tipo="date"
                        :valor-inicial="evidencia?.fecha_obtencion"
                        :error="errors.fecha_obtencion"
                        requerido
                    />

                    <CampoTexto
                        nombre="fecha_caducidad"
                        etiqueta="Caduca el"
                        tipo="date"
                        :valor-inicial="evidencia?.fecha_caducidad ?? ''"
                        :error="errors.fecha_caducidad"
                    />
                </div>

                <CampoSelect
                    v-model="periodicidad"
                    nombre="periodicidad_renovacion"
                    etiqueta="Periodicidad de renovación"
                    :opciones="periodicidades"
                    :error="errors.periodicidad_renovacion"
                />

                <CampoSelect
                    v-model="responsable"
                    nombre="responsable_id"
                    etiqueta="Responsable de renovarla"
                    :opciones="responsables"
                    :error="errors.responsable_id"
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
