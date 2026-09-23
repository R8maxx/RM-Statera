<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { conOpcionVacia } from '@/lib/formularios';
import AppLayout from '@/layouts/AppLayout.vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Bia {
    id: number;
    activo_id: number;
    servicio: string | null;
    servicioCodigo: string | null;
    impacto_4h: string;
    impacto_1d: string;
    impacto_3d: string;
    impacto_1s: string;
    impacto_1m: string;
    rto_horas: number;
    rpo_horas: number;
    justificacion: string | null;
    responsable_id: number | null;
}

const props = defineProps<{
    bia: Bia | null;
    servicios: Opcion[];
    nivelesImpacto: Opcion[];
    responsables: Opcion[];
}>();

const edicion = props.bia !== null;
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar BIA de ${bia?.servicio}` : 'Registrar BIA'">
        <FormularioRecurso
            :titulo="edicion ? `Editar el BIA de ${bia?.servicio}` : 'Registrar un BIA'"
            descripcion="El análisis de impacto en el negocio de un servicio: qué tan grave es no tenerlo, a cada horizonte, y qué RTO y RPO se declaran. § 4.11."
            :action="edicion ? `/continuidad/bia/${bia?.id}` : '/continuidad/bia'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Registrar'"
            :url-cancelar="edicion ? `/continuidad/bia/${bia?.id}` : '/continuidad/bia'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="El servicio">
                <!--
                    `activo_id` no se edita después del alta: cambiar de
                    servicio no es editar un BIA, es registrar uno distinto.
                    En edición se enseña como texto, no como desplegable.
                -->
                <CampoSelect
                    v-if="!edicion"
                    nombre="activo_id"
                    etiqueta="Servicio"
                    :opciones="servicios"
                    :error="errors.activo_id"
                    requerido
                    autofocus
                    ayuda="Sólo activos de tipo «Servicios»: el impacto de un servidor o un disco lo hereda el servicio que depende de ellos."
                />
                <p v-else class="text-sm">
                    <span class="font-medium">{{ bia?.servicio }}</span>
                    <span class="cifra ml-2 text-xs text-muted-foreground">{{ bia?.servicioCodigo }}</span>
                </p>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Los cinco tramos del MTPD"
                ayuda="Cómo de grave es no tener el servicio disponible, a cada horizonte. El primer tramo que llegue a «muy alto» fija el umbral tolerable."
            >
                <CampoSelect
                    nombre="impacto_4h"
                    etiqueta="A las 4 horas"
                    :opciones="nivelesImpacto"
                    :valor-inicial="bia?.impacto_4h ?? 'bajo'"
                    :error="errors.impacto_4h"
                    requerido
                />
                <CampoSelect
                    nombre="impacto_1d"
                    etiqueta="A 1 día"
                    :opciones="nivelesImpacto"
                    :valor-inicial="bia?.impacto_1d ?? 'bajo'"
                    :error="errors.impacto_1d"
                    requerido
                />
                <CampoSelect
                    nombre="impacto_3d"
                    etiqueta="A 3 días"
                    :opciones="nivelesImpacto"
                    :valor-inicial="bia?.impacto_3d ?? 'bajo'"
                    :error="errors.impacto_3d"
                    requerido
                />
                <CampoSelect
                    nombre="impacto_1s"
                    etiqueta="A 1 semana"
                    :opciones="nivelesImpacto"
                    :valor-inicial="bia?.impacto_1s ?? 'bajo'"
                    :error="errors.impacto_1s"
                    requerido
                />
                <CampoSelect
                    nombre="impacto_1m"
                    etiqueta="A 1 mes"
                    :opciones="nivelesImpacto"
                    :valor-inicial="bia?.impacto_1m ?? 'bajo'"
                    :error="errors.impacto_1m"
                    requerido
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="RTO y RPO">
                <CampoTexto
                    nombre="rto_horas"
                    etiqueta="RTO"
                    tipo="number"
                    :valor-inicial="bia?.rto_horas ?? undefined"
                    :error="errors.rto_horas"
                    requerido
                    ayuda="El tiempo de recuperación objetivo, en horas. Se compara con el umbral tolerable que derivan los cinco tramos de arriba."
                />
                <CampoTexto
                    nombre="rpo_horas"
                    etiqueta="RPO"
                    tipo="number"
                    :valor-inicial="bia?.rpo_horas ?? undefined"
                    :error="errors.rpo_horas"
                    requerido
                    ayuda="Cuántos datos se puede permitir perder la organización, en horas desde la última copia."
                />
                <CampoTextarea
                    nombre="justificacion"
                    etiqueta="Justificación"
                    :filas="3"
                    :valor-inicial="bia?.justificacion ?? undefined"
                    :error="errors.justificacion"
                    ayuda="Por qué esos tramos y ese RTO: lo que el auditor pregunta cuando el número no se explica solo."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Responsable" plegable>
                <CampoSelect
                    nombre="responsable_id"
                    etiqueta="Responsable"
                    :opciones="conOpcionVacia(responsables, 'Sin responsable')"
                    :valor-inicial="bia?.responsable_id ? String(bia.responsable_id) : undefined"
                    :error="errors.responsable_id"
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
