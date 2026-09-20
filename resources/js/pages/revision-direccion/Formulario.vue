<script setup lang="ts">
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed } from 'vue';

interface Revision {
    id: number;
    codigo: string;
    fecha: string;
    periodo_desde: string;
    periodo_hasta: string;
    asistentes: string | null;
    conclusiones: string | null;
}

interface Sugerencia {
    codigo: string;
    periodo_desde: string;
    periodo_hasta: string;
}

const props = defineProps<{
    revision: Revision | null;
    sugerencia: Sugerencia | null;
}>();

const edicion = props.revision !== null;

const valor = computed(() => ({
    codigo: props.revision?.codigo ?? props.sugerencia?.codigo ?? '',
    fecha: props.revision?.fecha ?? new Date().toISOString().slice(0, 10),
    desde: props.revision?.periodo_desde ?? props.sugerencia?.periodo_desde ?? '',
    hasta: props.revision?.periodo_hasta ?? props.sugerencia?.periodo_hasta ?? '',
}));
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar revisión' : 'Convocar revisión'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${revision?.codigo}` : 'Convocar una revisión por la dirección'"
            descripcion="La cláusula 9.3 pide que la dirección revise el sistema de gestión con una cadencia planificada. Las siete entradas obligatorias se recogen solas al aprobar el acta."
            :action="edicion ? `/revision-direccion/${revision?.id}` : '/revision-direccion'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Convocar'"
            :url-cancelar="edicion ? `/revision-direccion/${revision?.id}` : '/revision-direccion'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="La reunión">
                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="valor.codigo"
                    :error="errors.codigo"
                    requerido
                    autofocus
                    ayuda="Único dentro de la organización. Se propone el siguiente del año."
                />

                <CampoTexto
                    nombre="fecha"
                    etiqueta="Fecha de celebración"
                    tipo="date"
                    :valor-inicial="valor.fecha"
                    :error="errors.fecha"
                    requerido
                    ayuda="Cuándo se celebra. No es lo mismo que el periodo revisado: una revisión del ejercicio pasado suele celebrarse en el siguiente."
                />

                <CampoTextarea
                    nombre="asistentes"
                    etiqueta="Asistentes"
                    :filas="3"
                    :valor-inicial="revision?.asistentes ?? undefined"
                    :error="errors.asistentes"
                    ayuda="Quién asiste, con su cargo. Es lo primero que un auditor comprueba, y no se saca de los usuarios de la herramienta: a una revisión por la dirección asiste gente que no tiene cuenta."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Periodo revisado">
                <CampoTexto
                    nombre="periodo_desde"
                    etiqueta="Desde"
                    tipo="date"
                    :valor-inicial="valor.desde"
                    :error="errors.periodo_desde"
                    requerido
                    ayuda="Se propone el día siguiente al fin de la última revisión aprobada: dos actas seguidas no deberían dejar un hueco sin revisar."
                />

                <CampoTexto
                    nombre="periodo_hasta"
                    etiqueta="Hasta"
                    tipo="date"
                    :valor-inicial="valor.hasta"
                    :error="errors.periodo_hasta"
                    requerido
                    ayuda="De qué habla el acta. Las auditorías se recogen acotadas a este periodo."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Conclusiones" plegable>
                <CampoTextarea
                    nombre="conclusiones"
                    etiqueta="Conclusiones de la dirección"
                    :filas="6"
                    :valor-inicial="revision?.conclusiones ?? undefined"
                    :error="errors.conclusiones"
                    ayuda="Es la única parte del acta que escribe una persona; todo lo demás se calcula. Las decisiones concretas se registran aparte, como tareas con responsable y plazo."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
