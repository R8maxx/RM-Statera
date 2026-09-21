<script setup lang="ts">
import CampoCasillas from '@/components/formulario/CampoCasillas.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { conOpcionVacia } from '@/lib/formularios';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Incidente {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string;
    sistema_id: number | null;
    clasificacion: string;
    peligrosidad: string;
    fecha_deteccion: string;
    fecha_inicio: string | null;
    afecta_confidencialidad: boolean;
    afecta_integridad: boolean;
    afecta_disponibilidad: boolean;
    afecta_autenticidad: boolean;
    afecta_trazabilidad: boolean;
    impacto: string | null;
    acciones_contencion: string | null;
    responsable_id: number | null;
    notificable_aepd: boolean;
    notificable_ccn_cert: boolean;
}

const props = defineProps<{
    incidente: Incidente | null;
    activosVinculados: number[];
    sugerencia: { codigo: string; fecha_deteccion: string } | null;
    clasificaciones: { valor: string; etiqueta: string; descripcion: string }[];
    peligrosidades: Opcion[];
    sistemas: Opcion[];
    activosDisponibles: Opcion[];
    responsables: Opcion[];
    /** Las cinco del Anexo I, con el nombre de su columna como valor. */
    dimensionesDisponibles: Opcion[];
}>();

const edicion = props.incidente !== null;

const valor = computed(() => ({
    codigo: props.incidente?.codigo ?? props.sugerencia?.codigo ?? '',
    fechaDeteccion: props.incidente?.fecha_deteccion ?? props.sugerencia?.fecha_deteccion ?? '',
    peligrosidad: props.incidente?.peligrosidad ?? 'baja',
}));

/*
 * La clasificación **no** sale de `valor`, y no es un capricho: `valor` es un
 * `computed` que devuelve un objeto plano, así que escribir en una de sus
 * propiedades con `v-model` no invalida nada. La ayuda contextual de debajo se
 * quedaba congelada en la descripción de la clase inicial, y una vuelta del
 * servidor con errores de validación recalculaba el objeto y **perdía la clase
 * elegida**. Es lo único de este formulario que el usuario gobierna en vivo, así
 * que lleva su propio `ref`.
 */
const clasificacion = ref(props.incidente?.clasificacion ?? 'otros');

/*
 * Los tres grupos de casillas viajan como arrays y los pinta `CampoCasillas`,
 * que es el componente del producto: aporta el `fieldset`, la `legend`, el
 * `aria-invalid`, el mensaje de error y el `data-campo` que el resumen de
 * errores necesita para poder enfocar. Antes eran tres bloques a mano sin nada
 * de eso.
 *
 * Los dos primeros son **columnas booleanas** en la base, no relaciones: el
 * `FormRequest` las deriva del array, y **sólo si el array viene**, para que un
 * importador que postee los booleanos sueltos siga funcionando.
 */
const dimensiones = ref<string[]>(
    props.dimensionesDisponibles
        .map((opcion) => opcion.valor)
        .filter((clave) => props.incidente?.[clave as keyof Incidente] === true),
);

const notificables = ref<string[]>(
    (['notificable_aepd', 'notificable_ccn_cert'] as const).filter(
        (clave) => props.incidente?.[clave] === true,
    ),
);

const activos = ref<string[]>(props.activosVinculados.map(String));

/**
 * Los dos supervisores, con su descripción.
 *
 * Van escritos aquí y no en el servidor porque son texto de interfaz —el
 * porqué de marcar la casilla—, no vocabulario del dominio: los dos
 * destinatarios están fijados por ley y no hay catálogo detrás.
 */
const supervisores = [
    {
        valor: 'notificable_aepd',
        etiqueta: 'AEPD',
        descripcion:
            'Hubo datos personales de por medio. Marcarlo pone en marcha las 72 h del artículo 33.1 del RGPD desde la detección.',
    },
    {
        valor: 'notificable_ccn_cert',
        etiqueta: 'CCN-CERT',
        descripcion:
            'Procede notificarlo por el ENS. Sin cuenta atrás: el RD 311/2022 no fija horas, exige notificar «sin dilación», y Statera no se inventa un plazo legal.',
    },
];

const opcionesClasificacion = computed(() =>
    props.clasificaciones.map((clase) => ({ valor: clase.valor, etiqueta: clase.etiqueta })),
);

const ayudaClasificacion = computed(
    () =>
        props.clasificaciones.find((clase) => clase.valor === clasificacion.value)?.descripcion ??
        '',
);

</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar incidente' : 'Registrar incidente'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${incidente?.codigo}` : 'Registrar incidente'"
            descripcion="Qué ha pasado, a qué afectó y qué se hizo. Es op.exp.7, exigible desde categoría básica. Lo que se aprendió se escribe al cerrarlo."
            :action="edicion ? `/incidentes/${incidente?.id}` : '/incidentes'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Registrar'"
            :url-cancelar="edicion ? `/incidentes/${incidente?.id}` : '/incidentes'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué ha pasado">
                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="valor.codigo"
                    :error="errors.codigo"
                    requerido
                    autofocus
                />

                <CampoTexto
                    nombre="titulo"
                    etiqueta="Título"
                    :valor-inicial="incidente?.titulo ?? undefined"
                    :error="errors.titulo"
                    requerido
                    ayuda="En una línea: «correo fraudulento suplantando a la dirección»."
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="4"
                    :valor-inicial="incidente?.descripcion ?? undefined"
                    :error="errors.descripcion"
                    requerido
                />

                <CampoTexto
                    nombre="fecha_deteccion"
                    etiqueta="Detectado"
                    tipo="datetime-local"
                    :valor-inicial="valor.fechaDeteccion"
                    :error="errors.fecha_deteccion"
                    requerido
                    ayuda="Cuándo se tuvo constancia. Es desde aquí desde donde corren las 72 h de la AEPD, si hay datos personales de por medio."
                />

                <CampoTexto
                    nombre="fecha_inicio"
                    etiqueta="Empezó"
                    tipo="datetime-local"
                    :valor-inicial="incidente?.fecha_inicio ?? undefined"
                    :error="errors.fecha_inicio"
                    ayuda="En blanco mientras no se sepa, que es lo normal al principio. La diferencia con la detección es la primera cifra de cualquier informe de incidente."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Cómo se clasifica">
                <CampoSelect
                    v-model="clasificacion"
                    nombre="clasificacion"
                    etiqueta="Clasificación"
                    :opciones="opcionesClasificacion"
                    :error="errors.clasificacion"
                    requerido
                    :ayuda="ayudaClasificacion"
                />

                <CampoSelect
                    nombre="peligrosidad"
                    etiqueta="Peligrosidad"
                    :opciones="peligrosidades"
                    :valor-inicial="valor.peligrosidad"
                    :error="errors.peligrosidad"
                    requerido
                    ayuda="La declara quien registra el incidente. Statera no la deduce de las dimensiones afectadas: la guía no publica ninguna función que lo haga, y el mismo compromiso es crítico en un sistema y bajo en otro."
                />

                <CampoCasillas
                    v-model="dimensiones"
                    nombre="dimensiones"
                    etiqueta="Dimensiones afectadas"
                    :opciones="dimensionesDisponibles"
                    :error="errors.dimensiones"
                    ayuda="Las cinco del Anexo I. Es la primera pregunta de cualquier informe de incidente, y la que decide si hay datos personales de por medio."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Alcance y respuesta" plegable>
                <!--
                    Con la opción de «ninguno»: los dos son opcionales y sin
                    ella, una vez elegido el sistema no había forma de quitarlo
                    —Reka prohíbe el valor vacío en un `SelectItem`—.
                -->
                <CampoSelect
                    nombre="sistema_id"
                    etiqueta="Sistema"
                    :opciones="conOpcionVacia(sistemas, 'Ninguno en concreto')"
                    :valor-inicial="incidente?.sistema_id ? String(incidente.sistema_id) : undefined"
                    :error="errors.sistema_id"
                    ayuda="Opcional: un correo fraudulento a toda la organización no es de ningún sistema."
                />

                <CampoSelect
                    nombre="responsable_id"
                    etiqueta="Responsable"
                    :opciones="conOpcionVacia(responsables, 'Sin responsable')"
                    :valor-inicial="
                        incidente?.responsable_id ? String(incidente.responsable_id) : undefined
                    "
                    :error="errors.responsable_id"
                />

                <!--
                    El centinela de «ninguno» lo pone `CampoCasillas` y lo
                    traduce `NormalizaSeleccionVacia`. A mano estaba mal: el
                    campo oculto era escalar —`activos`, sin `[]`— y llegaba
                    como nulo, así que desmarcarlos todos no desvinculaba nada
                    y la edición entera fallaba en silencio.
                -->
                <CampoCasillas
                    v-model="activos"
                    nombre="activos"
                    etiqueta="Activos afectados"
                    :opciones="activosDisponibles"
                    :error="errors.activos"
                    desplazable
                    ayuda="Un cifrado por ransomware toca treinta equipos y sigue siendo un solo incidente."
                    vacio="Todavía no hay activos en el inventario a los que apuntar."
                />

                <CampoTextarea
                    nombre="impacto"
                    etiqueta="Impacto"
                    :filas="3"
                    :valor-inicial="incidente?.impacto ?? undefined"
                    :error="errors.impacto"
                    ayuda="A cuánta gente, a qué servicios y durante cuánto tiempo."
                />

                <CampoTextarea
                    nombre="acciones_contencion"
                    etiqueta="Acciones de contención"
                    :filas="3"
                    :valor-inicial="incidente?.acciones_contencion ?? undefined"
                    :error="errors.acciones_contencion"
                    ayuda="Lo que se hizo para parar el golpe. No es la acción correctiva: ésa cuelga de la no conformidad, si llega a haberla."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="A quién hay que notificar" plegable>
                <CampoCasillas
                    v-model="notificables"
                    nombre="notificables"
                    etiqueta="Supervisores a los que hay que notificar"
                    :opciones="supervisores"
                    :error="errors.notificables"
                    ayuda="Aquí se decide si hay que notificar. Anotar cuándo se notificó se hace después, desde la ficha: es el dato que se contrasta contra el justificante."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
