<script setup lang="ts">
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { conOpcionVacia, type Opcion } from '@/lib/formularios';
import CatalogoObligaciones from '@/components/obligacion/CatalogoObligaciones.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed } from 'vue';

interface OpcionNumerica {
    valor: number;
    etiqueta: string;
}

interface Compromiso {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    periodicidad_meses: number;
    computa_desde: string;
    sistema_id: number | null;
    responsable_id: number | null;
    notas: string | null;
}

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
 * Declarar un compromiso propio, o asumir uno del catálogo.
 *
 * **Dos caminos y no uno, y no son intercambiables.** Asumir del catálogo copia
 * el título y la cadencia sugerida y deja el rastro de qué fila lo originó —que
 * es lo que el importador mira para avisar de a cuántos afecta retirarla—.
 * Declarar uno propio es para lo que no exige ningún marco, y ahí todo lo escribe
 * quien lo declara.
 *
 * Por eso `obligacion_id` **no es un campo de este formulario**: dejar que se
 * eligiera aquí permitiría reetiquetar un compromiso como si saliera de otra
 * cosa.
 */
const props = defineProps<{
    compromiso: Compromiso | null;
    sugerencia: { codigo: string; computa_desde: string } | null;
    sinAsumir: Proponible[];
    sistemas: OpcionNumerica[];
    responsables: OpcionNumerica[];
}>();

const edicion = props.compromiso !== null;

const comoOpciones = (lista: OpcionNumerica[]): Opcion[] =>
    lista.map((item) => ({ valor: String(item.valor), etiqueta: item.etiqueta }));

const sistemas = computed(() => comoOpciones(props.sistemas));
const responsables = computed(() => comoOpciones(props.responsables));
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar obligación' : 'Nueva obligación'">
        <!--
            Lo del catálogo va antes del formulario y sólo en el alta: si lo que
            toca ya está propuesto, escribirlo a mano es trabajo de más y además
            pierde la traza de qué fila del catálogo lo exige.
        -->
        <CatalogoObligaciones
            v-if="!edicion && sinAsumir.length > 0"
            :obligaciones="sinAsumir"
            :sistemas="sistemas"
            :responsables="responsables"
        />

        <FormularioRecurso
            :titulo="edicion ? `Editar ${compromiso?.codigo}` : 'Obligación propia'"
            descripcion="Algo a lo que la organización se obliga por su cuenta, con su cadencia y desde cuándo corre el reloj."
            :action="edicion ? `/obligaciones/${compromiso?.id}` : '/obligaciones'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Declarar obligación'"
            :url-cancelar="edicion ? `/obligaciones/${compromiso?.id}` : '/obligaciones'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué hay que hacer">
                <FilaCampos codigo>
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="compromiso?.codigo ?? sugerencia?.codigo ?? ''"
                        :error="errors.codigo"
                        autofocus
                        ayuda="Se propone el siguiente del año. Se puede cambiar: una organización que ya llevaba esto en una hoja llega con su numeración."
                    />

                    <CampoTexto
                        nombre="titulo"
                        etiqueta="Obligación"
                        :valor-inicial="compromiso?.titulo ?? ''"
                        :error="errors.titulo"
                        requerido
                        ayuda="Qué hay que hacer, en una línea."
                    />
                </FilaCampos>

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="3"
                    :valor-inicial="compromiso?.descripcion ?? undefined"
                    :error="errors.descripcion"
                    ayuda="Qué se considera cumplirla y qué se guarda como prueba."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Cada cuánto">
                <FilaCampos>
                    <CampoTexto
                        nombre="periodicidad_meses"
                        etiqueta="Cadencia (meses)"
                        tipo="number"
                        :valor-inicial="compromiso?.periodicidad_meses ?? 12"
                        :error="errors.periodicidad_meses"
                        requerido
                        min="1"
                        max="120"
                        ayuda="En meses: 12 es anual y 24 bienal, que es la cadencia con la que se renueva la conformidad del ENS."
                    />

                    <CampoTexto
                        nombre="computa_desde"
                        etiqueta="Desde cuándo se cuenta"
                        tipo="date"
                        :valor-inicial="compromiso?.computa_desde ?? sugerencia?.computa_desde ?? ''"
                        :error="errors.computa_desde"
                        requerido
                        ayuda="La última vez que esto se hizo, si se hizo antes de tener Statera. Si no, el día en que se asume el compromiso."
                    />
                </FilaCampos>
            </SeccionFormulario>

            <SeccionFormulario titulo="De quién y de qué">
                <FilaCampos>
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="conOpcionVacia(responsables, 'Sin responsable')"
                        :valor-inicial="compromiso?.responsable_id ? String(compromiso.responsable_id) : undefined"
                        :error="errors.responsable_id"
                        ayuda="Quién responde de que esto se haga a tiempo."
                    />

                    <CampoSelect
                        nombre="sistema_id"
                        etiqueta="Sistema"
                        :opciones="conOpcionVacia(sistemas, 'La organización entera')"
                        :valor-inicial="compromiso?.sistema_id ? String(compromiso.sistema_id) : undefined"
                        :error="errors.sistema_id"
                        ayuda="Se deja en blanco si la obligación es de la organización entera, como el informe INES. La renovación de conformidad sí es de un sistema concreto."
                    />
                </FilaCampos>

                <CampoTextarea
                    nombre="notas"
                    etiqueta="Notas"
                    :filas="3"
                    :valor-inicial="compromiso?.notas ?? undefined"
                    :error="errors.notas"
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
