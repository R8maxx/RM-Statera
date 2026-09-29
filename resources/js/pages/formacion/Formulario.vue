<script setup lang="ts">
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { conOpcionVacia, type Opcion } from '@/lib/formularios';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref } from 'vue';

interface Accion {
    id: number;
    codigo: string;
    titulo: string;
    tipo: string;
    fecha: string;
    duracion_horas: string | null;
    contenido: string | null;
    evidencia_id: number | null;
    modalidad: string | null;
    imparte: string | null;
    ponente_persona_id: number | null;
    proveedor_id: number | null;
    ponente_nombre: string | null;
}

const props = defineProps<{
    accion: Accion | null;
    /**
     * Con `?desde=` trae además lo de la sesión que se repite: la
     * concienciación anual se imparte casi tal cual.
     */
    sugerencia: {
        codigo: string;
        fecha: string;
        titulo?: string | null;
        tipo?: string | null;
        duracion_horas?: string | null;
        contenido?: string | null;
        modalidad?: string | null;
        imparte?: string | null;
        ponente_persona_id?: number | null;
        proveedor_id?: number | null;
        ponente_nombre?: string | null;
    } | null;
    tipos: { valor: string; etiqueta: string; medida: string }[];
    evidencias: Opcion[];
    modalidades: Opcion[];
    imparticiones: Opcion[];
    personas: Opcion[];
    proveedores: Opcion[];
}>();

const edicion = props.accion !== null;

const valor = computed(() => ({
    codigo: props.accion?.codigo ?? props.sugerencia?.codigo ?? '',
    fecha: props.accion?.fecha ?? props.sugerencia?.fecha ?? '',
    tipo: props.accion?.tipo ?? props.sugerencia?.tipo ?? 'concienciacion',
    titulo: props.accion?.titulo ?? props.sugerencia?.titulo ?? undefined,
    duracion_horas: props.accion?.duracion_horas ?? props.sugerencia?.duracion_horas ?? undefined,
    contenido: props.accion?.contenido ?? props.sugerencia?.contenido ?? undefined,
}));

const origen = props.accion ?? props.sugerencia;

/*
 * Quién la impartió decide qué campos salen, y con `v-if` y no con `v-show`: lo
 * que no toca no tiene que viajar. El `FormRequest` vacía además lo que no
 * corresponde, porque al cambiar de externa a interna el nombre del formador
 * que ya estaba guardado no viaja y se quedaría en la fila.
 */
const imparte = ref<string | undefined>(origen?.imparte ?? undefined);

const aTexto = (id: number | null | undefined): string | undefined => (id == null ? undefined : String(id));

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
                <FilaCampos codigo>
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
                        :valor-inicial="valor.titulo"
                        :error="errors.titulo"
                        requerido
                    />
                </FilaCampos>

                <FilaCampos :columnas="3">
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
                        :valor-inicial="valor.duracion_horas"
                        :error="errors.duracion_horas"
                    />
                </FilaCampos>

                <CampoTextarea
                    nombre="contenido"
                    etiqueta="Contenido"
                    :filas="4"
                    :valor-inicial="valor.contenido"
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

            <SeccionFormulario
                titulo="Cómo y quién la impartió"
                ayuda="Interna si la dio alguien de la plantilla; externa si fue un formador o una empresa de fuera."
            >
                <FilaCampos>
                    <CampoSelect
                        nombre="modalidad"
                        etiqueta="Modalidad"
                        :opciones="conOpcionVacia(modalidades, 'Sin indicar')"
                        :valor-inicial="origen?.modalidad ?? undefined"
                        :error="errors.modalidad"
                    />

                    <CampoSelect
                        v-model="imparte"
                        nombre="imparte"
                        etiqueta="Impartida por"
                        :opciones="conOpcionVacia(imparticiones, 'Sin indicar')"
                        :error="errors.imparte"
                    />
                </FilaCampos>

                <CampoSelect
                    v-if="imparte === 'interna'"
                    nombre="ponente_persona_id"
                    etiqueta="Persona que la impartió"
                    :opciones="personas"
                    :valor-inicial="aTexto(origen?.ponente_persona_id)"
                    :error="errors.ponente_persona_id"
                    requerido
                    ayuda="Alguien de la plantilla, tenga o no cuenta en Statera."
                />

                <FilaCampos v-else-if="imparte === 'externa'">
                    <CampoTexto
                        nombre="ponente_nombre"
                        etiqueta="Quién la impartió"
                        :valor-inicial="origen?.ponente_nombre ?? undefined"
                        :error="errors.ponente_nombre"
                        ayuda="El formador o la formadora. Si sólo sabes la empresa, basta con elegirla."
                    />

                    <CampoSelect
                        nombre="proveedor_id"
                        etiqueta="Proveedor"
                        :opciones="conOpcionVacia(proveedores, 'Ninguno dado de alta')"
                        :valor-inicial="aTexto(origen?.proveedor_id)"
                        :error="errors.proveedor_id"
                        ayuda="La empresa que la impartió, si ya está en proveedores."
                    />
                </FilaCampos>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
