<script setup lang="ts">
import CampoCasillas from '@/components/formulario/CampoCasillas.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { conOpcionVacia } from '@/lib/formularios';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface TipoOpcion extends Opcion {
    descripcion: string;
}

interface Prueba {
    id: number;
    codigo: string;
    titulo: string;
    documento_id: number;
    plan: { id: number; codigo: string; titulo: string } | null;
    tipo: string;
    fecha_prevista: string;
    responsable_id: number | null;
}

/**
 * Planifica o edita una prueba de continuidad: § 4.11 y `op.cont.3`.
 *
 * **`documento_id` sólo se pide al planificar**, como `activo_id` en el BIA:
 * una prueba no cambia de plan, en edición se enseña como texto.
 *
 * **`servicios` va con `CampoCasillas`**, como `activos` en el formulario de
 * incidentes: es un grupo N:M y no una selección única, y con tres o cuatro
 * servicios las casillas enseñan a la vez qué hay y qué está marcado.
 */
const props = defineProps<{
    prueba: Prueba | null;
    serviciosVinculados: number[];
    sugerencia: { codigo: string } | null;
    planes: Opcion[];
    tipos: TipoOpcion[];
    servicios: Opcion[];
    responsables: Opcion[];
}>();

const edicion = props.prueba !== null;

const serviciosSeleccionados = ref<string[]>(props.serviciosVinculados.map(String));

/*
 * Con su propio `ref`, y no leído de `prueba` a cada render: mismo motivo que
 * `clasificacion` en el formulario de incidentes. Es lo único de aquí que la
 * ayuda contextual necesita en vivo, y una vuelta con errores de validación no
 * puede perder lo que la persona acaba de elegir.
 */
const tipoSeleccionado = ref(props.prueba?.tipo ?? 'sobremesa');

const ayudaTipo = computed(
    () => props.tipos.find((tipo) => tipo.valor === tipoSeleccionado.value)?.descripcion,
);
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar ${prueba?.codigo}` : 'Planificar prueba'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${prueba?.codigo}` : 'Planificar una prueba'"
            descripcion="Qué se va a comprobar del plan de continuidad, cuándo y sobre qué servicios. op.cont.3."
            :action="edicion ? `/continuidad/pruebas/${prueba?.id}` : '/continuidad/pruebas'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Planificar'"
            :url-cancelar="edicion ? `/continuidad/pruebas/${prueba?.id}` : '/continuidad/pruebas'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="El plan">
                <CampoSelect
                    v-if="!edicion"
                    nombre="documento_id"
                    etiqueta="Plan de continuidad"
                    :opciones="planes"
                    :error="errors.documento_id"
                    requerido
                    autofocus
                    ayuda="Sólo documentos de tipo «Plan de continuidad»."
                />
                <p v-else class="text-sm">
                    <span class="font-medium">{{ prueba?.plan?.titulo }}</span>
                    <span class="cifra ml-2 text-xs text-muted-foreground">{{ prueba?.plan?.codigo }}</span>
                </p>
            </SeccionFormulario>

            <SeccionFormulario titulo="La prueba">
                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="prueba?.codigo ?? sugerencia?.codigo"
                    :error="errors.codigo"
                    requerido
                    ayuda="Se propone solo; se puede escribir encima."
                />
                <CampoTexto
                    nombre="titulo"
                    etiqueta="Título"
                    :valor-inicial="prueba?.titulo"
                    :error="errors.titulo"
                    requerido
                />
                <CampoSelect
                    v-model="tipoSeleccionado"
                    nombre="tipo"
                    etiqueta="Tipo"
                    :opciones="tipos"
                    :error="errors.tipo"
                    requerido
                    :ayuda="ayudaTipo"
                />
                <CampoTexto
                    nombre="fecha_prevista"
                    etiqueta="Fecha prevista"
                    tipo="date"
                    :valor-inicial="prueba?.fecha_prevista"
                    :error="errors.fecha_prevista"
                    requerido
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Servicios cubiertos"
                ayuda="Sólo activos de tipo «Servicios»: el impacto de un servidor o un disco lo hereda el servicio que depende de ellos."
            >
                <CampoCasillas
                    v-model="serviciosSeleccionados"
                    nombre="servicios"
                    etiqueta="Servicios"
                    :opciones="servicios"
                    :error="errors.servicios"
                    requerido
                    desplazable
                    vacio="Todavía no hay servicios en el inventario a los que apuntar."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Responsable" plegable>
                <CampoSelect
                    nombre="responsable_id"
                    etiqueta="Responsable"
                    :opciones="conOpcionVacia(responsables, 'Sin responsable')"
                    :valor-inicial="prueba?.responsable_id ? String(prueba.responsable_id) : undefined"
                    :error="errors.responsable_id"
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
