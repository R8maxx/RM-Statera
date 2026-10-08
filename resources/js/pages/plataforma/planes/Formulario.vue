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
    precioMensualCentimos: number | null;
    descuentoAnual: number;
    contratable: boolean;
}

const props = defineProps<{ plan: Plan | null }>();

const edicion = props.plan !== null;
const activo = ref(props.plan?.activo ?? true);
const contratable = ref(props.plan?.contratable ?? false);

/** Los céntimos, en euros con coma, que es como se escriben y como se leen. */
const precioInicial =
    props.plan?.precioMensualCentimos == null
        ? undefined
        : (props.plan.precioMensualCentimos / 100).toFixed(2).replace('.', ',');
</script>

<template>
    <AppLayout :titulo="edicion ? `Editar ${plan?.nombre}` : 'Crear un plan'">
        <FormularioRecurso
            :titulo="edicion ? `Editar el plan ${plan?.nombre}` : 'Crear un plan'"
            descripcion="Lo que el plan deja hacer y lo que cuesta. El precio se enseña y queda en el histórico, pero todavía no se cobra: no hay pasarela de pago."
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
                titulo="Precio y contratación"
                ayuda="Sin IVA. Un plan contratable lo elige la organización por su cuenta desde su ficha, y el cambio es inmediato. Uno que no lo es —«Ilimitado», por ejemplo— sólo lo asigna la plataforma."
            >
                <FilaCampos>
                    <CampoTexto
                        nombre="precio_mensual"
                        etiqueta="Precio al mes, en euros"
                        :valor-inicial="precioInicial"
                        :error="errors.precio_mensual"
                        inputmode="decimal"
                        class="cifra"
                        placeholder="49,00"
                        :requerido="contratable"
                    />
                    <CampoTexto
                        nombre="descuento_anual"
                        etiqueta="Descuento si se paga el año, en %"
                        tipo="number"
                        :valor-inicial="plan?.descuentoAnual ?? 0"
                        :error="errors.descuento_anual"
                    />
                </FilaCampos>
                <CampoSwitch
                    v-model="contratable"
                    nombre="contratable"
                    etiqueta="La organización puede contratarlo por su cuenta"
                    :error="errors.contratable"
                    ayuda="Apagado, el plan se ve en la página de planes del cliente pero sólo lo asigna la plataforma."
                />
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
