<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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
}>();

const edicion = props.incidente !== null;

const valor = computed(() => ({
    codigo: props.incidente?.codigo ?? props.sugerencia?.codigo ?? '',
    fechaDeteccion: props.incidente?.fecha_deteccion ?? props.sugerencia?.fecha_deteccion ?? '',
    clasificacion: props.incidente?.clasificacion ?? 'otros',
    peligrosidad: props.incidente?.peligrosidad ?? 'baja',
}));

/*
 * Las cinco dimensiones y las dos casillas de notificación se llevan en local
 * porque una casilla sin marcar **no viaja en el formulario**: sin un campo
 * oculto con su valor, desmarcar «afecta a la confidencialidad» dejaría el valor
 * anterior puesto. El servidor lo vuelve a normalizar en `prepareForValidation`,
 * porque una petición puede no venir de esta pantalla.
 */
const dimensiones = ref({
    afecta_confidencialidad: props.incidente?.afecta_confidencialidad ?? false,
    afecta_integridad: props.incidente?.afecta_integridad ?? false,
    afecta_disponibilidad: props.incidente?.afecta_disponibilidad ?? false,
    afecta_autenticidad: props.incidente?.afecta_autenticidad ?? false,
    afecta_trazabilidad: props.incidente?.afecta_trazabilidad ?? false,
});

const notificable = ref({
    notificable_aepd: props.incidente?.notificable_aepd ?? false,
    notificable_ccn_cert: props.incidente?.notificable_ccn_cert ?? false,
});

const activos = ref<number[]>([...props.activosVinculados]);

const etiquetasDimension: Record<string, string> = {
    afecta_confidencialidad: 'Confidencialidad',
    afecta_integridad: 'Integridad',
    afecta_disponibilidad: 'Disponibilidad',
    afecta_autenticidad: 'Autenticidad',
    afecta_trazabilidad: 'Trazabilidad',
};

const opcionesClasificacion = computed(() =>
    props.clasificaciones.map((clase) => ({ valor: clase.valor, etiqueta: clase.etiqueta })),
);

const ayudaClasificacion = computed(
    () =>
        props.clasificaciones.find((clase) => clase.valor === valor.value.clasificacion)?.descripcion ??
        '',
);

function alternarActivo(id: number, marcado: boolean): void {
    activos.value = marcado ? [...activos.value, id] : activos.value.filter((otro) => otro !== id);
}
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
                    v-model="valor.clasificacion"
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

                <div class="space-y-2">
                    <Label>Dimensiones afectadas</Label>
                    <p class="text-sm text-muted-foreground">
                        Las cinco del Anexo I. Es la primera pregunta de cualquier informe de
                        incidente, y la que decide si hay datos personales de por medio.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <label
                            v-for="(etiqueta, clave) in etiquetasDimension"
                            :key="clave"
                            class="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                :model-value="dimensiones[clave as keyof typeof dimensiones]"
                                @update:model-value="
                                    (marcado) =>
                                        (dimensiones[clave as keyof typeof dimensiones] =
                                            marcado === true)
                                "
                            />
                            {{ etiqueta }}
                            <input
                                type="hidden"
                                :name="clave"
                                :value="dimensiones[clave as keyof typeof dimensiones] ? 1 : 0"
                            />
                        </label>
                    </div>
                </div>
            </SeccionFormulario>

            <SeccionFormulario titulo="Alcance y respuesta" plegable>
                <CampoSelect
                    nombre="sistema_id"
                    etiqueta="Sistema"
                    :opciones="sistemas"
                    :valor-inicial="incidente?.sistema_id ? String(incidente.sistema_id) : undefined"
                    :error="errors.sistema_id"
                    ayuda="Opcional: un correo fraudulento a toda la organización no es de ningún sistema."
                />

                <CampoSelect
                    nombre="responsable_id"
                    etiqueta="Responsable"
                    :opciones="responsables"
                    :valor-inicial="
                        incidente?.responsable_id ? String(incidente.responsable_id) : undefined
                    "
                    :error="errors.responsable_id"
                />

                <div v-if="activosDisponibles.length > 0" class="space-y-2">
                    <Label>Activos afectados</Label>
                    <p class="text-sm text-muted-foreground">
                        Un cifrado por ransomware toca treinta equipos y sigue siendo un solo
                        incidente.
                    </p>
                    <div class="max-h-56 space-y-1 overflow-y-auto rounded-md border border-border p-3">
                        <label
                            v-for="activo in activosDisponibles"
                            :key="activo.valor"
                            class="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                :model-value="activos.includes(Number(activo.valor))"
                                @update:model-value="
                                    (marcado) => alternarActivo(Number(activo.valor), marcado === true)
                                "
                            />
                            {{ activo.etiqueta }}
                        </label>
                    </div>
                    <input
                        v-for="id in activos"
                        :key="`activo-${id}`"
                        type="hidden"
                        name="activos[]"
                        :value="id"
                    />
                    <!-- Sin esto, desmarcarlos todos no manda `activos` y el
                         controlador entiende «no tocar» en vez de «ninguno». -->
                    <input v-if="activos.length === 0" type="hidden" name="activos" value="" />
                </div>

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
                <p class="text-sm text-muted-foreground">
                    Aquí se decide <strong>si</strong> hay que notificar. Anotar
                    <strong>cuándo</strong> se notificó se hace después, desde la ficha: es el dato
                    que se contrasta contra el justificante.
                </p>

                <label class="flex items-start gap-2 text-sm">
                    <Checkbox
                        :model-value="notificable.notificable_aepd"
                        @update:model-value="
                            (marcado) => (notificable.notificable_aepd = marcado === true)
                        "
                    />
                    <span>
                        <strong>AEPD</strong> — hubo datos personales de por medio. Marcarlo pone en
                        marcha las 72 h del artículo 33.1 del RGPD desde la detección.
                    </span>
                    <input
                        type="hidden"
                        name="notificable_aepd"
                        :value="notificable.notificable_aepd ? 1 : 0"
                    />
                </label>

                <label class="flex items-start gap-2 text-sm">
                    <Checkbox
                        :model-value="notificable.notificable_ccn_cert"
                        @update:model-value="
                            (marcado) => (notificable.notificable_ccn_cert = marcado === true)
                        "
                    />
                    <span>
                        <strong>CCN-CERT</strong> — procede notificarlo por el ENS.
                        <span class="text-muted-foreground">
                            Sin cuenta atrás: el RD 311/2022 no fija horas, exige notificar «sin
                            dilación», y Statera no se inventa un plazo legal.
                        </span>
                    </span>
                    <input
                        type="hidden"
                        name="notificable_ccn_cert"
                        :value="notificable.notificable_ccn_cert ? 1 : 0"
                    />
                </label>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
