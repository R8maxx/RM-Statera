<script setup lang="ts">
import EtiquetaQr from '@/components/activo/EtiquetaQr.vue';
import CampoCasillas from '@/components/formulario/CampoCasillas.vue';
import CampoOpciones from '@/components/formulario/CampoOpciones.vue';
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
import { computed, ref, watch } from 'vue';

interface Activo {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    tipo: string;
    subtipo: string | null;
    marca_modelo: string | null;
    especificaciones: string | null;
    sistema_operativo: string | null;
    fin_soporte_so: string | null;
    identificador: string | null;
    propietario_id: number | null;
    custodio_id: number | null;
    departamento: string | null;
    ubicacion: string | null;
    fin_garantia: string | null;
    estado_ciclo_vida: string;
    clasificacion: string;
    cifrado: string;
    copia_seguridad: string;
    ultima_revision: string | null;
    observaciones: string | null;
    valor_c: string;
    valor_i: string;
    valor_d: string;
    valor_a: string;
    valor_t: string;
    fecha_alta: string | null;
    fecha_baja: string | null;
    borrado_seguro_en: string | null;
    nota_baja: string | null;
    sistemas: { id: number; codigo: string; nombre: string }[];
}

interface OpcionConAyuda extends Opcion {
    ayuda: string;
}

interface OpcionTipo extends OpcionConAyuda {
    /** Si a este tipo le corresponde marca, modelo y sistema operativo. */
    fichaTecnica: boolean;
}

interface DimensionDeclarada {
    codigo: string;
    campo: string;
    nombre: string;
    pregunta: string;
}

const props = defineProps<{
    activo: Activo | null;
    etiqueta: { svg: string; url: string } | null;
    tipos: OpcionTipo[];
    estados: Opcion[];
    niveles: Opcion[];
    dimensiones: DimensionDeclarada[];
    clasificaciones: OpcionConAyuda[];
    controles: Opcion[];
    /** Sistema operativo => fecha ISO de fin de soporte, o null. */
    sistemasOperativos: Record<string, string | null>;
    personas: Opcion[];
    sistemas: Opcion[];
}>();

const edicion = props.activo !== null;

const propietarios = conOpcionVacia(props.personas, 'Sin propietario');
const custodios = conOpcionVacia(props.personas, 'Sin custodio');

/** Los SO conocidos, más «otro» para lo que no esté en la lista. */
const sistemasOperativos = conOpcionVacia(
    Object.keys(props.sistemasOperativos).map((nombre) => ({ valor: nombre, etiqueta: nombre })),
    'Sin especificar',
);

const tipo = ref(props.activo?.tipo ?? 'servicios');
const estado = ref(props.activo?.estado_ciclo_vida ?? 'en_produccion');
const clasificacion = ref(props.activo?.clasificacion ?? 'no_aplica');
const cifrado = ref(props.activo?.cifrado ?? 'no_aplica');
const copia = ref(props.activo?.copia_seguridad ?? 'no_aplica');
const propietario = ref(
    props.activo?.propietario_id ? String(props.activo.propietario_id) : SIN_VALOR,
);
const custodio = ref(props.activo?.custodio_id ? String(props.activo.custodio_id) : SIN_VALOR);
const sistemaOperativo = ref(props.activo?.sistema_operativo ?? SIN_VALOR);
const finSoporte = ref(props.activo?.fin_soporte_so ?? '');
const alcance = ref(props.activo?.sistemas.map((sistema) => String(sistema.id)) ?? []);

/**
 * Al elegir un sistema operativo conocido se propone su fin de soporte.
 *
 * Es una PROPUESTA y no una imposición: el campo queda editable porque una
 * organización puede tener soporte extendido contratado, y porque hay versiones
 * que no están en la lista. Lo que se evita es que nadie ponga la fecha por no
 * saber buscarla, que es como el indicador de obsolescencia se queda vacío para
 * siempre.
 */
watch(sistemaOperativo, (nuevo) => {
    finSoporte.value = props.sistemasOperativos[nuevo] ?? '';
});

/**
 * Un select por dimensión, con la clave del campo como nombre: así el error del
 * `FormRequest` (`valor_d`) llega al control que lo produjo sin traducción.
 */
const valores = ref<Record<string, string>>(
    Object.fromEntries(
        props.dimensiones.map((dimension) => [
            dimension.campo,
            /*
             * Sin preselección en el alta. `na` no es «bajo»: significa que la
             * dimensión no es de aplicación, y traerla puesta convierte una
             * decisión de valoración en un descuido que no se ve. Es el mismo
             * fallo silencioso que tenía el catálogo con `op.exp.7`.
             */
            (props.activo?.[dimension.campo as keyof Activo] as string | undefined) ?? '',
        ]),
    ),
);

const tipoElegido = computed(() => props.tipos.find((uno) => uno.valor === tipo.value));

const ejemploDelTipo = computed(() => tipoElegido.value?.ayuda);

/** Marca, modelo y sistema operativo no dicen nada de un servicio ni de una persona. */
const conFichaTecnica = computed(() => tipoElegido.value?.fichaTecnica === true);

const ayudaClasificacion = computed(
    () => props.clasificaciones.find((una) => una.valor === clasificacion.value)?.ayuda,
);

/** La baja sólo se pregunta cuando el activo dice estar retirado o dado de baja. */
const daDeBaja = computed(() => estado.value === 'retirado' || estado.value === 'dado_de_baja');

/*
 * Tres campos que aparecen a la vez a media pantalla dan un salto seco. Es un
 * cambio de estado, que es de lo que sí se anima según DESIGN.md §10, y la
 * variante ya está escrita para esto en `lib/motion.ts`.
 */
const { reducido } = useMovimientoReducido();
const variantesBaja = computed(() =>
    reducido.value ? { oculto: {}, visible: {} } : barraContextual,
);
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar ${activo!.codigo}` : 'Nuevo activo'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${activo!.codigo}` : 'Nuevo activo'"
            descripcion="Lo que hay que proteger. La valoración que pongas aquí es la propia; lo que el activo vale de verdad sale además de lo que se apoye en él, y eso se declara luego en su ficha."
            :action="edicion ? `/activos/${activo!.id}` : '/activos'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar cambios' : 'Dar de alta'"
            :url-cancelar="edicion ? `/activos/${activo!.id}` : '/activos'"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="Identificación"
                ayuda="El código es el que se usa en las etiquetas y en el análisis de riesgos: conviene que sea corto y estable. El tipo sigue la tipología de MAGERIT, que es la que espera un auditor del ENS."
                plegable
            >
                <div class="grid gap-5 sm:grid-cols-[10rem_1fr]">
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="activo?.codigo"
                        :error="errors.codigo"
                        requerido
                        :autofocus="!edicion"
                    />

                    <CampoTexto
                        nombre="nombre"
                        etiqueta="Nombre"
                        :valor-inicial="activo?.nombre"
                        :error="errors.nombre"
                        requerido
                    />
                </div>

                <CampoSelect
                    v-model="tipo"
                    nombre="tipo"
                    etiqueta="Tipo"
                    :opciones="tipos"
                    :error="errors.tipo"
                    :ayuda="ejemploDelTipo"
                    requerido
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :valor-inicial="activo?.descripcion ?? ''"
                    :error="errors.descripcion"
                    :filas="3"
                    ayuda="Qué hace y para quién. Lo que haría falta saber para decidir si se puede apagar."
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoTexto
                        nombre="subtipo"
                        etiqueta="Subtipo"
                        :valor-inicial="activo?.subtipo ?? ''"
                        :error="errors.subtipo"
                        placeholder="Portátil, EC2, Router…"
                        ayuda="La etiqueta con la que lo busca la gente."
                    />

                    <CampoTexto
                        nombre="identificador"
                        etiqueta="Nº de serie o identificador"
                        :valor-inicial="activo?.identificador ?? ''"
                        :error="errors.identificador"
                        ayuda="Nº de serie, ARN, hostname o IP. Es lo que no cambia."
                    />
                </div>

                <CampoTexto
                    nombre="ubicacion"
                    etiqueta="Ubicación"
                    :valor-inicial="activo?.ubicacion ?? ''"
                    :error="errors.ubicacion"
                    ayuda="Dónde está físicamente o en qué proveedor. Es lo primero que se pregunta cuando hay un incidente."
                />
            </SeccionFormulario>

            <!--
                Plegada cuando el tipo elegido no la necesita, pero nunca fuera
                del DOM: lo que sale del DOM sale del `FormData`, y como estos
                campos son `nullable` una edición borraría en silencio el modelo
                y el fin de soporte de un activo al que le cambiaron el tipo.
            -->
            <SeccionFormulario
                titulo="Ficha técnica"
                ayuda="Marca, modelo y sistema operativo. La fecha de fin de soporte se propone sola al elegir una versión conocida: un sistema sin parches es op.exp.4 y no lo ve nadie hasta que hay un incidente."
                plegable
                :plegada-por-defecto="!conFichaTecnica"
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoTexto
                        nombre="marca_modelo"
                        etiqueta="Marca y modelo"
                        :valor-inicial="activo?.marca_modelo ?? ''"
                        :error="errors.marca_modelo"
                    />

                    <CampoTexto
                        nombre="fin_garantia"
                        etiqueta="Fin de garantía o soporte"
                        tipo="date"
                        :valor-inicial="activo?.fin_garantia ?? ''"
                        :error="errors.fin_garantia"
                    />
                </div>

                <CampoTextarea
                    nombre="especificaciones"
                    etiqueta="Especificaciones"
                    :valor-inicial="activo?.especificaciones ?? ''"
                    :error="errors.especificaciones"
                    :filas="2"
                    ayuda="CPU, memoria y almacenamiento, o lo que haga falta para decidir si aguanta otro año."
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoSelect
                        v-model="sistemaOperativo"
                        nombre="sistema_operativo"
                        etiqueta="Sistema operativo"
                        :opciones="sistemasOperativos"
                        :error="errors.sistema_operativo"
                    />

                    <CampoTexto
                        v-model="finSoporte"
                        nombre="fin_soporte_so"
                        etiqueta="Fin de soporte del sistema"
                        tipo="date"
                        :error="errors.fin_soporte_so"
                        ayuda="Editable: se puede tener soporte extendido contratado."
                    />
                </div>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Seguridad"
                ayuda="La clasificación es la etiqueta que se le pone a la información y se decide (mp.info.2); el nivel del Anexo I, más abajo, se deriva del perjuicio. Conviven. En cifrado y copia, «por confirmar» no es «no»: la ausencia de dato no es ausencia de control, y contarlas igual convierte una duda en un incumplimiento falso."
                plegable
            >
                <CampoSelect
                    v-model="clasificacion"
                    nombre="clasificacion"
                    etiqueta="Clasificación de la información"
                    :opciones="clasificaciones"
                    :error="errors.clasificacion"
                    :ayuda="ayudaClasificacion"
                    requerido
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoSelect
                        v-model="cifrado"
                        nombre="cifrado"
                        etiqueta="Cifrado en reposo"
                        :opciones="controles"
                        :error="errors.cifrado"
                        requerido
                    />

                    <CampoSelect
                        v-model="copia"
                        nombre="copia_seguridad"
                        etiqueta="Copia de seguridad"
                        :opciones="controles"
                        :error="errors.copia_seguridad"
                        requerido
                    />
                </div>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Custodia"
                ayuda="El propietario responde del activo; el custodio lo usa. Cuando un portátil cambia de manos sólo cambia el custodio: el activo conserva su código y la etiqueta pegada en la carcasa sigue valiendo."
                plegable
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoSelect
                        v-model="propietario"
                        nombre="propietario_id"
                        etiqueta="Propietario"
                        :opciones="propietarios"
                        :error="errors.propietario_id"
                    />

                    <CampoSelect
                        v-model="custodio"
                        nombre="custodio_id"
                        etiqueta="Custodio"
                        :opciones="custodios"
                        :error="errors.custodio_id"
                    />
                </div>

                <CampoTexto
                    nombre="departamento"
                    etiqueta="Departamento"
                    :valor-inicial="activo?.departamento ?? ''"
                    :error="errors.departamento"
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Valoración"
                ayuda="Las cinco dimensiones del Anexo I, valoradas por el perjuicio que causaría el fallo. «No aplica» no es «bajo»: significa que la dimensión no es de aplicación a este activo."
                plegable
            >
                <CampoOpciones
                    v-for="dimension in dimensiones"
                    :key="dimension.codigo"
                    v-model="valores[dimension.campo]"
                    :nombre="dimension.campo"
                    :etiqueta="dimension.nombre"
                    :opciones="niveles"
                    :error="errors[dimension.campo]"
                    :ayuda="dimension.pregunta"
                    requerido
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Alcance"
                ayuda="En qué sistemas está declarado este activo. Puede estar en varios: el mismo servidor entra en el alcance del SGSI de ISO y en el del sistema del ENS, y duplicarlo sería volver a mantener dos inventarios."
                plegable
            >
                <CampoCasillas
                    v-model="alcance"
                    nombre="sistemas"
                    etiqueta="Sistemas"
                    :opciones="sistemas"
                    :error="errors.sistemas"
                    vacio="Todavía no hay ningún sistema dado de alta. El activo puede existir sin estar en ninguno."
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Ciclo de vida"
                ayuda="Retirado es que ya no presta servicio. Dado de baja es que además hay constancia de que se borró o destruyó lo que contenía, que es lo que exige mp.si.5. Un disco retirado que sigue en un cajón con los datos dentro es un hallazgo, no un activo cerrado."
                plegable
            >
                <CampoSelect
                    v-model="estado"
                    nombre="estado_ciclo_vida"
                    etiqueta="Estado"
                    :opciones="estados"
                    :error="errors.estado_ciclo_vida"
                    requerido
                />

                <!--
                    La fecha de baja no comparte rejilla con la de alta: con el
                    activo vigente desaparece y el `sm:grid-cols-2` dejaba media
                    fila en blanco.
                -->
                <CampoTexto
                    nombre="fecha_alta"
                    etiqueta="Fecha de alta"
                    tipo="date"
                    :valor-inicial="activo?.fecha_alta ?? ''"
                    :error="errors.fecha_alta"
                />

                <CampoTexto
                    nombre="ultima_revision"
                    etiqueta="Última revisión"
                    tipo="date"
                    :valor-inicial="activo?.ultima_revision ?? ''"
                    :error="errors.ultima_revision"
                    ayuda="Cuándo se comprobó por última vez que esta ficha se corresponde con la realidad. También se pone en lote desde la tabla."
                />

                <CampoTextarea
                    nombre="observaciones"
                    etiqueta="Observaciones"
                    :valor-inicial="activo?.observaciones ?? ''"
                    :error="errors.observaciones"
                    :filas="2"
                    ayuda="Lo que no cabe en ningún campo y es lo primero que alguien necesita leer."
                />

                <motion.div v-if="daDeBaja" :variants="variantesBaja" initial="oculto" animate="visible" class="grid gap-5 overflow-hidden">
                    <CampoTexto
                        nombre="fecha_baja"
                        etiqueta="Fecha de baja"
                        tipo="date"
                        :valor-inicial="activo?.fecha_baja ?? ''"
                        :error="errors.fecha_baja"
                        requerido
                    />

                    <CampoTexto
                        nombre="borrado_seguro_en"
                        etiqueta="Borrado seguro realizado el"
                        tipo="date"
                        :valor-inicial="activo?.borrado_seguro_en?.slice(0, 10) ?? ''"
                        :error="errors.borrado_seguro_en"
                        ayuda="Sin esta fecha el activo queda como retirado, no como dado de baja."
                    />

                    <CampoTextarea
                        nombre="nota_baja"
                        etiqueta="Cómo se borró o destruyó"
                        :valor-inicial="activo?.nota_baja ?? ''"
                        :error="errors.nota_baja"
                        :filas="2"
                        ayuda="El método y quién lo hizo. Es lo que se enseña cuando preguntan qué pasó con los datos."
                    />
                </motion.div>
            </SeccionFormulario>
            <SeccionFormulario
                v-if="etiqueta && activo"
                titulo="Etiqueta QR"
                ayuda="Sólo para mirar: no es un campo y no se envía con el formulario. El código lo genera el servidor a partir del identificador del activo, así que no cambia aunque cambies el nombre o el custodio."
                plegable
            >
                <EtiquetaQr :activo-id="activo.id" :svg="etiqueta.svg" :url="etiqueta.url" />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
