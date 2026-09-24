<script setup lang="ts">
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import CampoCasillas from '@/components/formulario/CampoCasillas.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { barraContextual } from '@/lib/motion';
import { motion } from 'motion-v';
import { computed, ref } from 'vue';

/**
 * Alta y edición de un riesgo.
 *
 * **La valoración no está aquí.** Se registra desde la ficha, por su propia ruta,
 * que es la que jubila la anterior y congela la escala con la que se midió. Un
 * riesgo nace registrado y sin valorar, y eso se puede contar: es el indicador
 * «sin valorar».
 */

interface ActivoVinculado {
    id: number;
    codigo: string;
    nombre: string;
}

interface Riesgo {
    id: number;
    codigo: string;
    titulo: string;
    amenaza_id: number | null;
    amenaza_libre: string | null;
    vulnerabilidad: string | null;
    propietario_id: number | null;
    fecha_revision: string | null;
    notas: string | null;
    activos: ActivoVinculado[];
}

const props = defineProps<{
    riesgo: Riesgo | null;
    amenazas: (Opcion & { grupo: string })[];
    activos: Opcion[];
    personas: Opcion[];
}>();

const edicion = props.riesgo !== null;

const { reducido } = useMovimientoReducido();
const variantesCampo = computed(() => (reducido.value ? { oculto: {}, visible: {} } : barraContextual));

/*
 * La opción vacía del select **no significa «ninguna»**: significa «no está en el
 * catálogo, la escribo yo». Se reutiliza `SIN_VALOR` porque el `FormRequest` ya lo
 * traduce a nulo en `amenaza_id` —está en `seleccionesOpcionales()`—, y así el
 * `CHECK` de la base recibe exactamente una de las dos columnas rellena.
 */
const amenazas = conOpcionVacia(props.amenazas, 'No está en el catálogo (la escribo yo)');

const amenaza = ref(props.riesgo?.amenaza_id ? String(props.riesgo.amenaza_id) : SIN_VALOR);
const delCatalogo = computed(() => amenaza.value !== SIN_VALOR);

const responsables = conOpcionVacia(props.personas, 'Sin asignar');
const propietario = ref(
    props.riesgo?.propietario_id ? String(props.riesgo.propietario_id) : SIN_VALOR,
);

const alcance = ref<string[]>(props.riesgo?.activos.map((activo) => String(activo.id)) ?? []);
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar ${riesgo!.codigo}` : 'Nuevo riesgo'">
        <FormularioRecurso
            :titulo="edicion ? `Editar el riesgo ${riesgo!.codigo}` : 'Registrar un riesgo'"
            descripcion="Un riesgo es una amenaza sobre unos activos. El impacto se deduce de lo que valen esos activos —incluido lo que heredan por el grafo de dependencias—, así que elegirlos bien es la mitad del trabajo."
            :action="edicion ? `/riesgos/${riesgo!.id}` : '/riesgos'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar cambios' : 'Registrar riesgo'"
            :url-cancelar="edicion ? `/riesgos/${riesgo!.id}` : '/riesgos'"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="Qué puede pasar"
                ayuda="La amenaza es lo que ocurre; la vulnerabilidad es por qué nos afectaría a nosotros. Separarlas es lo que permite tratar la segunda cuando la primera no se puede evitar."
            >
                <CampoTexto
                    nombre="titulo"
                    etiqueta="Título"
                    :valor-inicial="riesgo?.titulo ?? ''"
                    :error="errors.titulo"
                    placeholder="Robo de un portátil con información de clientes"
                    requerido
                    autofocus
                />

                <CampoSelect
                    v-model="amenaza"
                    nombre="amenaza_id"
                    etiqueta="Amenaza"
                    :opciones="amenazas"
                    :error="errors.amenaza_id"
                    ayuda="Del catálogo de MAGERIT. La letra del código dice el grupo: N desastres naturales, I de origen industrial, E errores no intencionados, A ataques intencionados."
                    placeholder="Elige una amenaza"
                    requerido
                />

                <!--
                    Éste sí lleva `v-if`, a diferencia de la regla general de que un
                    campo que existe en edición nunca se saca del DOM. Aquí es lo
                    correcto: el `CHECK` de la base exige exactamente UNA de las dos
                    columnas, así que cuando la amenaza viene del catálogo esta otra
                    tiene que llegar ausente, no vacía.
                -->
                <motion.div
                    v-if="!delCatalogo"
                    :variants="variantesCampo"
                    initial="oculto"
                    animate="visible"
                    class="overflow-hidden"
                >
                    <CampoTexto
                        nombre="amenaza_libre"
                        etiqueta="La amenaza, en tus palabras"
                        :valor-inicial="riesgo?.amenaza_libre ?? ''"
                        :error="errors.amenaza_libre"
                        placeholder="El proveedor del ERP cierra"
                        ayuda="Para lo que MAGERIT no recoge. No se añade al catálogo: es de este riesgo y de ninguno más."
                        requerido
                    />
                </motion.div>

                <CampoTextarea
                    nombre="vulnerabilidad"
                    etiqueta="Vulnerabilidad"
                    :valor-inicial="riesgo?.vulnerabilidad ?? ''"
                    :error="errors.vulnerabilidad"
                    :filas="3"
                    ayuda="Qué nos expone a esa amenaza. «Los portátiles salen de la oficina sin cifrar», no «robo»."
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Sobre qué pesa"
                ayuda="Un riesgo puede pesar sobre treinta activos y sigue siendo uno solo. Duplicarlo por activo haría que el indicador dijera treinta donde hay una cosa que decidir."
            >
                <CampoCasillas
                    v-model="alcance"
                    nombre="activos"
                    etiqueta="Activos afectados"
                    :opciones="activos"
                    :error="errors.activos"
                    vacio="No hay ningún activo vigente en el inventario. Un riesgo necesita al menos uno: el impacto se deduce de lo que valen."
                    requerido
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Quién responde y cuándo se revisa"
                ayuda="ISO 27001 exige que el propietario del riesgo apruebe lo que queda después de tratarlo. Sin propietario, el riesgo se puede medir pero no se puede aceptar."
                plegable
            >
                <FilaCampos>
                    <CampoSelect
                        v-model="propietario"
                        nombre="propietario_id"
                        etiqueta="Propietario del riesgo"
                        :opciones="responsables"
                        :error="errors.propietario_id"
                        ayuda="Quien responde de la decisión, que no es quien hace el trabajo."
                    />

                    <CampoTexto
                        nombre="fecha_revision"
                        etiqueta="Próxima reevaluación"
                        tipo="date"
                        :valor-inicial="riesgo?.fecha_revision ?? ''"
                        :error="errors.fecha_revision"
                        ayuda="Sin fecha no es que no corra prisa: es que nadie ha dicho cuándo toca volver a mirarlo."
                    />
                </FilaCampos>

                <CampoTextarea
                    nombre="notas"
                    etiqueta="Notas"
                    :valor-inicial="riesgo?.notas ?? ''"
                    :error="errors.notas"
                    :filas="3"
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
