<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { computed, ref } from 'vue';

interface Documento {
    id: number;
    codigo: string;
    titulo: string;
    tipo: string;
    clasificacion: string;
    sistema_id: number | null;
    responsable_id: number | null;
    notas: string | null;
}

/** Las opciones de sistema y de tipo llevan su marco para poder avisar antes de enviar. */
interface OpcionConMarco extends Opcion {
    marco: string | null;
}

const props = defineProps<{
    documento: Documento | null;
    sistemas: OpcionConMarco[];
    tipos: OpcionConMarco[];
    clasificaciones: Opcion[];
    responsables: Opcion[];
}>();

const edicion = props.documento !== null;

const responsables = conOpcionVacia(props.responsables, 'Sin responsable');

const tipo = ref(props.documento?.tipo ?? props.tipos[0]?.valor ?? 'soa_iso');
const sistema = ref(props.documento?.sistema_id ? String(props.documento.sistema_id) : '');
const clasificacion = ref(props.documento?.clasificacion ?? 'uso_interno');
const responsable = ref(
    props.documento?.responsable_id ? String(props.documento.responsable_id) : SIN_VALOR,
);

/**
 * El aviso de marco incompatible, en el momento de elegir.
 *
 * Una SoA de un sistema declarado bajo el ENS no es un documento raro, es un
 * documento imposible: sus controles no existen en ese marco. El `FormRequest`
 * lo rechaza igual; esto sólo evita el viaje de ida y vuelta.
 */
const avisoMarco = computed<string | null>(() => {
    const elegido = props.sistemas.find((s) => s.valor === sistema.value);
    const esperado = props.tipos.find((t) => t.valor === tipo.value)?.marco ?? null;

    if (!elegido || !esperado || elegido.marco === esperado) {
        return null;
    }

    return `Ese sistema está declarado bajo otro marco. Elige uno de ${esperado} o cambia el tipo de documento.`;
});
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar ${documento!.codigo}` : 'Nuevo documento'">
        <FormularioRecurso
            :titulo="edicion ? 'Editar documento' : 'Nuevo documento'"
            descripcion="El documento no se redacta: se genera a partir de lo que ya está registrado. Aquí sólo se decide de qué sistema es, cómo se llama y quién responde de él."
            :action="edicion ? `/documentos/${documento!.id}` : '/documentos'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar cambios' : 'Crear documento'"
            :url-cancelar="edicion ? `/documentos/${documento!.id}` : '/documentos'"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="Qué documento es"
                ayuda="La Declaración de Aplicabilidad de ISO y la del ENS son dos consultas distintas sobre las mismas implantaciones. Cada una sólo puede emitirse para un sistema de su marco."
                plegable
            >
                <CampoSelect
                    v-model="tipo"
                    nombre="tipo"
                    etiqueta="Tipo de documento"
                    :opciones="tipos"
                    :error="errors.tipo"
                    requerido
                />

                <CampoSelect
                    v-model="sistema"
                    nombre="sistema_id"
                    etiqueta="Sistema"
                    :opciones="sistemas"
                    :error="errors.sistema_id ?? avisoMarco ?? undefined"
                    requerido
                    ayuda="De él salen el alcance declarado, la categoría y el conjunto de requisitos exigibles."
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Identificación"
                ayuda="El código va impreso en la cabecera de cada página y es por el que el auditor cita el documento. Una etiqueta impresa dura años: conviene que sea estable."
                plegable
            >
                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="documento?.codigo"
                    :error="errors.codigo"
                    placeholder="SOA-SGSI-01"
                    requerido
                    autofocus
                />

                <CampoTexto
                    nombre="titulo"
                    etiqueta="Título"
                    :valor-inicial="documento?.titulo ?? 'Declaración de Aplicabilidad'"
                    :error="errors.titulo"
                    requerido
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Clasificación y responsable"
                ayuda="La clasificación se estampa en el pie de cada página, que es lo que pide mp.info.2. Un documento sin marca acaba reenviado a quien no debe."
                plegable
            >
                <CampoSelect
                    v-model="clasificacion"
                    nombre="clasificacion"
                    etiqueta="Clasificación"
                    :opciones="clasificaciones"
                    :error="errors.clasificacion"
                    requerido
                />

                <CampoSelect
                    v-model="responsable"
                    nombre="responsable_id"
                    etiqueta="Responsable"
                    :opciones="responsables"
                    :error="errors.responsable_id"
                />

                <CampoTextarea
                    nombre="notas"
                    etiqueta="Notas"
                    :valor-inicial="documento?.notas ?? ''"
                    :error="errors.notas"
                    :filas="3"
                    ayuda="Para uso interno. No salen en el PDF."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
