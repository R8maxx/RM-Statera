<script setup lang="ts">
import CampoSwitch from '@/components/formulario/CampoSwitch.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';

/**
 * Crear o cambiar un plan (punto 43). Un plan no se borra: se retira, y quien
 * ya lo tiene lo conserva.
 */
interface Plan {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    limiteCuentas: number | null;
    limiteSistemas: number | null;
    diasGracia: number;
    activo: boolean;
}

const props = defineProps<{ plan: Plan | null }>();

const edicion = props.plan !== null;
const activo = ref(props.plan?.activo ?? true);
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar ${plan?.nombre}` : 'Crear un plan'">
        <FormularioRecurso
            :titulo="edicion ? `Editar el plan ${plan?.nombre}` : 'Crear un plan'"
            descripcion="Sin precio: el cobro todavía no pasa por Statera. Lo que importa aquí es lo que el plan deja hacer."
            :action="edicion ? `/plataforma/planes/${plan?.id}` : '/plataforma/planes'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Crear el plan'"
            url-cancelar="/plataforma/planes"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="El plan">
                <FilaCampos>
                    <CampoTexto
                        nombre="nombre"
                        etiqueta="Nombre"
                        :valor-inicial="plan?.nombre"
                        :error="errors.nombre"
                        requerido
                        autofocus
                    />
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="plan?.codigo"
                        :error="errors.codigo"
                        requerido
                        ayuda="En minúsculas, con números y guiones: «basica», «media-10»."
                    />
                </FilaCampos>
                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :valor-inicial="plan?.descripcion ?? undefined"
                    :filas="2"
                    :error="errors.descripcion"
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Límites"
                ayuda="En blanco, sin límite. El auditor externo no cuenta como cuenta: se le da acceso para que audite."
            >
                <FilaCampos>
                    <CampoTexto
                        nombre="limite_cuentas"
                        etiqueta="Cuentas"
                        tipo="number"
                        :valor-inicial="plan?.limiteCuentas ?? undefined"
                        :error="errors.limite_cuentas"
                    />
                    <CampoTexto
                        nombre="limite_sistemas"
                        etiqueta="Sistemas"
                        tipo="number"
                        :valor-inicial="plan?.limiteSistemas ?? undefined"
                        :error="errors.limite_sistemas"
                    />
                </FilaCampos>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Vencimiento"
                ayuda="Pasada la fecha de vencimiento, la organización sigue trabajando estos días con un aviso. Después pasa a sólo lectura: ve y descarga todo, pero no cambia nada."
            >
                <CampoTexto
                    nombre="dias_gracia"
                    etiqueta="Días de gracia"
                    tipo="number"
                    :valor-inicial="plan?.diasGracia ?? 15"
                    :error="errors.dias_gracia"
                    requerido
                />
                <CampoSwitch
                    v-model="activo"
                    nombre="activo"
                    etiqueta="Se ofrece a clientes nuevos"
                    :error="errors.activo"
                    ayuda="Apagado, el plan se retira: deja de salir al dar de alta, pero quien ya lo tiene lo conserva."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
