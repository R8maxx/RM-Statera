<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoSeleccionMultiple, { type OpcionTipada } from '@/components/formulario/CampoSeleccionMultiple.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { computed, ref } from 'vue';

interface Prevista {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    canal: string;
    responsable_id: number | null;
    periodicidad_meses: number | null;
    computa_desde: string | null;
    destinatarios_otros: string | null;
    partes_interesadas: string[];
}

/**
 * Alta y edición de una línea del plan de comunicación. Cláusula 7.4.
 *
 * Las cinco preguntas de la norma, en el orden en que se piensan: qué, a quién,
 * quién, cómo y cuándo. **«Cuando proceda» es una respuesta**: lo que no tiene
 * cadencia no entra en el calendario, y no hace falta inventarle una.
 */
const props = defineProps<{
    prevista: Prevista | null;
    sugerencia: { codigo: string; computa_desde: string } | null;
    canales: Opcion[];
    cadencias: Opcion[];
    partesInteresadas: OpcionTipada[];
    responsables: Opcion[];
}>();

const edicion = props.prevista !== null;

const partesElegidas = ref<string[]>(props.prevista?.partes_interesadas ?? []);

const cadencia = ref<string>(
    props.prevista?.periodicidad_meses ? String(props.prevista.periodicidad_meses) : edicion ? SIN_VALOR : '3',
);

// Una cadencia que no está en la lista corta se ofrece igual, para no perderla.
const opcionesCadencia = computed(() => {
    const lista = [...props.cadencias];
    const actual = props.prevista?.periodicidad_meses;

    if (actual && !lista.some((opcion) => opcion.valor === String(actual))) {
        lista.push({ valor: String(actual), etiqueta: `Cada ${actual} meses` });
    }

    return conOpcionVacia(lista, 'Cuando proceda');
});

const periodica = computed(() => cadencia.value !== SIN_VALOR);
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar comunicación prevista' : 'Nueva comunicación prevista'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${prevista?.codigo}` : 'Nueva comunicación prevista'"
            descripcion="Qué se comunica, a quién, quién, cómo y cuándo. La cláusula 7.4 pide decidirlo."
            :action="edicion ? `/plan-comunicacion/${prevista?.id}` : '/plan-comunicacion'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Añadir al plan'"
            :url-cancelar="edicion ? `/plan-comunicacion/${prevista?.id}` : '/plan-comunicacion'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué se comunica">
                <FilaCampos>
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="prevista?.codigo ?? sugerencia?.codigo ?? ''"
                        :error="errors.codigo"
                        requerido
                        autofocus
                        ayuda="Único dentro de la organización. Se propone el siguiente."
                    />

                    <CampoSelect
                        nombre="canal"
                        etiqueta="Cómo"
                        :opciones="canales"
                        :valor-inicial="prevista?.canal"
                        :error="errors.canal"
                        requerido
                    />
                </FilaCampos>

                <CampoTexto
                    nombre="titulo"
                    etiqueta="Qué se comunica"
                    :valor-inicial="prevista?.titulo ?? ''"
                    :error="errors.titulo"
                    requerido
                    ayuda="En una línea: «informe trimestral de seguridad a la dirección»."
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="3"
                    :valor-inicial="prevista?.descripcion ?? undefined"
                    :error="errors.descripcion"
                    ayuda="Qué contenido lleva y por qué hace falta."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="A quién y quién">
                <CampoSeleccionMultiple
                    v-model="partesElegidas"
                    nombre="partes_interesadas"
                    etiqueta="Partes interesadas"
                    :opciones="partesInteresadas"
                    :error="errors.partes_interesadas"
                    vacio="No hay partes interesadas vigentes. Se registran en el contexto (cláusula 4.2)."
                    ayuda="Sólo las vigentes."
                />

                <CampoTextarea
                    nombre="destinatarios_otros"
                    etiqueta="Otros destinatarios"
                    :filas="2"
                    :valor-inicial="prevista?.destinatarios_otros ?? undefined"
                    :error="errors.destinatarios_otros"
                    ayuda="Cuando no es una parte interesada registrada: «todo el personal de la sede»."
                />

                <CampoSelect
                    nombre="responsable_id"
                    etiqueta="Quién lo comunica"
                    :opciones="conOpcionVacia(responsables, 'Sin responsable')"
                    :valor-inicial="prevista?.responsable_id ? String(prevista.responsable_id) : SIN_VALOR"
                    :error="errors.responsable_id"
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Cuándo">
                <FilaCampos>
                    <CampoSelect
                        nombre="periodicidad_meses"
                        etiqueta="Cadencia"
                        :opciones="opcionesCadencia"
                        :valor-inicial="cadencia"
                        :error="errors.periodicidad_meses"
                        ayuda="Con cadencia entra en el calendario y avisa cuando toca."
                        @update:model-value="(valor?: string) => (cadencia = valor ?? SIN_VALOR)"
                    />

                    <CampoTexto
                        v-if="periodica"
                        nombre="computa_desde"
                        etiqueta="Empieza a contar"
                        tipo="date"
                        :valor-inicial="prevista?.computa_desde ?? sugerencia?.computa_desde ?? ''"
                        :error="errors.computa_desde"
                        requerido
                        ayuda="La primera vez toca una cadencia después de esta fecha."
                    />
                </FilaCampos>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
