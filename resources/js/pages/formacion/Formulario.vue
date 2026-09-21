<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { conOpcionVacia, type Opcion } from '@/lib/formularios';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed } from 'vue';

interface Accion {
    id: number;
    codigo: string;
    titulo: string;
    tipo: string;
    fecha: string;
    duracion_horas: string | null;
    contenido: string | null;
    evidencia_id: number | null;
}

const props = defineProps<{
    accion: Accion | null;
    sugerencia: { codigo: string; fecha: string } | null;
    tipos: { valor: string; etiqueta: string; medida: string }[];
    evidencias: Opcion[];
}>();

const edicion = props.accion !== null;

const valor = computed(() => ({
    codigo: props.accion?.codigo ?? props.sugerencia?.codigo ?? '',
    fecha: props.accion?.fecha ?? props.sugerencia?.fecha ?? '',
    tipo: props.accion?.tipo ?? 'concienciacion',
}));

const opcionesTipo = computed(() =>
    props.tipos.map((tipo) => ({ valor: tipo.valor, etiqueta: `${tipo.etiqueta} (${tipo.medida})` })),
);
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar sesión' : 'Nueva sesión'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${accion?.codigo}` : 'Nueva sesión'"
            descripcion="Una sesión de formación o de concienciación. Quién asistió se apunta después, desde su ficha: son veinte marcas y van en un solo gesto."
            :action="edicion ? `/formacion/${accion?.id}` : '/formacion'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Registrar sesión'"
            :url-cancelar="edicion ? `/formacion/${accion?.id}` : '/formacion'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué se impartió">
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
                    :valor-inicial="accion?.titulo ?? undefined"
                    :error="errors.titulo"
                    requerido
                />

                <CampoSelect
                    nombre="tipo"
                    etiqueta="Tipo"
                    :opciones="opcionesTipo"
                    :valor-inicial="valor.tipo"
                    :error="errors.tipo"
                    requerido
                    ayuda="El ENS las separa: concienciar es recordar lo que todo el mundo tiene que saber; formar es enseñar a hacer algo a quien lo tiene que hacer."
                />

                <CampoTexto
                    nombre="fecha"
                    etiqueta="Fecha"
                    tipo="date"
                    :valor-inicial="valor.fecha"
                    :error="errors.fecha"
                    requerido
                    ayuda="Cuándo se impartió. Una asistencia sólo cuenta como formación reciente durante doce meses."
                />

                <CampoTexto
                    nombre="duracion_horas"
                    etiqueta="Duración (horas)"
                    tipo="number"
                    step="0.25"
                    :valor-inicial="accion?.duracion_horas ?? undefined"
                    :error="errors.duracion_horas"
                />

                <CampoTextarea
                    nombre="contenido"
                    etiqueta="Contenido"
                    :filas="4"
                    :valor-inicial="accion?.contenido ?? undefined"
                    :error="errors.contenido"
                    ayuda="Qué se trató. Es lo que un auditor lee para decidir si la sesión cubre lo que la medida pide."
                />

                <!--
                    La prueba de la medida. El campo existía en la base y en el
                    `FormRequest` desde el primer día —con el nombre «hoja de
                    firmas»— y no había forma de rellenarlo desde ninguna
                    pantalla, así que `mp.per.3` y `mp.per.4` quedaban
                    declaradas y sin probar.
                -->
                <CampoSelect
                    nombre="evidencia_id"
                    etiqueta="Hoja de firmas"
                    :opciones="conOpcionVacia(evidencias, 'Sin evidencia adjunta')"
                    :valor-inicial="accion?.evidencia_id ? String(accion.evidencia_id) : undefined"
                    :error="errors.evidencia_id"
                    ayuda="Una evidencia que ya esté en el repositorio: la lista de asistentes firmada, el certificado o la captura de la plataforma. La misma prueba vale para todos los marcos donde aplique."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
