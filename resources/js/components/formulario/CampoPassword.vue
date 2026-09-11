<script setup lang="ts">
import CampoBase from '@/components/formulario/CampoBase.vue';
import { Input } from '@/components/ui/input';
import { EyeIcon, EyeOffIcon } from '@lucide/vue';
import { computed, ref, useId } from 'vue';

/**
 * Un campo de contraseña que se puede mirar.
 *
 * Dos cosas que un campo de contraseña a secas no da y que son la diferencia
 * entre entrar a la primera y entrar a la tercera:
 *
 * 1. **Revelar el texto.** Con una contraseña larga de gestor, escribirla a
 *    ciegas y fallar es lo normal. El botón la muestra mientras se pulsa la
 *    vista, y el estado se anuncia con `aria-pressed`.
 * 2. **Avisar del bloqueo de mayúsculas.** Es la causa número uno de un fallo
 *    de acceso que nadie entiende, y el navegador no dice nada. Se detecta con
 *    `getModifierState`, que no lee la tecla pulsada: sólo el estado del
 *    bloqueo.
 *
 * El valor nunca se registra ni se emite: se queda en el `<input>`, que es lo
 * que lee el `FormData` del `<Form>` de Inertia.
 */
withDefaults(
    defineProps<{
        nombre: string;
        etiqueta: string;
        error?: string | string[] | null;
        ayuda?: string;
        autocomplete?: 'current-password' | 'new-password';
        autofocus?: boolean;
        requerido?: boolean;
    }>(),
    { autocomplete: 'current-password' },
);

const modelo = defineModel<string | undefined>();

const visible = ref(false);
const mayusculas = ref(false);

const idMayusculas = `mayusculas-${useId()}`;
const descritoExtra = computed(() => (mayusculas.value ? [idMayusculas] : []));

function comprobarMayusculas(evento: KeyboardEvent): void {
    mayusculas.value = evento.getModifierState?.('CapsLock') ?? false;
}
</script>

<template>
    <CampoBase
        :nombre="nombre"
        :etiqueta="etiqueta"
        :error="error"
        :ayuda="ayuda"
        :requerido="requerido"
        :descrito-extra="descritoExtra"
        #default="{ atributos }"
    >
        <div class="relative">
            <Input
                v-model="modelo"
                :name="nombre"
                :type="visible ? 'text' : 'password'"
                :required="requerido"
                :autocomplete="autocomplete"
                :autofocus="autofocus"
                class="pr-10"
                v-bind="atributos"
                @keyup="comprobarMayusculas"
                @keydown="comprobarMayusculas"
                @blur="mayusculas = false"
            />

            <button
                type="button"
                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md text-muted-foreground transition-colors hover:text-foreground"
                :aria-label="visible ? 'Ocultar la contraseña' : 'Mostrar la contraseña'"
                :aria-pressed="visible"
                :aria-controls="atributos.id"
                @click="visible = !visible"
            >
                <EyeOffIcon v-if="visible" class="size-4" />
                <EyeIcon v-else class="size-4" />
            </button>
        </div>

        <p
            v-if="mayusculas"
            :id="idMayusculas"
            class="text-xs font-medium text-estado-en-progreso"
            role="status"
        >
            El bloqueo de mayúsculas está activado.
        </p>
    </CampoBase>
</template>
