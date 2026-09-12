<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { ref } from 'vue';

interface Tarea {
    id: number;
    titulo: string;
    descripcion: string | null;
    origen: string;
    prioridad: string;
    responsable_id: number | null;
    fecha_limite: string | null;
    coste_estimado: string | null;
    notas: string | null;
}

interface Requisito {
    implantacionId: number;
    codigo: string;
    titulo: string;
    marco: string | null;
    sistema: string;
}

const props = defineProps<{
    tarea: Tarea | null;
    desdeImplantacion: Requisito | null;
    origenes: Opcion[];
    prioridades: Opcion[];
    responsables: Opcion[];
}>();

const edicion = props.tarea !== null;

const responsables = conOpcionVacia(props.responsables, 'Sin responsable');

/*
 * Cuando la tarea nace de un requisito pendiente, el origen ya está decidido y
 * no se pregunta: preguntarlo invita a cambiarlo, y entonces el campo que existe
 * para que la tarea sea trazable deja de serlo.
 */
const origen = ref(props.tarea?.origen ?? (props.desdeImplantacion ? 'brecha_implantacion' : 'propia'));
const prioridad = ref(props.tarea?.prioridad ?? 'media');
const responsable = ref(props.tarea?.responsable_id ? String(props.tarea.responsable_id) : SIN_VALOR);
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar ${tarea!.titulo}` : 'Nueva tarea'">
        <FormularioRecurso
            :titulo="edicion ? 'Editar tarea' : 'Nueva tarea'"
            descripcion="Lo que hay que hacer, quién lo hace y para cuándo. Una tarea puede hacer avanzar requisitos de varios marcos a la vez."
            :action="edicion ? `/tareas/${tarea!.id}` : '/tareas'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar cambios' : 'Crear tarea'"
            :url-cancelar="edicion ? `/tareas/${tarea!.id}` : '/tareas'"
            #default="{ errors }"
        >
            <!--
                El requisito del que sale viaja oculto: el vínculo se crea en la
                misma transacción que la tarea, porque una tarea que nace de un
                requisito y se queda sin el vínculo pierde justo lo que la hacía
                trazable.
            -->
            <input
                v-if="desdeImplantacion"
                type="hidden"
                name="implantaciones[]"
                :value="desdeImplantacion.implantacionId"
            />

            <SeccionFormulario
                titulo="Qué hay que hacer"
                ayuda="El título es lo que se lee en la lista y en el plan de adecuación: conviene que diga la acción, no el problema."
                plegable
            >
                <CampoTexto
                    nombre="titulo"
                    etiqueta="Título"
                    :valor-inicial="tarea?.titulo"
                    :error="errors.titulo"
                    requerido
                    autofocus
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :valor-inicial="tarea?.descripcion ?? ''"
                    :error="errors.descripcion"
                    :filas="3"
                    ayuda="En qué consiste y qué hace falta para darla por hecha."
                />

                <p v-if="desdeImplantacion" class="text-sm text-muted-foreground">
                    Sale de
                    <span class="cifra">{{ desdeImplantacion.codigo }}</span>
                    · {{ desdeImplantacion.titulo }}
                </p>

                <CampoSelect
                    v-else-if="!edicion"
                    v-model="origen"
                    nombre="origen"
                    etiqueta="Origen"
                    :opciones="origenes"
                    :error="errors.origen"
                    requerido
                    ayuda="De dónde sale. Hallazgo de auditoría, riesgo, incidente y revisión por la dirección llegarán con sus módulos."
                />

                <input v-if="desdeImplantacion || edicion" type="hidden" name="origen" :value="origen" />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Quién y para cuándo"
                ayuda="Una tarea sin responsable no la hace nadie, y una sin plazo no vence nunca: las dos cosas se pueden dejar en blanco, pero entonces nada avisará de ellas."
                plegable
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoSelect
                        v-model="responsable"
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="errors.responsable_id"
                    />

                    <CampoSelect
                        v-model="prioridad"
                        nombre="prioridad"
                        etiqueta="Prioridad"
                        :opciones="prioridades"
                        :error="errors.prioridad"
                        requerido
                    />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoTexto
                        nombre="fecha_limite"
                        etiqueta="Fecha límite"
                        tipo="date"
                        :valor-inicial="tarea?.fecha_limite ?? ''"
                        :error="errors.fecha_limite"
                    />

                    <CampoTexto
                        nombre="coste_estimado"
                        etiqueta="Coste estimado (€)"
                        tipo="number"
                        step="0.01"
                        :valor-inicial="tarea?.coste_estimado ?? ''"
                        :error="errors.coste_estimado"
                        ayuda="Para el plan de adecuación: una tarea sin coste no se puede presupuestar."
                    />
                </div>

                <CampoTextarea
                    nombre="notas"
                    etiqueta="Notas"
                    :valor-inicial="tarea?.notas ?? ''"
                    :error="errors.notas"
                    :filas="2"
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
