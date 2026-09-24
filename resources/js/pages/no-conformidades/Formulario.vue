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

interface Incidente {
    id: number;
    codigo: string;
    titulo: string;
    estado: string;
    tono: string;
    icono: string;
    peligrosidad: string;
    fecha: string;
}

interface NoConformidad {
    id: number;
    codigo: string;
    origen: string;
    origenEtiqueta: string;
    hallazgo_id: number | null;
    descripcion: string;
    correccion_inmediata: string | null;
    analisis_causa_raiz: string | null;
    responsable_id: number | null;
    fecha_deteccion: string;
    fecha_prevista: string | null;
}

interface Sugerencia {
    codigo: string;
    origen: string;
    descripcion: string | null;
    fecha_deteccion: string;
}

const props = defineProps<{
    noConformidad: NoConformidad | null;
    hallazgo: Hallazgo | null;
    /** Al abrirla desde un incidente (`?incidente=`); en la edición no llega. */
    incidente?: Incidente | null;
    sugerencia: Sugerencia | null;
    origenes: Opcion[];
    responsables: Opcion[];
    /** En la edición, si una prueba de continuidad o un incidente fijan el origen. */
    origenFijo?: boolean;
}>();

const edicion = props.noConformidad !== null;

/*
 * Lo que viene del hallazgo llega puesto y no se pide otra vez: la descripción,
 * el origen y la fecha de detección son del hecho que se está tratando, y
 * pedirlos de nuevo es cómo se acaba con dos versiones del mismo hallazgo.
 */
const valor = computed(() => ({
    codigo: props.noConformidad?.codigo ?? props.sugerencia?.codigo ?? '',
    origen: props.noConformidad?.origen ?? props.sugerencia?.origen ?? 'propia',
    descripcion: props.noConformidad?.descripcion ?? props.sugerencia?.descripcion ?? '',
    fechaDeteccion: props.noConformidad?.fecha_deteccion ?? props.sugerencia?.fecha_deteccion ?? '',
}));

/*
 * Con hallazgo detrás el origen no se elige: la base lo impone —sólo el origen
 * `auditoria` admite hallazgo— y ofrecerlo sería dejar elegir algo que se va a
 * rechazar después. Viaja igual, en un campo oculto. Lo mismo al abrirla desde
 * un incidente, y en la edición de una no conformidad nacida de una prueba de
 * continuidad o de un incidente, que es lo que dice `origenFijo`.
 *
 * **El incidente viaja en su propio campo oculto, como el hallazgo.** Faltaba
 * desde el § 4.10: el controlador mandaba el incidente y rellenaba el origen,
 * pero el formulario no enviaba `incidente_id`, y la no conformidad se guardaba
 * con origen `incidente` sin decir de cuál —y el incidente seguía ofreciendo
 * abrir otra—.
 */
const origenFijo = computed(
    () => props.hallazgo !== null || (props.incidente ?? null) !== null || props.origenFijo === true,
);
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar no conformidad' : 'Nueva no conformidad'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${noConformidad?.codigo}` : 'Nueva no conformidad'"
            descripcion="La cláusula 10.2 pide cuatro cosas: reaccionar, analizar la causa, corregir y comprobar que funcionó."
            :action="edicion ? `/no-conformidades/${noConformidad?.id}` : '/no-conformidades'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Abrir no conformidad'"
            :url-cancelar="edicion ? `/no-conformidades/${noConformidad?.id}` : '/no-conformidades'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué ha fallado">
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

                <div
                    v-else-if="incidente"
                    class="space-y-2 rounded-xl border border-border bg-muted/40 p-4"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <CeldaBadge
                            :valor="{
                                valor: incidente.estado,
                                etiqueta: incidente.estado,
                                tono: incidente.tono,
                                icono: incidente.icono,
                            }"
                        />
                        <span class="cifra text-xs text-muted-foreground">{{ incidente.codigo }}</span>
                        <span class="cifra text-xs text-muted-foreground">{{ incidente.fecha }}</span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ incidente.titulo }} · peligrosidad {{ incidente.peligrosidad.toLowerCase() }}
                    </p>
                    <input type="hidden" name="incidente_id" :value="incidente.id" />
                    <input type="hidden" name="origen" :value="valor.origen" />
                </div>

                <CampoTexto
                    nombre="codigo"
                    etiqueta="Código"
                    :valor-inicial="valor.codigo"
                    :error="errors.codigo"
                    requerido
                    autofocus
                    ayuda="Único dentro de la organización. Se propone el siguiente del año."
                />

                <div v-if="origenFijo && !hallazgo && !incidente" class="space-y-1">
                    <p class="text-sm font-medium">Origen</p>
                    <p class="text-sm text-muted-foreground">
                        {{ noConformidad?.origenEtiqueta }}: lo fija de dónde salió y no se cambia.
                    </p>
                    <input type="hidden" name="origen" :value="valor.origen" />
                </div>

                <CampoSelect
                    v-if="!origenFijo"
                    nombre="origen"
                    etiqueta="Origen"
                    :opciones="origenes"
                    :valor-inicial="valor.origen"
                    :error="errors.origen"
                    requerido
                    ayuda="De dónde sale. Los orígenes cuyo módulo no existe todavía no se ofrecen."
                />

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="3"
                    :valor-inicial="valor.descripcion"
                    :error="errors.descripcion"
                    requerido
                    ayuda="Qué requisito se incumple y en qué se nota."
                />

                <CampoTexto
                    nombre="fecha_deteccion"
                    etiqueta="Fecha de detección"
                    tipo="date"
                    :valor-inicial="valor.fechaDeteccion"
                    :error="errors.fecha_deteccion"
                    requerido
                    ayuda="Cuándo se detectó, que es donde empieza a correr el reloj."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Qué se hace" plegable>
                <CampoTextarea
                    nombre="correccion_inmediata"
                    etiqueta="Corrección inmediata"
                    :filas="3"
                    :valor-inicial="noConformidad?.correccion_inmediata ?? undefined"
                    :error="errors.correccion_inmediata"
                    ayuda="Lo que se hizo el mismo día para contenerlo. No es la acción correctiva: eso ataca la causa y va como tarea."
                />

                <CampoTextarea
                    nombre="analisis_causa_raiz"
                    etiqueta="Análisis de causa raíz"
                    :filas="4"
                    :valor-inicial="noConformidad?.analisis_causa_raiz ?? undefined"
                    :error="errors.analisis_causa_raiz"
                    ayuda="Por qué pasó. Sin esto, la corrección trata el síntoma y vuelve el año que viene."
                />

                <FilaCampos>
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :valor-inicial="noConformidad?.responsable_id ? String(noConformidad.responsable_id) : undefined"
                        :error="errors.responsable_id"
                        ayuda="Quién responde del tratamiento."
                    />

                    <CampoTexto
                        nombre="fecha_prevista"
                        etiqueta="Fecha prevista"
                        tipo="date"
                        :valor-inicial="noConformidad?.fecha_prevista ?? undefined"
                        :error="errors.fecha_prevista"
                        ayuda="Para cuándo se espera tenerla tratada. Sin fecha no vence ni sale en ningún aviso."
                    />
                </FilaCampos>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
