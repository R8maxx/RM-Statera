<script setup lang="ts">
import CampoOpciones from '@/components/formulario/CampoOpciones.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Opcion } from '@/lib/formularios';

/**
 * Evaluar el contrato de un proveedor (§ 4.9, A.5.20, `op.ext.1`).
 *
 * **Cada cláusula vigente se contesta**, y ninguna viene marcada: una respuesta
 * preseleccionada es una que nadie leyó. «No aplica» es una respuesta; dejarla
 * en blanco, no.
 *
 * La evaluación no se edita después: lo que se registra es lo que decía el
 * contrato hoy. Por eso el botón dice lo que hace.
 */
interface Clausula {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    referencias: string;
}

defineProps<{
    proveedor: {
        id: number;
        codigo: string;
        nombre: string;
        criticidad: string;
        es_nube: boolean;
        es_subencargado_rgpd: boolean;
    };
    clausulas: Clausula[];
    resultados: Opcion[];
    respuestas: Opcion[];
    hoy: string;
}>();
</script>

<template>
    <AppLayout :titulo="`Evaluar ${proveedor.nombre}`">
        <FormularioRecurso
            :titulo="`Evaluar ${proveedor.nombre}`"
            :descripcion="`Criticidad ${proveedor.criticidad.toLowerCase()}. Lo que se registra aquí es lo que dice su contrato hoy, cláusula a cláusula, y no se edita después: si cambia, se evalúa otra vez.`"
            :action="`/proveedores/${proveedor.id}/evaluaciones`"
            method="post"
            etiqueta-enviar="Registrar la evaluación"
            :url-cancelar="`/proveedores/${proveedor.id}`"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="Las cláusulas"
                ayuda="Lo que se comprueba en el contrato. Sale del catálogo, no de esta organización: es el mismo para todos."
            >
                <div v-for="clausula in clausulas" :key="clausula.id" class="grid gap-2 border-b pb-4 last:border-b-0">
                    <CampoOpciones
                        :nombre="`clausulas[${clausula.id}][resultado]`"
                        :etiqueta="`${clausula.codigo} · ${clausula.titulo}`"
                        :opciones="respuestas"
                        :error="errors[`clausulas.${clausula.id}.resultado`]"
                        :ayuda="`${clausula.descripcion ?? ''} ${clausula.referencias ? `(${clausula.referencias})` : ''}`.trim()"
                        requerido
                    />
                    <CampoTexto
                        :nombre="`clausulas[${clausula.id}][nota]`"
                        :etiqueta="`Nota sobre ${clausula.codigo}`"
                        :error="errors[`clausulas.${clausula.id}.nota`]"
                        placeholder="Dónde lo dice el contrato, o qué falta."
                    />
                </div>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="La conclusión"
                ayuda="Apto homologa al proveedor. Apto con condiciones deja algo pendiente, y lo pendiente va escrito. No apto lo rechaza. Si alguna cláusula no se cumple, «apto» no se admite."
            >
                <FilaCampos>
                    <CampoTexto nombre="fecha" etiqueta="Fecha" tipo="date" :valor-inicial="hoy" :error="errors.fecha" requerido />
                    <CampoSelect nombre="resultado" etiqueta="Resultado" :opciones="resultados" :error="errors.resultado" requerido />
                </FilaCampos>

                <CampoTextarea
                    nombre="conclusiones"
                    etiqueta="Conclusiones"
                    :filas="4"
                    :error="errors.conclusiones"
                    ayuda="Obligatorio si no es apto sin más: qué falla, o qué condiciones se ponen y para cuándo."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
