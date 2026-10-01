<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible } from '@/lib/celdas';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { computed } from 'vue';

interface Comunicacion {
    id: number;
    fecha: string;
    prevista: string | null;
    asunto: string;
    resumen: string | null;
    canal: string;
    parte_interesada_id: string | null;
    tipo_recibida: string | null;
    respuesta: string | null;
    evidencia_id: string | null;
}

/**
 * Lo que se comunicó o se recibió, en el alta y en la edición.
 *
 * **Lo recibido es la retroalimentación de la 9.3.2 e)**: quién lo dijo, qué es
 * —una queja, una sugerencia, una encuesta— y qué se contestó. Lo emitido suelto
 * puede apuntarse a una línea del plan, y entonces la cumple.
 *
 * En la edición no se mueven la fecha ni la línea del plan: de las dos cuelga
 * hasta cuándo cubre. Si se apuntó mal, se borra y se registra otra vez.
 */
const props = defineProps<{
    comunicacion: Comunicacion | null;
    sentido: 'emitida' | 'recibida';
    sugerencia: { fecha: string } | null;
    canales: Opcion[];
    tipos: Opcion[];
    partesInteresadas: Opcion[];
    previstas: Opcion[];
    evidencias: Opcion[];
}>();

const edicion = props.comunicacion !== null;
const recibida = computed(() => props.sentido === 'recibida');

const titulo = computed(() => {
    if (edicion) {
        return recibida.value ? 'Editar lo recibido' : 'Editar lo comunicado';
    }

    return recibida.value ? 'Registrar lo recibido' : 'Registrar lo comunicado';
});
</script>

<template>
    <AppLayout :titulo="titulo">
        <FormularioRecurso
            :titulo="titulo"
            :descripcion="
                recibida
                    ? 'Una queja, una sugerencia, el resultado de una encuesta: la retroalimentación que revisa la dirección (9.3.2 e).'
                    : 'Algo que se comunicó a una parte interesada. Si cumple una línea del plan, indícala.'
            "
            :action="edicion ? `/comunicaciones/${comunicacion?.id}` : '/comunicaciones'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Registrar'"
            url-cancelar="/comunicaciones"
            #default="{ errors }"
        >
            <input v-if="!edicion" type="hidden" name="sentido" :value="sentido" />

            <SeccionFormulario :titulo="recibida ? 'Qué se recibió' : 'Qué se comunicó'">
                <FilaCampos>
                    <CampoTexto
                        v-if="!edicion"
                        nombre="fecha"
                        etiqueta="Fecha"
                        tipo="date"
                        :valor-inicial="sugerencia?.fecha ?? ''"
                        :error="errors.fecha"
                        requerido
                        ayuda="Es un hecho: no puede ser una fecha futura."
                    />
                    <p v-else class="text-sm">
                        <span class="block text-muted-foreground">Fecha</span>
                        {{ fechaLegible(comunicacion?.fecha ?? '') }}
                        <template v-if="comunicacion?.prevista"> · cumple {{ comunicacion.prevista }}</template>
                    </p>

                    <CampoSelect
                        v-if="recibida"
                        nombre="tipo_recibida"
                        etiqueta="Qué es"
                        :opciones="tipos"
                        :valor-inicial="comunicacion?.tipo_recibida ?? undefined"
                        :error="errors.tipo_recibida"
                        requerido
                    />
                </FilaCampos>

                <CampoTexto
                    nombre="asunto"
                    etiqueta="Asunto"
                    :valor-inicial="comunicacion?.asunto ?? ''"
                    :error="errors.asunto"
                    requerido
                    autofocus
                />

                <CampoTextarea
                    nombre="resumen"
                    etiqueta="Resumen"
                    :filas="3"
                    :valor-inicial="comunicacion?.resumen ?? undefined"
                    :error="errors.resumen"
                    :ayuda="recibida ? 'Qué se dijo, con las palabras de quien lo dijo si se puede.' : undefined"
                />

                <FilaCampos>
                    <CampoSelect
                        nombre="parte_interesada_id"
                        :etiqueta="recibida ? 'De quién' : 'A quién'"
                        :opciones="conOpcionVacia(partesInteresadas, 'Ninguna registrada')"
                        :valor-inicial="comunicacion?.parte_interesada_id ?? SIN_VALOR"
                        :error="errors.parte_interesada_id"
                    />

                    <CampoSelect
                        nombre="canal"
                        :etiqueta="recibida ? 'Por dónde llegó' : 'Cómo'"
                        :opciones="canales"
                        :valor-inicial="comunicacion?.canal ?? 'correo'"
                        :error="errors.canal"
                        requerido
                    />
                </FilaCampos>

                <CampoSelect
                    v-if="!recibida && !edicion"
                    nombre="comunicacion_prevista_id"
                    etiqueta="Cumple la línea del plan"
                    :opciones="conOpcionVacia(previstas, 'Ninguna: no estaba prevista')"
                    :valor-inicial="SIN_VALOR"
                    :error="errors.comunicacion_prevista_id"
                    ayuda="Si la cumple, mueve su próxima fecha."
                />
            </SeccionFormulario>

            <SeccionFormulario v-if="recibida" titulo="Qué se hizo con ello">
                <CampoTextarea
                    nombre="respuesta"
                    etiqueta="Respuesta"
                    :filas="3"
                    :valor-inicial="comunicacion?.respuesta ?? undefined"
                    :error="errors.respuesta"
                    ayuda="Qué se contestó o qué se decidió. Se puede completar después."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Prueba" plegable>
                <CampoSelect
                    nombre="evidencia_id"
                    etiqueta="Evidencia"
                    :opciones="conOpcionVacia(evidencias, 'Sin prueba')"
                    :valor-inicial="comunicacion?.evidencia_id ?? SIN_VALOR"
                    :error="errors.evidencia_id"
                    ayuda="Una evidencia ya subida: el correo, el acta, el informe de la encuesta."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
