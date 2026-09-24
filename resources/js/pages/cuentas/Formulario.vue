<script setup lang="ts">
import CampoCasillas from '@/components/formulario/CampoCasillas.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia } from '@/lib/formularios';
import { computed, ref } from 'vue';

/**
 * Invitar una cuenta o cambiar la que hay (§ 4.19).
 *
 * **El nombre y el correo sólo se escriben al invitar.** Después son de la
 * propia cuenta y se cambian desde su perfil: reescribir el correo de otro es
 * quedarse con su cuenta pidiendo una contraseña nueva.
 *
 * **El alcance sale sólo con el rol de auditor**, y con `v-if` y no con
 * `v-show`, al revés que una sección plegada: aquí lo que se quiere es
 * justamente que los campos NO viajen, porque para cualquier otro rol el
 * `FormRequest` los prohíbe.
 */
interface Opcion {
    valor: string;
    etiqueta: string;
}

interface OpcionRol extends Opcion {
    descripcion: string;
}

interface Cuenta {
    id: number;
    nombre: string;
    email: string;
    rol: string | null;
    sistemas: string[];
    accesoHasta: string | null;
    personaId: number | null;
}

const props = defineProps<{
    cuenta: Cuenta | null;
    roles: OpcionRol[];
    sistemas: Opcion[];
    personas: Opcion[];
}>();

const edicion = props.cuenta !== null;

const rol = ref<string | undefined>(props.cuenta?.rol ?? undefined);
const elegido = computed(() => props.roles.find((uno) => uno.valor === rol.value));
const esAuditor = computed(() => rol.value === 'auditor');

const sistemasElegidos = ref<string[]>(props.cuenta?.sistemas ?? []);

const opcionesPersona = computed(() => conOpcionVacia(props.personas, 'Sin enlazar con nadie de la plantilla'));
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar cuenta' : 'Invitar una cuenta'">
        <FormularioRecurso
            :titulo="edicion ? `Editar la cuenta de ${cuenta?.nombre}` : 'Invitar una cuenta'"
            :descripcion="
                edicion
                    ? 'El rol, el alcance y la persona enlazada. El nombre y el correo los cambia la propia cuenta desde su perfil.'
                    : 'Le llegará un correo con un enlace para fijar su contraseña. Nadie más la conoce, tampoco quien invita.'
            "
            :action="edicion ? `/cuentas/${cuenta?.id}` : '/cuentas'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Enviar la invitación'"
            :url-cancelar="edicion ? `/cuentas/${cuenta?.id}` : '/cuentas'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Quién es">
                <FilaCampos v-if="!edicion">
                    <CampoTexto nombre="name" etiqueta="Nombre" :error="errors.name" requerido autofocus />
                    <CampoTexto
                        nombre="email"
                        etiqueta="Correo electrónico"
                        tipo="email"
                        :error="errors.email"
                        requerido
                        ayuda="Es con lo que entrará. Tiene que ser único en todo Statera."
                    />
                </FilaCampos>
                <dl v-else class="grid gap-1 text-sm">
                    <dt class="text-muted-foreground">{{ cuenta?.nombre }}</dt>
                    <dd>{{ cuenta?.email }}</dd>
                </dl>

                <CampoSelect
                    nombre="persona_id"
                    etiqueta="Persona de la plantilla"
                    :opciones="opcionesPersona"
                    :valor-inicial="cuenta?.personaId ? String(cuenta.personaId) : undefined"
                    :error="errors.persona_id"
                    ayuda="Opcional. Una cuenta es quien entra en Statera; una persona es alguien de la plantilla. Un auditor externo tiene lo primero y no lo segundo."
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Rol"
                ayuda="Son papeles distintos y no niveles de un mismo permiso. Sólo hay un rol por cuenta."
            >
                <CampoSelect
                    v-model="rol"
                    nombre="rol"
                    etiqueta="Rol"
                    :opciones="roles"
                    :valor-inicial="cuenta?.rol ?? undefined"
                    :error="errors.rol"
                    :ayuda="elegido?.descripcion"
                    requerido
                />
            </SeccionFormulario>

            <SeccionFormulario
                v-if="esAuditor"
                titulo="Qué audita y hasta cuándo"
                ayuda="El auditor externo sólo ve los sistemas que se auditan y sólo mientras dura la auditoría. Lo que es de toda la organización —la política, el contexto, la revisión por la dirección— lo ve igual, porque lo necesita para auditar cualquier sistema."
            >
                <CampoCasillas
                    v-model="sistemasElegidos"
                    nombre="sistemas"
                    etiqueta="Sistemas"
                    :opciones="sistemas"
                    :error="errors.sistemas ?? errors['sistemas.0']"
                    vacio="No hay sistemas dados de alta."
                    requerido
                />

                <CampoTexto
                    nombre="acceso_hasta"
                    etiqueta="Acceso hasta"
                    tipo="date"
                    :valor-inicial="cuenta?.accesoHasta ?? undefined"
                    :error="errors.acceso_hasta"
                    requerido
                    ayuda="El último día que entra. Pasada la fecha la cuenta caduca sola; no hace falta acordarse de desactivarla."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
