<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Sistema {
    id: number;
    codigo: string;
    nombre: string;
    marco_id: number;
    descripcion: string | null;
    estado: string;
    alcance_declarado: string | null;
    exclusiones_justificadas: string | null;
}

const props = defineProps<{
    sistema: Sistema | null;
    marcos: Opcion[];
    estados: Opcion[];
}>();

const edicion = props.sistema !== null;

/* Los selects de Reka no son <select>, así que su valor sí necesita estado. */
const marco = ref(props.sistema ? String(props.sistema.marco_id) : undefined);
const estado = ref(props.sistema?.estado ?? 'borrador');
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar ${sistema!.codigo}` : 'Nuevo sistema'">
        <FormularioRecurso
            :titulo="edicion ? 'Editar sistema' : 'Nuevo sistema'"
            descripcion="La categoría ENS no se elige aquí: se deriva de la valoración de las cinco dimensiones."
            :action="edicion ? `/sistemas/${sistema!.id}` : '/sistemas'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar cambios' : 'Crear sistema'"
            url-cancelar="/sistemas"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="Identificación"
                ayuda="El código es el que aparecerá en la declaración de aplicabilidad y en las evidencias, así que conviene que no cambie."
                plegable
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="sistema?.codigo"
                        :error="errors.codigo"
                        ayuda="Único dentro de la organización."
                        requerido
                        autofocus
                    />

                    <CampoSelect
                        v-model="estado"
                        nombre="estado"
                        etiqueta="Estado"
                        :opciones="estados"
                        :error="errors.estado"
                        requerido
                    />
                </div>

                <CampoTexto
                    nombre="nombre"
                    etiqueta="Nombre"
                    :valor-inicial="sistema?.nombre"
                    :error="errors.nombre"
                    requerido
                />

                <CampoSelect
                    v-model="marco"
                    nombre="marco_id"
                    etiqueta="Marco"
                    :opciones="marcos"
                    :error="errors.marco_id"
                    placeholder="Selecciona el marco normativo"
                    requerido
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :valor-inicial="sistema?.descripcion ?? ''"
                    :error="errors.descripcion"
                    :filas="3"
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Alcance"
                ayuda="Los dos campos que un auditor lee antes que ningún otro. Excluir algo sin decir por qué es el motivo de rechazo más habitual."
                plegable
            >
                <CampoTextarea
                    nombre="alcance_declarado"
                    etiqueta="Alcance declarado"
                    :valor-inicial="sistema?.alcance_declarado ?? ''"
                    :error="errors.alcance_declarado"
                    ayuda="Qué queda dentro del sistema: sedes, procesos, servicios y activos."
                />

                <CampoTextarea
                    nombre="exclusiones_justificadas"
                    etiqueta="Exclusiones justificadas"
                    :valor-inicial="sistema?.exclusiones_justificadas ?? ''"
                    :error="errors.exclusiones_justificadas"
                    ayuda="Qué queda fuera y con qué motivo."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
