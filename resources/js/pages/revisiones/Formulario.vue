<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { ref } from 'vue';

interface Revision {
    id: number;
    fecha: string;
    responsable_id: number | null;
    alcance: string;
    altas: number;
    bajas: number;
    desviaciones: string | null;
    acciones: string | null;
}

const props = defineProps<{ revision: Revision | null; personas: Opcion[] }>();

const edicion = props.revision !== null;

const responsables = conOpcionVacia(props.personas, 'Sin asignar');
const responsable = ref(
    props.revision?.responsable_id ? String(props.revision.responsable_id) : SIN_VALOR,
);

/** Hoy por defecto: lo normal es registrar la revisión el día que se hace. */
const hoy = new Date().toISOString().slice(0, 10);
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar revisión' : 'Registrar revisión'">
        <FormularioRecurso
            :titulo="edicion ? 'Editar revisión' : 'Registrar revisión del inventario'"
            descripcion="A.5.9 de ISO y op.exp.1 del ENS no piden un inventario, piden un inventario mantenido. Esto es lo que demuestra la diferencia."
            :action="edicion ? `/revisiones/${revision!.id}` : '/revisiones'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar cambios' : 'Registrar revisión'"
            url-cancelar="/revisiones"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="Qué se revisó"
                ayuda="Una revisión parcial es perfectamente válida —el parque de puestos, la cuenta de la nube—, pero tiene que decir qué miró. Una que no lo dice no demuestra nada."
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoTexto
                        nombre="fecha"
                        etiqueta="Fecha"
                        tipo="date"
                        :valor-inicial="revision?.fecha ?? hoy"
                        :error="errors.fecha"
                        requerido
                        autofocus
                    />

                    <CampoSelect
                        v-model="responsable"
                        nombre="responsable_id"
                        etiqueta="Responsable de la revisión"
                        :opciones="responsables"
                        :error="errors.responsable_id"
                    />
                </div>

                <CampoTexto
                    nombre="alcance"
                    etiqueta="Alcance revisado"
                    :valor-inicial="revision?.alcance ?? ''"
                    :error="errors.alcance"
                    placeholder="Parque de puestos de trabajo"
                    requerido
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <CampoTexto
                        nombre="altas"
                        etiqueta="Altas"
                        tipo="number"
                        :valor-inicial="String(revision?.altas ?? 0)"
                        :error="errors.altas"
                        requerido
                    />

                    <CampoTexto
                        nombre="bajas"
                        etiqueta="Bajas"
                        tipo="number"
                        :valor-inicial="String(revision?.bajas ?? 0)"
                        :error="errors.bajas"
                        requerido
                    />
                </div>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Qué salió"
                ayuda="Esta es la parte que el auditor lee, y la que demuestra que la revisión sirvió para algo más que para poner una fecha. Dejarla en blanco es legítimo si de verdad no había nada; conviene que sea cierto."
            >
                <CampoTextarea
                    nombre="desviaciones"
                    etiqueta="Desviaciones detectadas"
                    :valor-inicial="revision?.desviaciones ?? ''"
                    :error="errors.desviaciones"
                    :filas="4"
                    ayuda="Equipos sin propietario, datos que no cuadran, activos que ya no están donde decía la ficha."
                />

                <CampoTextarea
                    nombre="acciones"
                    etiqueta="Acciones acordadas y plazo"
                    :valor-inicial="revision?.acciones ?? ''"
                    :error="errors.acciones"
                    :filas="4"
                    ayuda="Qué se va a hacer con cada desviación y para cuándo."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
