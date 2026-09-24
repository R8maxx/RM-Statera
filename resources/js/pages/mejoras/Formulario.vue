<script setup lang="ts">
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Hallazgo {
    id: number;
    tipo: string;
    tipoEtiqueta: string;
    tipoTono: string;
    tipoIcono: string;
    descripcion: string;
    auditoria: string | null;
    auditoria_id: number | null;
    medida: string | null;
}

interface Mejora {
    id: number;
    codigo: string;
    origen: string;
    hallazgo_id: number | null;
    titulo: string;
    descripcion: string | null;
    beneficio_esperado: string | null;
    responsable_id: number | null;
    fecha_deteccion: string;
    fecha_prevista: string | null;
}

interface Sugerencia {
    codigo: string;
    origen: string;
    titulo: string | null;
    fecha_deteccion: string;
}

const props = defineProps<{
    mejora: Mejora | null;
    hallazgo: Hallazgo | null;
    sugerencia: Sugerencia | null;
    origenes: Opcion[];
    responsables: Opcion[];
}>();

const edicion = props.mejora !== null;

const valor = computed(() => ({
    codigo: props.mejora?.codigo ?? props.sugerencia?.codigo ?? '',
    origen: props.mejora?.origen ?? props.sugerencia?.origen ?? 'propia',
    titulo: props.mejora?.titulo ?? props.sugerencia?.titulo ?? '',
    fechaDeteccion: props.mejora?.fecha_deteccion ?? props.sugerencia?.fecha_deteccion ?? '',
}));

/*
 * Con hallazgo detrás el origen no se elige: la base lo impone —sólo el origen
 * `auditoria` admite hallazgo— y ofrecerlo sería dejar elegir algo que se va a
 * rechazar después. Viaja igual, en un campo oculto.
 */
const origenFijo = computed(() => props.hallazgo !== null);
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar mejora' : 'Nueva oportunidad de mejora'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${mejora?.codigo}` : 'Nueva oportunidad de mejora'"
            descripcion="Lo que se puede hacer mejor sin que nada incumpla. La cláusula 10.1 pide mejorar de forma continua."
            :action="edicion ? `/mejoras/${mejora?.id}` : '/mejoras'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Apuntar mejora'"
            :url-cancelar="edicion ? `/mejoras/${mejora?.id}` : '/mejoras'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué se puede hacer mejor">
                <div
                    v-if="hallazgo"
                    class="space-y-2 rounded-xl border border-border bg-muted/40 p-4"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <CeldaBadge
                            :valor="{
                                valor: hallazgo.tipo,
                                etiqueta: hallazgo.tipoEtiqueta,
                                tono: hallazgo.tipoTono,
                                icono: hallazgo.tipoIcono,
                            }"
                        />
                        <span v-if="hallazgo.auditoria" class="cifra text-xs text-muted-foreground">
                            {{ hallazgo.auditoria }}
                        </span>
                        <span v-if="hallazgo.medida" class="cifra text-xs text-muted-foreground">
                            {{ hallazgo.medida }}
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">{{ hallazgo.descripcion }}</p>
                    <input type="hidden" name="hallazgo_id" :value="hallazgo.id" />
                    <input type="hidden" name="origen" :value="valor.origen" />
                </div>

                <FilaCampos>
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="valor.codigo"
                        :error="errors.codigo"
                        requerido
                        autofocus
                        ayuda="Único dentro de la organización. Se propone el siguiente del año."
                    />

                    <CampoSelect
                        v-if="!origenFijo"
                        nombre="origen"
                        etiqueta="Origen"
                        :opciones="origenes"
                        :valor-inicial="valor.origen"
                        :error="errors.origen"
                        requerido
                        ayuda="De dónde sale. Un indicador que se queda corto no es una no conformidad, y aquí sí tiene sitio."
                    />
                </FilaCampos>

                <CampoTexto
                    nombre="titulo"
                    etiqueta="Mejora"
                    :valor-inicial="valor.titulo"
                    :error="errors.titulo"
                    requerido
                    ayuda="En una línea: «automatizar el inventario de software de los puestos»."
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="3"
                    :valor-inicial="mejora?.descripcion ?? undefined"
                    :error="errors.descripcion"
                    ayuda="Qué se hace hoy y qué se propone cambiar."
                />

                <CampoTexto
                    nombre="fecha_deteccion"
                    etiqueta="Fecha"
                    tipo="date"
                    :valor-inicial="valor.fechaDeteccion"
                    :error="errors.fecha_deteccion"
                    requerido
                    ayuda="Cuándo se apuntó."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Qué se espera conseguir" plegable>
                <CampoTextarea
                    nombre="beneficio_esperado"
                    etiqueta="Beneficio esperado"
                    :filas="3"
                    :valor-inicial="mejora?.beneficio_esperado ?? undefined"
                    :error="errors.beneficio_esperado"
                    ayuda="No es un objetivo de seguridad: aquello es un compromiso firmado con plazo y recursos. Si esta mejora acaba siéndolo, el objetivo se registra aparte."
                />

                <FilaCampos>
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :valor-inicial="mejora?.responsable_id ? String(mejora.responsable_id) : undefined"
                        :error="errors.responsable_id"
                        ayuda="Quién la lleva, si ya se sabe."
                    />

                    <CampoTexto
                        nombre="fecha_prevista"
                        etiqueta="Fecha prevista"
                        tipo="date"
                        :valor-inicial="mejora?.fecha_prevista ?? undefined"
                        :error="errors.fecha_prevista"
                        ayuda="Opcional siempre. Nadie se compromete a una mejora: si la fecha pasa, se señala en gris y no en rojo."
                    />
                </FilaCampos>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
