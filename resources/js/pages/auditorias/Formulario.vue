<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref } from 'vue';

interface OpcionSistema {
    valor: string;
    etiqueta: string;
    marco: string | null;
}

interface OpcionTipo {
    valor: string;
    etiqueta: string;
    descripcion: string;
    admiteEntidad: boolean;
}

interface Auditoria {
    id: number;
    codigo: string;
    sistema_id: number | null;
    tipo: string;
    fecha: string;
    alcance: string | null;
    auditor: string | null;
    entidad_certificadora: string | null;
}

const props = defineProps<{
    auditoria: Auditoria | null;
    sistemas: OpcionSistema[];
    tipos: OpcionTipo[];
}>();

const edicion = props.auditoria !== null;

const tipo = ref(props.auditoria?.tipo ?? props.tipos[0]?.valor ?? 'interna');

/*
 * La entidad certificadora sólo la firma una auditoría externa, y el `CHECK` de
 * la tabla dice lo mismo. Se esconde en vez de dejar que la persona la rellene y
 * se la rechacen después: un campo que no aplica y se ofrece igualmente es una
 * invitación a un error que luego hay que explicar.
 */
const admiteEntidad = computed(
    () => props.tipos.find((t) => t.valor === tipo.value)?.admiteEntidad ?? false,
);

const ayudaTipo = computed(
    () => props.tipos.find((t) => t.valor === tipo.value)?.descripcion ?? '',
);

const opcionesTipo = computed(() => props.tipos.map((t) => ({ valor: t.valor, etiqueta: t.etiqueta })));
const opcionesSistema = computed(() => props.sistemas.map((s) => ({ valor: s.valor, etiqueta: s.etiqueta })));
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar auditoría' : 'Nueva auditoría'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${auditoria?.codigo}` : 'Nueva auditoría'"
            descripcion="La cláusula 9.2 de ISO pide al menos una auditoría interna al año. Para el ENS de categoría básica, la autoevaluación."
            :action="edicion ? `/auditorias/${auditoria?.id}` : '/auditorias'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Registrar auditoría'"
            :url-cancelar="edicion ? `/auditorias/${auditoria?.id}` : '/auditorias'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué se audita">
                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="auditoria?.codigo"
                    :error="errors.codigo"
                    requerido
                    autofocus
                    ayuda="Único dentro de la organización. Por ejemplo, AUD-2026-01."
                />

                <!--
                    El sistema es obligatorio: el SGSI es un sistema, y sin él no
                    hay checklist que generar, que es la mitad del módulo.
                -->
                <CampoSelect
                    nombre="sistema_id"
                    etiqueta="Sistema"
                    :opciones="opcionesSistema"
                    :valor-inicial="auditoria?.sistema_id ? String(auditoria.sistema_id) : undefined"
                    :error="errors.sistema_id"
                    requerido
                    ayuda="De él sale la checklist: una línea por medida exigible."
                />

                <CampoSelect
                    nombre="tipo"
                    etiqueta="Tipo"
                    :opciones="opcionesTipo"
                    :valor-inicial="tipo"
                    :error="errors.tipo"
                    requerido
                    :ayuda="ayudaTipo"
                    @update:model-value="(valor?: string) => (tipo = valor ?? tipo)"
                />

                <CampoTexto
                    nombre="fecha"
                    etiqueta="Fecha"
                    tipo="date"
                    :valor-inicial="auditoria?.fecha"
                    :error="errors.fecha"
                    requerido
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Quién la hace" plegable>
                <CampoTexto
                    nombre="auditor"
                    etiqueta="Auditor"
                    :valor-inicial="auditoria?.auditor ?? undefined"
                    :error="errors.auditor"
                    ayuda="Texto libre: el auditor externo no tiene cuenta en Statera, y el interno puede no tenerla."
                />

                <CampoTexto
                    v-if="admiteEntidad"
                    nombre="entidad_certificadora"
                    etiqueta="Entidad certificadora"
                    :valor-inicial="auditoria?.entidad_certificadora ?? undefined"
                    :error="errors.entidad_certificadora"
                    ayuda="La entidad acreditada por ENAC que firma la certificación."
                />

                <CampoTextarea
                    nombre="alcance"
                    etiqueta="Alcance"
                    :filas="3"
                    :valor-inicial="auditoria?.alcance ?? undefined"
                    :error="errors.alcance"
                    ayuda="Qué se ha muestreado. Lo que no entra en la muestra se marca como tal en la checklist."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
