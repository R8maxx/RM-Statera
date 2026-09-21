<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Persona {
    id: number;
    codigo: string;
    nombre: string;
    nombre_pila: string;
    apellido1: string | null;
    apellido2: string | null;
    nif: string | null;
    telefono: string | null;
    telefono_fijo: string | null;
    direccion: string | null;
    fecha_nacimiento: string | null;
    puesto: string | null;
    email: string | null;
    user_id: number | null;
    fecha_alta: string;
    fecha_baja: string | null;
    notas: string | null;
}

const props = defineProps<{
    persona: Persona | null;
    sugerencia: { codigo: string; fecha_alta: string } | null;
    cuentas: Opcion[];
}>();

const edicion = props.persona !== null;

const valor = computed(() => ({
    codigo: props.persona?.codigo ?? props.sugerencia?.codigo ?? '',
    fechaAlta: props.persona?.fecha_alta ?? props.sugerencia?.fecha_alta ?? '',
}));
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar persona' : 'Nueva persona'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${persona?.nombre}` : 'Nueva persona'"
            descripcion="El registro de plantilla, no las cuentas de Statera. Es de donde salen los nombramientos del 5.3, la formación de mp.per.3 y mp.per.4 y los deberes por escrito de mp.per.2."
            :action="edicion ? `/personas/${persona?.id}` : '/personas'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Dar de alta'"
            :url-cancelar="edicion ? `/personas/${persona?.id}` : '/personas'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Quién es">
                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="valor.codigo"
                    :error="errors.codigo"
                    requerido
                    autofocus
                    ayuda="Único dentro de la organización. Se propone el siguiente, pero si ya hay número de empleado, es el que vale."
                />

                <!--
                    Tres campos y no uno. El nombre completo que se muestra, se
                    ordena y se busca lo calcula PostgreSQL desde estos tres, así
                    que aquí no hay ningún campo «nombre completo» que rellenar: no
                    existe como dato que se escriba.
                -->
                <CampoTexto
                    nombre="nombre_pila"
                    etiqueta="Nombre"
                    :valor-inicial="persona?.nombre_pila ?? undefined"
                    :error="errors.nombre_pila"
                    requerido
                />

                <CampoTexto
                    nombre="apellido1"
                    etiqueta="Primer apellido"
                    :valor-inicial="persona?.apellido1 ?? undefined"
                    :error="errors.apellido1"
                />

                <CampoTexto
                    nombre="apellido2"
                    etiqueta="Segundo apellido"
                    :valor-inicial="persona?.apellido2 ?? undefined"
                    :error="errors.apellido2"
                />

                <CampoTexto
                    nombre="puesto"
                    etiqueta="Puesto"
                    :valor-inicial="persona?.puesto ?? undefined"
                    :error="errors.puesto"
                    ayuda="El que ocupa en la organización, no el rol ENS: eso se designa aparte y por sistema."
                />

                <CampoTexto
                    nombre="email"
                    etiqueta="Correo"
                    tipo="email"
                    :valor-inicial="persona?.email ?? undefined"
                    :error="errors.email"
                />

                <CampoSelect
                    nombre="user_id"
                    etiqueta="Cuenta de Statera"
                    :opciones="cuentas"
                    :valor-inicial="persona?.user_id ? String(persona.user_id) : undefined"
                    :error="errors.user_id"
                    ayuda="El puente entre la plantilla y la herramienta, y puede estar vacío: la mayoría de la gente no entra nunca en Statera. Una cuenta pertenece como mucho a una persona."
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Identificación y contacto"
                ayuda="Datos personales. Se guardan para poder identificar y localizar a quien figura en un nombramiento o en una hoja de firmas, y no salen ni en la búsqueda de la tabla ni en la exportación."
            >
                <CampoTexto
                    nombre="nif"
                    etiqueta="NIF o documento"
                    :valor-inicial="persona?.nif ?? undefined"
                    :error="errors.nif"
                    ayuda="NIF, NIE o pasaporte. No se comprueba la letra —un documento extranjero no la tiene—, sólo que no haya dos iguales en la organización."
                />

                <CampoTexto
                    nombre="fecha_nacimiento"
                    etiqueta="Fecha de nacimiento"
                    tipo="date"
                    :valor-inicial="persona?.fecha_nacimiento ?? undefined"
                    :error="errors.fecha_nacimiento"
                />

                <CampoTexto
                    nombre="telefono"
                    etiqueta="Teléfono"
                    tipo="tel"
                    :valor-inicial="persona?.telefono ?? undefined"
                    :error="errors.telefono"
                />

                <CampoTexto
                    nombre="telefono_fijo"
                    etiqueta="Teléfono fijo"
                    tipo="tel"
                    :valor-inicial="persona?.telefono_fijo ?? undefined"
                    :error="errors.telefono_fijo"
                    ayuda="El fijo o el corporativo, si lo hay."
                />

                <CampoTextarea
                    nombre="direccion"
                    etiqueta="Dirección"
                    :filas="2"
                    :valor-inicial="persona?.direccion ?? undefined"
                    :error="errors.direccion"
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Desde cuándo y hasta cuándo">
                <CampoTexto
                    nombre="fecha_alta"
                    etiqueta="Fecha de alta"
                    tipo="date"
                    :valor-inicial="valor.fechaAlta"
                    :error="errors.fecha_alta"
                    requerido
                />

                <CampoTexto
                    nombre="fecha_baja"
                    etiqueta="Fecha de baja"
                    tipo="date"
                    :valor-inicial="persona?.fecha_baja ?? undefined"
                    :error="errors.fecha_baja"
                    ayuda="En blanco mientras siga en plantilla: «activa» no es una casilla, se deriva de este campo. Al rellenarla, la checklist de salida pasa a ser lo que hay que cerrar."
                />

                <CampoTextarea
                    nombre="notas"
                    etiqueta="Notas"
                    :filas="3"
                    :valor-inicial="persona?.notas ?? undefined"
                    :error="errors.notas"
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
