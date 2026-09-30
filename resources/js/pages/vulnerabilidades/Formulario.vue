<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CampoCasillas from '@/components/formulario/CampoCasillas.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible } from '@/lib/celdas';
import { conOpcionVacia, type Opcion } from '@/lib/formularios';
import { Link, useHttp } from '@inertiajs/vue3';
import { SearchIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * Registrar o corregir una vulnerabilidad (invariante 8, A.8.8, `op.exp.4`).
 *
 * **Con CVSS la severidad no se elige**: se calcula con los tramos de FIRST y
 * se enseña en vivo. El campo de severidad sólo sale sin puntuación —un boletín
 * del fabricante, un hallazgo de auditoría—. **El estado no está aquí**: se
 * mueve desde la ficha, que es la que deja el histórico.
 *
 * **Con un CVE, «Traer datos» rellena lo que NVD sabe** y marca si está en el
 * catálogo KEV de CISA (`Fuentes/ConsultarCve`). Rellena sólo lo que está
 * vacío —lo que ya se escribió, o lo que sugirió el aviso de obsolescencia, no
 * se pisa— y dice qué ha tocado y qué no. No guarda nada: se revisa y se
 * registra como siempre. La procedencia —el día de la consulta y la marca de
 * KEV— viaja en dos campos ocultos que **sólo valen para el CVE consultado**:
 * si se cambia el CVE después, se caen.
 */
interface Vulnerabilidad {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    cve: string | null;
    cvss_puntuacion: string | null;
    cvss_vector: string | null;
    cwe: string | null;
    referencias: string[];
    kev_desde: string | null;
    nvd_consultado_el: string | null;
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

interface DatosCve {
    cve: string;
    titulo: string | null;
    descripcion: string | null;
    idioma: 'es' | 'en' | null;
    cvssPuntuacion: string | null;
    cvssVector: string | null;
    cvssVersion: string | null;
    cwe: string | null;
    referencias: string[];
    rechazada: boolean;
    kev: { nombre: string; desde: string; ransomware: boolean } | null;
    kevConsultado: boolean;
    consultadoEl: string;
}

interface RespuestaCve {
    estado: 'encontrado' | 'no_encontrado' | 'no_disponible' | 'desactivada';
    mensaje: string | null;
    datos: DatosCve | null;
    yaRegistrada: { id: number; codigo: string } | null;
}

const edicion = props.vulnerabilidad !== null;

const titulo = ref(props.vulnerabilidad?.titulo ?? props.sugerencia?.titulo ?? '');
const descripcion = ref(props.vulnerabilidad?.descripcion ?? '');
const cve = ref(props.vulnerabilidad?.cve ?? '');
const cvss = ref(props.vulnerabilidad?.cvss_puntuacion ?? '');
const vector = ref(props.vulnerabilidad?.cvss_vector ?? '');
const cwe = ref(props.vulnerabilidad?.cwe ?? '');
const referencias = ref((props.vulnerabilidad?.referencias ?? []).join('\n'));

/* ------------------------------------------------------------ Consulta del CVE */

const consultado = ref<string | null>(props.vulnerabilidad?.nvd_consultado_el ? (props.vulnerabilidad.cve ?? null) : null);
const kevDesde = ref<string | null>(props.vulnerabilidad?.kev_desde ?? null);
const nvdConsultadoEl = ref<string | null>(props.vulnerabilidad?.nvd_consultado_el ?? null);

/** La procedencia sólo vale para el CVE que se consultó. */
const procedenciaVigente = computed(() => consultado.value !== null && cve.value.trim().toUpperCase() === consultado.value);

const consulta = useHttp<{ cve: string; vulnerabilidad_id: number | null }, RespuestaCve>({
    cve: '',
    vulnerabilidad_id: props.vulnerabilidad?.id ?? null,
});
const respuesta = ref<RespuestaCve | null>(null);
const rellenados = ref<string[]>([]);
const respetados = ref<string[]>([]);

function lista(nombres: string[]): string {
    return nombres.length <= 1 ? (nombres[0] ?? '') : `${nombres.slice(0, -1).join(', ')} y ${nombres.at(-1)}`;
}

function rellenar(datos: DatosCve): void {
    const hechos: string[] = [];
    const sinTocar: string[] = [];
    const campos: { nombre: string; campo: typeof titulo; valor: string | null }[] = [
        { nombre: 'título', campo: titulo, valor: datos.titulo },
        { nombre: datos.idioma === 'en' ? 'descripción (en inglés)' : 'descripción', campo: descripcion, valor: datos.descripcion },
        { nombre: 'puntuación CVSS', campo: cvss, valor: datos.cvssPuntuacion },
        { nombre: 'vector CVSS', campo: vector, valor: datos.cvssVector },
        { nombre: 'CWE', campo: cwe, valor: datos.cwe },
        {
            nombre: datos.referencias.length === 1 ? '1 referencia' : `${datos.referencias.length} referencias`,
            campo: referencias,
            valor: datos.referencias.length > 0 ? datos.referencias.join('\n') : null,
        },
    ];

    for (const { nombre, campo, valor } of campos) {
        if (valor === null) {
            continue;
        }

        if (String(campo.value).trim() === '') {
            campo.value = valor;
            hechos.push(nombre);
        } else if (String(campo.value).trim() !== valor) {
            sinTocar.push(nombre);
        }
    }

    rellenados.value = hechos;
    respetados.value = sinTocar;
    cve.value = datos.cve;
    consultado.value = datos.cve;
    kevDesde.value = datos.kev?.desde ?? null;
    nvdConsultadoEl.value = datos.consultadoEl;
}

async function traer(): Promise<void> {
    respuesta.value = null;
    consulta.cve = cve.value.trim();

    try {
        const recibida = await consulta.post('/vulnerabilidades/consulta-cve');
        respuesta.value = recibida;

        if (recibida.estado === 'encontrado' && recibida.datos) {
            rellenar(recibida.datos);
        }
    } catch {
        /* Un 422 queda en `consulta.errors`; un fallo de red lo anuncia el manejador global. */
    }
}

const datosCve = computed(() => (respuesta.value?.estado === 'encontrado' ? respuesta.value.datos : null));
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
            <SeccionFormulario
                titulo="Qué es"
                ayuda="Con el CVE, «Traer datos» rellena lo que NVD sabe de ella y mira si está en el catálogo KEV de CISA. Sólo sale el identificador, y sólo se rellena lo que está vacío."
            >
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1 basis-60">
                        <CampoTexto
                            v-model="cve"
                            nombre="cve"
                            etiqueta="CVE"
                            :error="errors.cve ?? consulta.errors.cve"
                            placeholder="CVE-2024-3094"
                            :autofocus="!edicion"
                            :ayuda="
                                procedenciaVigente && nvdConsultadoEl
                                    ? `Datos de NVD consultados el ${fechaLegible(nvdConsultadoEl)}.`
                                    : 'Si no tiene —un sistema sin soporte, un hallazgo de auditoría—, se deja en blanco.'
                            "
                        />
                    </div>
                    <div class="mt-[1.625rem] flex flex-wrap gap-2">
                        <Button type="button" variant="outline" :disabled="consulta.processing" @click="traer">
                            {{ consulta.processing ? 'Consultando…' : 'Traer datos' }}
                        </Button>
                        <!--
                            Para quien no sabe el código: el buscador de NVD en otra pestaña. La
                            búsqueda la hace su navegador; desde aquí no sale nada. Sin texto
                            precargado: el buscador nuevo de NVD no lo admite por la dirección.
                        -->
                        <Button as-child variant="ghost">
                            <a href="https://nvd.nist.gov/vuln/search" target="_blank" rel="noopener noreferrer">
                                <SearchIcon aria-hidden="true" />
                                Buscar el código
                                <span class="sr-only">en NVD (se abre en otra pestaña)</span>
                            </a>
                        </Button>
                    </div>
                </div>
                <p class="-mt-2 text-xs text-muted-foreground">
                    ¿No lo sabes? Búscalo en NVD por producto y versión —«openssh 8.9», «fortios 7.2»—, copia el CVE-AAAA-NNNN y
                    pulsa «Traer datos». Las que se están explotando están en el
                    <a
                        href="https://www.cisa.gov/known-exploited-vulnerabilities-catalog"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary underline-offset-4 hover:underline"
                        >catálogo KEV de CISA<span class="sr-only"> (se abre en otra pestaña)</span></a
                    >.
                </p>

                <input type="hidden" name="kev_desde" :value="procedenciaVigente ? (kevDesde ?? '') : ''" />
                <input type="hidden" name="nvd_consultado_el" :value="procedenciaVigente ? (nvdConsultadoEl ?? '') : ''" />

                <div v-if="respuesta" class="space-y-3" aria-live="polite">
                    <Aviso v-if="datosCve" tono="exito" :titulo="`Datos de ${datosCve.cve} traídos de NVD`">
                        <template v-if="rellenados.length > 0">Rellenado: {{ lista(rellenados) }}.</template>
                        <template v-else>No había nada vacío que rellenar.</template>
                        <template v-if="respetados.length > 0"> Sin tocar, porque ya tenían otro valor: {{ lista(respetados) }}.</template>
                        <template v-if="datosCve.cvssVersion === '4.0'">
                            NVD sólo la puntúa con CVSS 4.0: se trae el vector, y la puntuación, si la hay, se escribe con la v3.1.
                        </template>
                        <template v-if="datosCve.kevConsultado && !datosCve.kev"> No está en el catálogo KEV de CISA.</template>
                        Revísalo antes de registrar.
                    </Aviso>
                    <Aviso v-if="datosCve?.rechazada" tono="info" titulo="CVE rechazado">{{ respuesta.mensaje }}</Aviso>
                    <Aviso v-if="datosCve?.kev" tono="info" titulo="Se está explotando">
                        Está en el catálogo KEV de CISA desde el {{ fechaLegible(datosCve.kev.desde) }} como «{{ datosCve.kev.nombre }}»<template
                            v-if="datosCve.kev.ransomware"
                            >, y se ha usado en campañas de ransomware</template
                        >. Pesa más que la puntuación al decidir por dónde empezar.
                    </Aviso>
                    <Aviso v-if="datosCve && !datosCve.kevConsultado" tono="info" titulo="Sin comprobar en KEV">
                        El catálogo de CISA no ha contestado. Que no aparezca no quiere decir que no se esté explotando.
                    </Aviso>
                    <Aviso v-if="!datosCve" tono="info" :titulo="respuesta.estado === 'no_encontrado' ? 'No está en NVD' : 'NVD no disponible'">
                        {{ respuesta.mensaje }}
                    </Aviso>
                    <Aviso v-if="respuesta.yaRegistrada" tono="info" titulo="Ya está registrada">
                        Este CVE ya es
                        <Link :href="`/vulnerabilidades/${respuesta.yaRegistrada.id}`" class="font-medium underline underline-offset-4">
                            {{ respuesta.yaRegistrada.codigo }}</Link
                        >. Si afecta a más activos, se añaden allí.
                    </Aviso>
                </div>

                <FilaCampos codigo>
                    <CampoTexto
                        nombre="codigo"
                        etiqueta="Código"
                        :valor-inicial="vulnerabilidad?.codigo ?? sugerencia?.codigo ?? ''"
                        :error="errors.codigo"
                        requerido
                    />
                    <CampoTexto v-model="titulo" nombre="titulo" etiqueta="Título" :error="errors.titulo" requerido :autofocus="edicion" />
                </FilaCampos>

                <CampoTextarea v-model="descripcion" nombre="descripcion" etiqueta="Descripción" :filas="3" :error="errors.descripcion" />

                <CampoTextarea
                    v-model="referencias"
                    nombre="referencias"
                    etiqueta="Referencias"
                    :filas="3"
                    :error="errors.referencias ?? Object.entries(errors).find(([clave]) => clave.startsWith('referencias.'))?.[1]"
                    ayuda="Una dirección por línea: el aviso del fabricante, el parche, el análisis."
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
                        v-model="cvss"
                        nombre="cvss_puntuacion"
                        etiqueta="Puntuación CVSS"
                        :error="errors.cvss_puntuacion"
                        placeholder="De 0 a 10"
                    />
                    <CampoTexto v-model="cwe" nombre="cwe" etiqueta="CWE" :error="errors.cwe" placeholder="CWE-362" />
                </FilaCampos>

                <CampoTexto
                    v-model="vector"
                    nombre="cvss_vector"
                    etiqueta="Vector CVSS"
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
