<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoSwitch from '@/components/formulario/CampoSwitch.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { computed, ref } from 'vue';

interface OpcionTipo extends Opcion {
    tono: string;
    icono: string;
    ambito: string;
}

interface Cuestion {
    id: number;
    codigo: string;
    tipo: string;
    titulo: string;
    descripcion: string | null;
    materia: string;
    es_climatica: boolean;
    responsable_id: number | null;
}

const props = defineProps<{
    cuestion: Cuestion | null;
    sugerencia: { codigo: string; tipo: string } | null;
    tipos: OpcionTipo[];
    materias: Opcion[];
    usuarios: Opcion[];
}>();

const edicion = props.cuestion !== null;

/*
 * Los desplegables van con `v-model` sobre un `ref`, que es como se usan en el
 * resto del producto: `CampoSelect` no tiene `valorInicial` —Reka pinta un botón
 * y el valor viaja en un campo oculto—, así que un `:valor-inicial` aquí se
 * quedaría en un atributo suelto y la edición abriría el desplegable vacío.
 */
const tipo = ref(props.cuestion?.tipo ?? props.sugerencia?.tipo ?? 'debilidad');
const materia = ref(props.cuestion?.materia ?? 'organizativo');
const esClimatica = ref(props.cuestion?.es_climatica ?? false);
const responsable = ref(
    props.cuestion?.responsable_id ? String(props.cuestion.responsable_id) : SIN_VALOR,
);

const responsables = conOpcionVacia(props.usuarios, 'Sin responsable');

/*
 * El ámbito se enseña y no se pregunta: lo deriva `TipoCuestion::ambito()` y
 * guardarlo aparte sería el mismo dato en dos columnas que pueden discrepar. Pero
 * tiene que **verse**, porque es la mitad del DAFO que el color no dice —el tono
 * codifica el signo— y quien elige «amenaza» tiene que saber que está diciendo
 * «esto viene de fuera».
 */
const ambito = computed(
    () => props.tipos.find((opcion) => opcion.valor === tipo.value)?.ambito ?? '',
);

const ayudaTipo = computed(() =>
    ambito.value
        ? `Fortalezas y debilidades son internas; oportunidades y amenazas, externas. Ésta es ${ambito.value.toLowerCase()}.`
        : 'Fortalezas y debilidades son internas; oportunidades y amenazas, externas.',
);
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar cuestión' : 'Nueva cuestión'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${cuestion?.codigo}` : 'Nueva cuestión del contexto'"
            descripcion="Una cuestión interna o externa de las que pide la cláusula 4.1. Se guarda en el borrador de la revisión en curso; si no hay ninguno, se abre uno."
            :action="edicion ? `/contexto/cuestiones/${cuestion?.id}` : '/contexto/cuestiones'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Registrar cuestión'"
            :url-cancelar="edicion ? `/contexto/cuestiones/${cuestion?.id}` : '/contexto/cuestiones'"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="Qué es"
                ayuda="El tipo decide en qué cuadrante del DAFO cae, y de él se derivan el ámbito y el signo."
            >
                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="cuestion?.codigo ?? sugerencia?.codigo ?? ''"
                    :error="errors.codigo"
                    requerido
                    autofocus
                    ayuda="Único dentro de la organización. Se propone el siguiente de la serie."
                />

                <CampoSelect
                    v-model="tipo"
                    nombre="tipo"
                    etiqueta="Tipo"
                    :opciones="tipos"
                    :error="errors.tipo"
                    requerido
                    :ayuda="ayudaTipo"
                />

                <CampoTexto
                    nombre="titulo"
                    etiqueta="Título"
                    :valor-inicial="cuestion?.titulo ?? undefined"
                    :error="errors.titulo"
                    requerido
                    ayuda="Una línea que se entienda leída sola en un acta."
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="4"
                    :valor-inicial="cuestion?.descripcion ?? undefined"
                    :error="errors.descripcion"
                    ayuda="Por qué es pertinente para la seguridad de la información. Es lo que el auditor lee para decidir si el análisis lo hizo alguien o salió de una plantilla."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Cómo se clasifica">
                <CampoSelect
                    v-model="materia"
                    nombre="materia"
                    etiqueta="Materia"
                    :opciones="materias"
                    :error="errors.materia"
                    requerido
                    ayuda="De qué va. Contesta a «¿qué se nos viene por el lado legal?», que es otra pregunta distinta de «¿qué amenazas tenemos?»."
                />

                <CampoSwitch
                    v-model="esClimatica"
                    nombre="es_climatica"
                    etiqueta="Relacionada con el cambio climático"
                    ayuda="La enmienda 1:2024 obliga a determinar si el cambio climático es pertinente. Marcarlo aquí es lo que permite enseñar cuáles son, en vez de sólo afirmarlo."
                />

                <CampoSelect
                    v-model="responsable"
                    nombre="responsable_id"
                    etiqueta="Responsable"
                    :opciones="responsables"
                    :error="errors.responsable_id"
                    ayuda="Quién sigue esta cuestión. No es obligatorio: muchas no tienen dueño, y forzarlo llena el campo de nombres que no significan nada."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
