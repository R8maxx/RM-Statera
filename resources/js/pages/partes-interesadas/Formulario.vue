<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { ref, watch } from 'vue';

interface OpcionTipo extends Opcion {
    ambitoSugerido: string | null;
}

interface Parte {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    ambito: string;
    descripcion: string | null;
    responsable_id: number | null;
}

const props = defineProps<{
    parte: Parte | null;
    sugerencia: { codigo: string } | null;
    tipos: OpcionTipo[];
    ambitos: Opcion[];
    naturalezas: Opcion[];
    usuarios: Opcion[];
}>();

const edicion = props.parte !== null;

const tipo = ref(props.parte?.tipo ?? 'cliente');
const ambito = ref(props.parte?.ambito ?? 'externo');
const responsable = ref(
    props.parte?.responsable_id ? String(props.parte.responsable_id) : SIN_VALOR,
);

const responsables = conOpcionVacia(props.usuarios, 'Sin asignar');

/*
 * El tipo **propone** el ámbito y no lo impone, que es la diferencia con el DAFO:
 * allí una fortaleza es interna por definición, y aquí un socio o un accionista
 * son lo que cada organización decida. Se rellena al cambiar el tipo para no
 * preguntar dos veces lo mismo en seis casos de ocho, y se puede cambiar a mano.
 *
 * `null` en `ambitoSugerido` es «no hay respuesta por defecto»: entonces no se
 * toca lo que haya puesto quien escribe.
 */
watch(tipo, (nuevo) => {
    const sugerido = props.tipos.find((opcion) => opcion.valor === nuevo)?.ambitoSugerido;

    if (sugerido) {
        ambito.value = sugerido;
    }
});
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar parte interesada' : 'Nueva parte interesada'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${parte?.codigo}` : 'Nueva parte interesada'"
            descripcion="Quién tiene algo que decir sobre la seguridad de la organización. Lo que os exige se escribe después, en su ficha."
            :action="edicion ? `/partes-interesadas/${parte?.id}` : '/partes-interesadas'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Registrar parte interesada'"
            :url-cancelar="edicion ? `/partes-interesadas/${parte?.id}` : '/partes-interesadas'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Quién es">
                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="parte?.codigo ?? sugerencia?.codigo ?? ''"
                    :error="errors.codigo"
                    requerido
                    autofocus
                    ayuda="Único dentro de la organización. Se propone el siguiente de la serie."
                />

                <CampoTexto
                    nombre="nombre"
                    etiqueta="Nombre"
                    :valor-inicial="parte?.nombre ?? undefined"
                    :error="errors.nombre"
                    requerido
                    ayuda="Cómo se la llama en las actas: «Agencia Tributaria», «clientes del sector público», «comité de dirección»."
                />

                <CampoSelect
                    v-model="tipo"
                    nombre="tipo"
                    etiqueta="Tipo"
                    :opciones="tipos"
                    :error="errors.tipo"
                    requerido
                    ayuda="«Sociedad y usuarios» es el público que no tiene contrato con la organización y al que le pasan cosas igual."
                />

                <CampoSelect
                    v-model="ambito"
                    nombre="ambito"
                    etiqueta="Ámbito"
                    :opciones="ambitos"
                    :error="errors.ambito"
                    requerido
                    ayuda="Se propone según el tipo y se puede cambiar: un socio o un accionista son internos o externos según cómo esté montada la organización."
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="3"
                    :valor-inicial="parte?.descripcion ?? undefined"
                    :error="errors.descripcion"
                    ayuda="Qué relación tiene con la organización y por qué le importa su seguridad."
                />

                <CampoSelect
                    v-model="responsable"
                    nombre="responsable_id"
                    etiqueta="Quién la atiende"
                    :opciones="responsables"
                    :error="errors.responsable_id"
                    ayuda="Quién habla con ellos. No es obligatorio."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
