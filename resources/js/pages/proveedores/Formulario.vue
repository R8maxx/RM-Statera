<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoSwitch from '@/components/formulario/CampoSwitch.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { computed, ref } from 'vue';

/**
 * Alta y edición de un proveedor (§ 4.9).
 *
 * **El estado no se elige aquí**: lo decide una evaluación, y retirar va desde
 * la ficha. **Los activos tampoco**: se asignan desde el activo, que es donde
 * se sabe quién presta cada cosa.
 *
 * La criticidad se explica en vivo: con activos, el mínimo lo ponen ellos y
 * declararla por debajo pide justificarlo. El `FormRequest` y
 * `CriticidadProveedor` son quienes deciden; esto sólo avisa antes de enviar.
 */
interface Proveedor {
    id: number;
    codigo: string;
    nombre: string;
    cif: string | null;
    servicio_prestado: string;
    criticidad_declarada: string | null;
    justificacion_criticidad: string | null;
    es_nube: boolean;
    modelo_nube: string | null;
    ubicacion_datos: string;
    ubicacion_detalle: string | null;
    es_subencargado_rgpd: boolean;
    responsable_id: number | null;
    notas: string | null;
}

const props = defineProps<{
    proveedor: Proveedor | null;
    sugerencia: { codigo: string } | null;
    criticidadDerivada: { valor: string; etiqueta: string } | null;
    criticidades: Opcion[];
    modelosNube: Opcion[];
    ubicaciones: Opcion[];
    responsables: Opcion[];
}>();

const edicion = props.proveedor !== null;

const esNube = ref(props.proveedor?.es_nube ?? false);
const subencargado = ref(props.proveedor?.es_subencargado_rgpd ?? false);
const declarada = ref<string | undefined>(props.proveedor?.criticidad_declarada ?? undefined);

const pesos: Record<string, number> = { baja: 1, media: 2, alta: 3 };

/** Si lo declarado queda por debajo de lo que dicen los activos. */
const rebaja = computed(
    () =>
        props.criticidadDerivada !== null &&
        declarada.value !== undefined &&
        declarada.value !== SIN_VALOR &&
        (pesos[declarada.value] ?? 0) < (pesos[props.criticidadDerivada.valor] ?? 0),
);

const opcionesCriticidad = computed(() =>
    props.criticidadDerivada === null
        ? props.criticidades
        : conOpcionVacia(props.criticidades, `La de sus activos (${props.criticidadDerivada.etiqueta.toLowerCase()})`),
);

const responsables = conOpcionVacia(props.responsables, 'Sin responsable');
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar proveedor' : 'Nuevo proveedor'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${proveedor?.nombre}` : 'Nuevo proveedor'"
            descripcion="Quién es, qué presta y dónde tiene los datos. Si su contrato cumple lo que pide la organización lo dice una evaluación, no este formulario."
            :action="edicion ? `/proveedores/${proveedor?.id}` : '/proveedores'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Dar de alta'"
            :url-cancelar="edicion ? `/proveedores/${proveedor?.id}` : '/proveedores'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Quién es">
                <FilaCampos codigo>
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="proveedor?.codigo ?? sugerencia?.codigo ?? ''"
                        :error="errors.codigo"
                        requerido
                        autofocus
                        ayuda="Único en la organización. Se propone el siguiente."
                    />
                    <CampoTexto
                        nombre="nombre"
                        etiqueta="Nombre"
                        :valor-inicial="proveedor?.nombre ?? ''"
                        :error="errors.nombre"
                        requerido
                    />
                </FilaCampos>

                <FilaCampos>
                    <CampoTexto nombre="cif" etiqueta="CIF" :valor-inicial="proveedor?.cif ?? ''" :error="errors.cif" />
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :valor-inicial="proveedor?.responsable_id ? String(proveedor.responsable_id) : undefined"
                        :error="errors.responsable_id"
                        ayuda="Quién lleva la relación y a quién avisa el calendario cuando toca reevaluarlo."
                    />
                </FilaCampos>

                <CampoTextarea
                    nombre="servicio_prestado"
                    etiqueta="Qué presta"
                    :filas="3"
                    :valor-inicial="proveedor?.servicio_prestado ?? ''"
                    :error="errors.servicio_prestado"
                    requerido
                    ayuda="El servicio, no el contrato: «alojamiento de la sede electrónica», «nóminas», «limpieza con acceso a las oficinas»."
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Criticidad"
                ayuda="Cuánto depende la organización de este proveedor, y por tanto cada cuánto se le reevalúa. Si presta activos del inventario, el mínimo lo pone la valoración más alta de esos activos."
            >
                <Aviso v-if="criticidadDerivada" tono="info" :titulo="`Sus activos le dan criticidad ${criticidadDerivada.etiqueta.toLowerCase()}`">
                    Se puede declarar más alta sin más. Más baja, sólo explicando por qué: es la que decide cada cuánto
                    se vuelve a mirar su contrato.
                </Aviso>
                <Aviso v-else tono="info" titulo="No presta ningún activo del inventario">
                    Sin activos no hay de dónde derivarla, así que hay que declararla: una gestoría o la limpieza con
                    acceso a las oficinas siguen siendo terceros.
                </Aviso>

                <CampoSelect
                    v-model="declarada"
                    nombre="criticidad_declarada"
                    etiqueta="Criticidad"
                    :opciones="opcionesCriticidad"
                    :valor-inicial="proveedor?.criticidad_declarada ?? undefined"
                    :error="errors.criticidad_declarada"
                    :requerido="criticidadDerivada === null"
                />

                <CampoTextarea
                    v-if="rebaja || proveedor?.justificacion_criticidad"
                    nombre="justificacion_criticidad"
                    etiqueta="Por qué es menor que la de sus activos"
                    :filas="2"
                    :valor-inicial="proveedor?.justificacion_criticidad ?? ''"
                    :error="errors.justificacion_criticidad"
                    :requerido="rebaja"
                    ayuda="Por ejemplo: «el activo está replicado con otro proveedor y la caída de éste no para el servicio»."
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Nube y datos"
                ayuda="op.nub.1 es exigible desde la categoría básica, y A.5.23 pide lo mismo en ISO. Dónde están los datos decide si hay transferencia internacional."
            >
                <CampoSwitch v-model="esNube" nombre="es_nube" etiqueta="Presta un servicio en la nube" :error="errors.es_nube" />

                <CampoSelect
                    v-if="esNube"
                    nombre="modelo_nube"
                    etiqueta="Modelo de servicio"
                    :opciones="modelosNube"
                    :valor-inicial="proveedor?.modelo_nube ?? undefined"
                    :error="errors.modelo_nube"
                    requerido
                />

                <FilaCampos>
                    <CampoSelect
                        nombre="ubicacion_datos"
                        etiqueta="Dónde están los datos"
                        :opciones="ubicaciones"
                        :valor-inicial="proveedor?.ubicacion_datos ?? 'desconocida'"
                        :error="errors.ubicacion_datos"
                        requerido
                    />
                    <CampoTexto
                        nombre="ubicacion_detalle"
                        etiqueta="País o región"
                        :valor-inicial="proveedor?.ubicacion_detalle ?? ''"
                        :error="errors.ubicacion_detalle"
                        placeholder="Irlanda, Fráncfort, EE. UU.…"
                    />
                </FilaCampos>

                <CampoSwitch
                    v-model="subencargado"
                    nombre="es_subencargado_rgpd"
                    etiqueta="Trata datos personales por cuenta de la organización"
                    :error="errors.es_subencargado_rgpd"
                    ayuda="Es encargado —o subencargado— del tratamiento, y el contrato necesita lo que pide el artículo 28 del RGPD."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Lo demás">
                <CampoTextarea
                    nombre="notas"
                    etiqueta="Notas"
                    :filas="3"
                    :valor-inicial="proveedor?.notas ?? ''"
                    :error="errors.notas"
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
