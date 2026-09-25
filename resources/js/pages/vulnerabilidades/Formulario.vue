<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CampoCasillas from '@/components/formulario/CampoCasillas.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, type Opcion } from '@/lib/formularios';
import { computed, ref } from 'vue';

/**
 * Registrar o corregir una vulnerabilidad (invariante 8, A.8.8, `op.exp.4`).
 *
 * **Con CVSS la severidad no se elige**: se calcula con los tramos de FIRST y
 * se enseña en vivo. El campo de severidad sólo sale sin puntuación —un boletín
 * del fabricante, un hallazgo de auditoría—. **El estado no está aquí**: se
 * mueve desde la ficha, que es la que deja el histórico.
 */
interface Vulnerabilidad {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    cve: string | null;
    cvss_puntuacion: string | null;
    cvss_vector: string | null;
    severidad: string;
    origen: string;
    fecha_deteccion: string;
    activos: string[];
    proveedor_id: number | null;
    riesgo_id: number | null;
    incidente_id: number | null;
    responsable_id: number | null;
    remediacion: string | null;
}

const props = defineProps<{
    vulnerabilidad: Vulnerabilidad | null;
    sugerencia: { codigo: string; activos: string[]; titulo: string | null } | null;
    severidades: Opcion[];
    origenes: Opcion[];
    activos: Opcion[];
    proveedores: Opcion[];
    riesgos: Opcion[];
    incidentes: Opcion[];
    responsables: Opcion[];
    hoy: string;
}>();

const edicion = props.vulnerabilidad !== null;

const cvss = ref(props.vulnerabilidad?.cvss_puntuacion ?? '');
const activosElegidos = ref<string[]>(props.vulnerabilidad?.activos ?? props.sugerencia?.activos ?? []);

/** Los tramos de FIRST (CVSS v3.1, § 5), los mismos que `Severidad::desdeCvss()`. */
const derivada = computed((): string | null => {
    const texto = String(cvss.value).trim().replace(',', '.');

    if (texto === '') {
        return null;
    }

    const puntuacion = Number(texto);

    if (Number.isNaN(puntuacion) || puntuacion < 0 || puntuacion > 10) {
        return null;
    }

    if (puntuacion >= 9) return 'Crítica';
    if (puntuacion >= 7) return 'Alta';
    if (puntuacion >= 4) return 'Media';
    if (puntuacion > 0) return 'Baja';

    return 'Informativa';
});

const hayCvss = computed(() => String(cvss.value).trim() !== '');

const proveedores = computed(() => conOpcionVacia(props.proveedores, 'Ninguno'));
const riesgos = computed(() => conOpcionVacia(props.riesgos, 'Ninguno'));
const incidentes = computed(() => conOpcionVacia(props.incidentes, 'Ninguno'));
const responsables = computed(() => conOpcionVacia(props.responsables, 'Sin responsable'));
</script>

<template>
    <AppLayout :titulo="edicion ? 'Editar vulnerabilidad' : 'Registrar vulnerabilidad'">
        <FormularioRecurso
            :titulo="edicion ? `Editar ${vulnerabilidad?.codigo}` : 'Registrar vulnerabilidad'"
            descripcion="Qué es, dónde está y cuánto pesa. El plazo para arreglarla sale solo de su severidad y de la política de la organización."
            :action="edicion ? `/vulnerabilidades/${vulnerabilidad?.id}` : '/vulnerabilidades'"
            :method="edicion ? 'put' : 'post'"
            :etiqueta-enviar="edicion ? 'Guardar' : 'Registrar'"
            :url-cancelar="edicion ? `/vulnerabilidades/${vulnerabilidad?.id}` : '/vulnerabilidades'"
            #default="{ errors }"
        >
            <SeccionFormulario titulo="Qué es">
                <FilaCampos codigo>
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="vulnerabilidad?.codigo ?? sugerencia?.codigo ?? ''"
                        :error="errors.codigo"
                        requerido
                    />
                    <CampoTexto
                        nombre="titulo"
                        etiqueta="Título"
                        :valor-inicial="vulnerabilidad?.titulo ?? sugerencia?.titulo ?? ''"
                        :error="errors.titulo"
                        requerido
                        autofocus
                    />
                </FilaCampos>

                <CampoTextarea
                    nombre="descripcion"
                    etiqueta="Descripción"
                    :filas="3"
                    :valor-inicial="vulnerabilidad?.descripcion ?? ''"
                    :error="errors.descripcion"
                />

                <FilaCampos>
                    <CampoSelect
                        nombre="origen"
                        etiqueta="Cómo se supo"
                        :opciones="origenes"
                        :valor-inicial="vulnerabilidad?.origen ?? undefined"
                        :error="errors.origen"
                        requerido
                    />
                    <CampoTexto
                        nombre="fecha_deteccion"
                        etiqueta="Detectada el"
                        tipo="date"
                        :valor-inicial="vulnerabilidad?.fecha_deteccion ?? hoy"
                        :error="errors.fecha_deteccion"
                        requerido
                        ayuda="El plazo de remediación cuenta desde aquí."
                    />
                </FilaCampos>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Cuánto pesa"
                ayuda="Con puntuación CVSS la severidad sale sola, con los tramos de la especificación de FIRST. Sin puntuación —un boletín, un hallazgo de auditoría— se declara."
            >
                <FilaCampos>
                    <CampoTexto
                        nombre="cve"
                        etiqueta="CVE"
                        :valor-inicial="vulnerabilidad?.cve ?? ''"
                        :error="errors.cve"
                        placeholder="CVE-2024-3094"
                    />
                    <CampoTexto
                        v-model="cvss"
                        nombre="cvss_puntuacion"
                        etiqueta="Puntuación CVSS"
                        :error="errors.cvss_puntuacion"
                        placeholder="De 0 a 10"
                    />
                </FilaCampos>

                <CampoTexto
                    nombre="cvss_vector"
                    etiqueta="Vector CVSS"
                    :valor-inicial="vulnerabilidad?.cvss_vector ?? ''"
                    :error="errors.cvss_vector"
                    placeholder="CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H"
                />

                <Aviso v-if="derivada" tono="info" :titulo="`Severidad ${derivada.toLowerCase()}`">
                    Sale de la puntuación: 9 o más es crítica, de 7 a 8,9 alta, de 4 a 6,9 media y por debajo de 4 baja.
                </Aviso>

                <CampoSelect
                    v-if="!hayCvss"
                    nombre="severidad"
                    etiqueta="Severidad"
                    :opciones="severidades"
                    :valor-inicial="vulnerabilidad?.severidad ?? undefined"
                    :error="errors.severidad"
                    requerido
                />
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Dónde está"
                ayuda="Los activos afectados. Son también los que deciden qué ve un auditor externo: sólo las de los sistemas que audita."
            >
                <CampoCasillas
                    v-model="activosElegidos"
                    nombre="activos"
                    etiqueta="Activos afectados"
                    :opciones="activos"
                    :error="errors.activos ?? errors['activos.0']"
                    vacio="No hay activos en el inventario."
                    desplazable
                />

                <CampoSelect
                    nombre="proveedor_id"
                    etiqueta="Depende de un proveedor"
                    :opciones="proveedores"
                    :valor-inicial="vulnerabilidad?.proveedor_id ? String(vulnerabilidad.proveedor_id) : undefined"
                    :error="errors.proveedor_id"
                    ayuda="Si el arreglo tiene que llegar de fuera: un parche del fabricante, un cambio del proveedor de nube."
                />
            </SeccionFormulario>

            <SeccionFormulario titulo="Con qué se relaciona y quién la lleva">
                <FilaCampos>
                    <CampoSelect
                        nombre="riesgo_id"
                        etiqueta="Riesgo"
                        :opciones="riesgos"
                        :valor-inicial="vulnerabilidad?.riesgo_id ? String(vulnerabilidad.riesgo_id) : undefined"
                        :error="errors.riesgo_id"
                        ayuda="El escenario del análisis de riesgos que la hace creíble, si lo hay."
                    />
                    <CampoSelect
                        nombre="incidente_id"
                        etiqueta="Incidente"
                        :opciones="incidentes"
                        :valor-inicial="vulnerabilidad?.incidente_id ? String(vulnerabilidad.incidente_id) : undefined"
                        :error="errors.incidente_id"
                        ayuda="Si se descubrió por un incidente, o si llegó a explotarse."
                    />
                </FilaCampos>

                <CampoSelect
                    nombre="responsable_id"
                    etiqueta="Responsable"
                    :opciones="responsables"
                    :valor-inicial="vulnerabilidad?.responsable_id ? String(vulnerabilidad.responsable_id) : undefined"
                    :error="errors.responsable_id"
                />

                <CampoTextarea
                    nombre="remediacion"
                    etiqueta="Cómo se arregla"
                    :filas="3"
                    :valor-inicial="vulnerabilidad?.remediacion ?? ''"
                    :error="errors.remediacion"
                    ayuda="El parche, la versión, el cambio de configuración o la medida que la mitiga."
                />
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
