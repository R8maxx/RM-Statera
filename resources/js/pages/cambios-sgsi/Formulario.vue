<script setup lang="ts">
import FilaCampos from '@/components/formulario/FilaCampos.vue';
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

interface Cambio {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    ambito: string;
    origen: string;
    proposito: string | null;
    consecuencias: string | null;
    integridad: string | null;
    recursos: string | null;
    estado: string;
    responsable_id: number | null;
    fecha_propuesta: string;
    fecha_prevista: string | null;
}

interface Sugerencia {
    codigo: string;
    origen: string;
    fecha_propuesta: string;
}

/**
 * Alta y edición de un cambio del SGSI. Cláusula 6.3.
 *
 * Las cuatro preguntas de un cambio planificado —para qué, qué arrastra, cómo se
 * mantiene el sistema en pie y con qué— son opcionales al escribir: se rellenan
 * antes de pedir la firma, no el día que se apunta.
 */
const props = defineProps<{
    cambio: Cambio | null;
    sugerencia: Sugerencia | null;
    ambitos: Opcion[];
    origenes: Opcion[];
    responsables: Opcion[];
}>();

const edicion = props.cambio !== null;

// Un cambio ya aprobado no puede quedarse sin plazo por una edición.
const comprometido = computed(() => ['aprobado', 'implantado', 'revisado'].includes(props.cambio?.estado ?? ''));
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar cambio' : 'Nuevo cambio del SGSI'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${cambio?.codigo}` : 'Nuevo cambio del SGSI'"
            descripcion="Un cambio del propio sistema de gestión. La cláusula 6.3 pide que se haga de forma planificada."
            :action="edicion ? `/cambios-sgsi/${cambio?.id}` : '/cambios-sgsi'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Proponer cambio'"
            :url-cancelar="edicion ? `/cambios-sgsi/${cambio?.id}` : '/cambios-sgsi'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué cambia">
                <FilaCampos>
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="cambio?.codigo ?? sugerencia?.codigo ?? ''"
                        :error="errors.codigo"
                        requerido
                        autofocus
                        ayuda="Único dentro de la organización. Se propone el siguiente del año."
                    />

                    <CampoSelect
                        nombre="ambito"
                        etiqueta="Ámbito"
                        :opciones="ambitos"
                        :valor-inicial="cambio?.ambito"
                        :error="errors.ambito"
                        requerido
                        ayuda="Qué parte del sistema de gestión. Un cambio técnico —un parche, un servidor— no va aquí."
                    />
                </FilaCampos>

                <CampoTexto
                    nombre="titulo"
                    etiqueta="Cambio"
                    :valor-inicial="cambio?.titulo ?? ''"
                    :error="errors.titulo"
                    requerido
                    ayuda="En una línea: «ampliar el alcance del SGSI a la sede de Valencia»."
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="3"
                    :valor-inicial="cambio?.descripcion ?? undefined"
                    :error="errors.descripcion"
                />

                <FilaCampos>
                    <CampoSelect
                        nombre="origen"
                        etiqueta="Origen"
                        :opciones="origenes"
                        :valor-inicial="cambio?.origen ?? sugerencia?.origen ?? 'propio'"
                        :error="errors.origen"
                        requerido
                        ayuda="De dónde sale la necesidad del cambio."
                    />

                    <CampoTexto
                        nombre="fecha_propuesta"
                        etiqueta="Fecha"
                        tipo="date"
                        :valor-inicial="cambio?.fecha_propuesta ?? sugerencia?.fecha_propuesta ?? ''"
                        :error="errors.fecha_propuesta"
                        requerido
                        ayuda="Cuándo se propuso."
                    />
                </FilaCampos>
            </SeccionFormulario>

            <SeccionFormulario titulo="Cómo se planifica">
                <CampoTextarea
                    nombre="proposito"
                    etiqueta="Propósito"
                    :filas="3"
                    :valor-inicial="cambio?.proposito ?? undefined"
                    :error="errors.proposito"
                    ayuda="Para qué se cambia. Es contra lo que se revisará al final si sirvió."
                />

                <CampoTextarea
                    nombre="consecuencias"
                    etiqueta="Consecuencias"
                    :filas="3"
                    :valor-inicial="cambio?.consecuencias ?? undefined"
                    :error="errors.consecuencias"
                    ayuda="A qué afecta: documentos, roles, controles, riesgos que aparecen o cambian."
                />

                <CampoTextarea
                    nombre="integridad"
                    etiqueta="Integridad del SGSI"
                    :filas="3"
                    :valor-inicial="cambio?.integridad ?? undefined"
                    :error="errors.integridad"
                    ayuda="Cómo sigue funcionando el sistema de gestión mientras dura el cambio."
                />

                <CampoTextarea
                    nombre="recursos"
                    etiqueta="Recursos"
                    :filas="2"
                    :valor-inicial="cambio?.recursos ?? undefined"
                    :error="errors.recursos"
                    ayuda="Personas, tiempo o presupuesto que hacen falta."
                />

                <FilaCampos>
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :valor-inicial="cambio?.responsable_id ? String(cambio.responsable_id) : undefined"
                        :error="errors.responsable_id"
                        ayuda="Quién lo lleva a cabo."
                    />

                    <CampoTexto
                        nombre="fecha_prevista"
                        etiqueta="Fecha prevista"
                        tipo="date"
                        :valor-inicial="cambio?.fecha_prevista ?? undefined"
                        :error="errors.fecha_prevista"
                        :requerido="comprometido"
                        ayuda="Opcional para proponerlo, obligatoria para aprobarlo."
                    />
                </FilaCampos>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
