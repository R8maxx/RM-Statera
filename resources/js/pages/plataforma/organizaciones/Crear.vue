<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia } from '@/lib/formularios';
import { computed } from 'vue';

/**
 * Dar de alta una organización cliente (punto 41).
 *
 * Lo mínimo para que el cliente entre: cómo se llama y quién es su primer
 * responsable de seguridad. El resto de la ficha, el sistema y la valoración
 * los hace el propio cliente, porque son suyos: la aplicabilidad se deriva de
 * lo que él valora y no la decide nadie por él.
 */
const props = defineProps<{ planes: { valor: string; etiqueta: string }[] }>();

const opcionesPlan = computed(() => conOpcionVacia(props.planes, 'Sin plan: sin límites y sin vencimiento'));
</script>

<template>
    <AppLayout titulo="Dar de alta una organización">
        <FormularioRecurso
            titulo="Dar de alta una organización"
            descripcion="El responsable de seguridad recibirá un correo para fijar su contraseña. Desde ahí, completa la ficha, da de alta su primer sistema e invita al resto."
            action="/plataforma/organizaciones"
            method="post"
            etiqueta-enviar="Dar de alta"
            url-cancelar="/plataforma/organizaciones"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="La organización"
                ayuda="Cómo se la nombra en la pantalla y, si ya se sabe, cómo firma. El cliente puede corregirlo después desde su ficha."
            >
                <CampoTexto nombre="nombre" etiqueta="Nombre" :error="errors.nombre" requerido autofocus />
                <FilaCampos>
                    <CampoTexto
                        nombre="razon_social"
                        etiqueta="Razón social"
                        :error="errors.razon_social"
                        ayuda="La que firma la Declaración de Aplicabilidad."
                    />
                    <CampoTexto nombre="cif" etiqueta="CIF" :error="errors.cif" />
                </FilaCampos>
                <CampoTexto nombre="sector" etiqueta="Sector" :error="errors.sector" />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Responsable de seguridad"
                ayuda="La primera cuenta del cliente. Es quien invita al resto, así que tiene que ser alguien de la organización y no de la plataforma."
            >
                <FilaCampos>
                    <CampoTexto
                        nombre="responsable_nombre"
                        etiqueta="Nombre"
                        :error="errors.responsable_nombre"
                        requerido
                    />
                    <CampoTexto
                        nombre="responsable_email"
                        etiqueta="Correo electrónico"
                        tipo="email"
                        :error="errors.responsable_email"
                        requerido
                        ayuda="Es con lo que entrará. Tiene que ser único en todo Statera."
                    />
                </FilaCampos>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Suscripción"
                ayuda="Se puede dejar para después. Sin plan, la organización no tiene límites y no vence."
            >
                <FilaCampos>
                    <CampoSelect nombre="plan_id" etiqueta="Plan" :opciones="opcionesPlan" :error="errors.plan_id" />
                    <CampoTexto
                        nombre="vence_en"
                        etiqueta="Vence el"
                        tipo="date"
                        :error="errors.vence_en"
                        ayuda="El último día incluido. En blanco, no vence."
                    />
                </FilaCampos>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
