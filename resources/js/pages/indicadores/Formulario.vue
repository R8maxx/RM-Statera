<script setup lang="ts">
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoSwitch from '@/components/formulario/CampoSwitch.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, watch } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface OpcionNumerica {
    valor: number;
    etiqueta: string;
}

interface Calculo {
    valor: string;
    etiqueta: string;
    metodo: string;
    unidad: string;
    sentido: string;
    admiteMarco: boolean;
}

interface Indicador {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    origen: string;
    calculo: string | null;
    formula_o_fuente: string | null;
    marco_id: number | null;
    unidad: string;
    sentido: string;
    periodicidad: string;
    objetivo: number | null;
    responsable_id: number | null;
    activo: boolean;
}

const props = defineProps<{
    indicador: Indicador | null;
    sugerencia: { codigo: string } | null;
    calculos: Calculo[];
    origenes: Opcion[];
    unidades: Opcion[];
    sentidos: Opcion[];
    periodicidades: Opcion[];
    marcos: OpcionNumerica[];
    responsables: OpcionNumerica[];
}>();

const edicion = props.indicador !== null;

/*
 * Las dos mitades del «formula_o_fuente» de la especificación se enseñan por
 * separado y nunca a la vez: un indicador es calculado o es manual, y el `CHECK`
 * de la tabla lo impone en las dos direcciones. Enseñar los dos campos invita a
 * rellenar los dos y a que la base rechace la fila con un mensaje que no
 * menciona ninguna de las dos palabras.
 */
const origen = ref(props.indicador?.origen ?? 'manual');
const calculo = ref(props.indicador?.calculo ?? '');
const unidad = ref(props.indicador?.unidad ?? 'recuento');
const sentido = ref(props.indicador?.sentido ?? 'mayor_mejor');

const esCalculado = computed(() => origen.value === 'calculado');

const calculoElegido = computed(() => props.calculos.find((c) => c.valor === calculo.value) ?? null);

/*
 * Al elegir un cálculo se **proponen** su unidad y su sentido naturales, y se
 * dejan editables: medir las tareas vencidas como porcentaje del total abierto
 * es legítimo. Propone y no impone, igual que el ámbito sugerido de una parte
 * interesada.
 */
watch(calculo, () => {
    if (calculoElegido.value === null) {
        return;
    }

    unidad.value = calculoElegido.value.unidad;
    sentido.value = calculoElegido.value.sentido;
});

const opcionesCalculo = computed<Opcion[]>(() =>
    props.calculos.map((c) => ({ valor: c.valor, etiqueta: c.etiqueta })),
);

const marcosComoOpciones = computed<Opcion[]>(() =>
    props.marcos.map((m) => ({ valor: String(m.valor), etiqueta: m.etiqueta })),
);

const responsablesComoOpciones = computed<Opcion[]>(() =>
    props.responsables.map((r) => ({ valor: String(r.valor), etiqueta: r.etiqueta })),
);

const admiteMarco = computed(() => esCalculado.value && (calculoElegido.value?.admiteMarco ?? false));
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar indicador' : 'Nuevo indicador'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${indicador?.codigo}` : 'Nuevo indicador'"
            descripcion="La cláusula 9.1 pregunta qué se mide, con qué método, cada cuánto y quién lo revisa. Las cuatro se contestan aquí."
            :action="edicion ? `/indicadores/${indicador?.id}` : '/indicadores'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Declarar indicador'"
            :url-cancelar="edicion ? `/indicadores/${indicador?.id}` : '/indicadores'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué se mide">
                <FilaCampos codigo>
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="indicador?.codigo ?? sugerencia?.codigo ?? ''"
                        :error="errors.codigo"
                        requerido
                        autofocus
                        ayuda="Único dentro de la organización. Se cita en las actas, así que conviene que sea corto."
                    />

                    <CampoTexto
                        nombre="nombre"
                        etiqueta="Nombre"
                        :valor-inicial="indicador?.nombre ?? ''"
                        :error="errors.nombre"
                        requerido
                        ayuda="Qué mide, en una línea."
                    />
                </FilaCampos>

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="3"
                    :valor-inicial="indicador?.descripcion ?? undefined"
                    :error="errors.descripcion"
                    ayuda="Para qué se sigue y qué decisión depende de él."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="De dónde sale la cifra">
                <FilaCampos>
                    <CampoSelect
                        v-model="origen"
                        nombre="origen"
                        etiqueta="Origen"
                        :opciones="origenes"
                        :error="errors.origen"
                        requerido
                        ayuda="Statera calcula unos cuantos leyendo sus propias tablas. El resto se registran a mano."
                    />

                    <CampoSelect
                        v-if="esCalculado"
                        v-model="calculo"
                        nombre="calculo"
                        etiqueta="Cálculo"
                        :opciones="opcionesCalculo"
                        :error="errors.calculo"
                        requerido
                        ayuda="Catálogo cerrado: cada cálculo usa la misma consulta que el panel, para que las dos cifras no puedan discrepar."
                    />
                </FilaCampos>

                <p
                    v-if="esCalculado && calculoElegido"
                    class="rounded-xl border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
                >
                    {{ calculoElegido.metodo }}
                </p>

                <CampoTextarea
                    v-else-if="!esCalculado"
                    nombre="formula_o_fuente"
                    etiqueta="Método"
                    :filas="3"
                    :valor-inicial="indicador?.formula_o_fuente ?? undefined"
                    :error="errors.formula_o_fuente"
                    requerido
                    ayuda="De dónde sale el número y quién lo toma. Es lo que pregunta la cláusula 9.1 b), y «lo cuenta el responsable del servicio desde la hoja de registro» es una respuesta válida."
                />

                <CampoSelect
                    v-if="admiteMarco"
                    nombre="marco_id"
                    etiqueta="Marco"
                    :opciones="marcosComoOpciones"
                    :valor-inicial="indicador?.marco_id ? String(indicador.marco_id) : undefined"
                    :error="errors.marco_id"
                    ayuda="Opcional. ISO y el ENS avanzan a ritmos distintos, y una sola cifra los promedia hasta que no dice nada."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Contra qué se juzga">
                <FilaCampos :columnas="3">
                    <CampoSelect
                        v-model="unidad"
                        nombre="unidad"
                        etiqueta="Unidad"
                        :opciones="unidades"
                        :error="errors.unidad"
                        requerido
                    />

                    <CampoSelect
                        v-model="sentido"
                        nombre="sentido"
                        etiqueta="Sentido"
                        :opciones="sentidos"
                        :error="errors.sentido"
                        requerido
                        ayuda="Hacia dónde mejora. Sin esto, el veredicto sale invertido en la mitad de los indicadores."
                    />

                    <CampoTexto
                        nombre="objetivo"
                        etiqueta="Objetivo"
                        tipo="number"
                        step="0.01"
                        :valor-inicial="indicador?.objetivo !== null && indicador?.objetivo !== undefined ? String(indicador.objetivo) : undefined"
                        :error="errors.objetivo"
                        ayuda="Opcional. Sin objetivo el indicador no está «fuera de objetivo»: está sin objetivo, que es una respuesta distinta y legítima."
                    />
                </FilaCampos>

                <FilaCampos :columnas="3">
                    <CampoSelect
                        nombre="periodicidad"
                        etiqueta="Cadencia"
                        :opciones="periodicidades"
                        :valor-inicial="indicador?.periodicidad ?? 'trimestral'"
                        :error="errors.periodicidad"
                        requerido
                        ayuda="Cada cuánto se mide. Un periodo que cierre sin medición sale en rojo: es la 9.1 sin hacer."
                    />

                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsablesComoOpciones"
                        :valor-inicial="indicador?.responsable_id ? String(indicador.responsable_id) : undefined"
                        :error="errors.responsable_id"
                        ayuda="Quién responde de que la cifra esté tomada a tiempo."
                    />

                    <CampoSwitch
                        v-if="edicion"
                        nombre="activo"
                        etiqueta="En seguimiento"
                        :valor-inicial="indicador?.activo ?? true"
                        :error="errors.activo"
                        ayuda="Retirarlo deja de pedir mediciones y conserva toda la serie: es esa serie la que explica por qué se dejó de medir."
                    />
                </FilaCampos>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
