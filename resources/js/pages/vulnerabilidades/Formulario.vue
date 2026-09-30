<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CampoLista from '@/components/formulario/CampoLista.vue';
import CampoRelacion from '@/components/formulario/CampoRelacion.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoSeleccionMultiple, { type OpcionTipada } from '@/components/formulario/CampoSeleccionMultiple.vue';
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
import { RefreshCwIcon } from '@lucide/vue';
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
 *
 * **«Lo que sale de aquí»** (el `#resumen` del carril) dice antes de registrar
 * lo que el sistema va a derivar: la severidad, los días que da la política de
 * la organización (`plazos`, los mismos que aplica `PlazoRemediacion`) y la
 * fecha límite. Es una previsión: la que vale es la que calcula el servidor.
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
    activos: OpcionTipada[];
    proveedores: Opcion[];
    riesgos: Opcion[];
    incidentes: Opcion[];
    responsables: Opcion[];
    /** Días de remediación por severidad; nulo donde no hay plazo. */
    plazos: Record<string, number | null>;
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
const referencias = ref<string[]>([...(props.vulnerabilidad?.referencias ?? [])]);

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

/*
 * Lo que trajo NVD, por campo, para la marca de procedencia de cada uno
 * (`MarcaProcedencia`): «NVD» mientras siga el valor traído, «editado» cuando
 * alguien lo cambie. Sólo los que se rellenaron: los respetados no son de NVD.
 */
type CampoNvd = 'titulo' | 'descripcion' | 'cvss' | 'vector' | 'cwe' | 'referencias';
const traidos = ref<Partial<Record<CampoNvd, string>>>({});

function textoDe(campo: CampoNvd): string {
    const valores = { titulo, descripcion, cvss, vector, cwe };

    return campo === 'referencias'
        ? referencias.value.map((linea) => linea.trim()).filter(Boolean).join('\n')
        : String(valores[campo].value).trim();
}

/** El chip que lleva cada campo: nada si no lo trajo NVD. */
function procedencia(campo: CampoNvd): { procedencia: string | null; procedenciaEditada: boolean } {
    const traido = procedenciaVigente.value ? traidos.value[campo] : undefined;

    return traido === undefined
        ? { procedencia: null, procedenciaEditada: false }
        : { procedencia: 'NVD', procedenciaEditada: textoDe(campo) !== traido };
}

function lista(nombres: string[]): string {
    return nombres.length <= 1 ? (nombres[0] ?? '') : `${nombres.slice(0, -1).join(', ')} y ${nombres.at(-1)}`;
}

function rellenar(datos: DatosCve): void {
    const hechos: string[] = [];
    const sinTocar: string[] = [];
    const nuevos: Partial<Record<CampoNvd, string>> = {};
    const campos: { clave: CampoNvd; nombre: string; valor: string | null; poner: (valor: string) => void }[] = [
        { clave: 'titulo', nombre: 'título', valor: datos.titulo, poner: (valor) => (titulo.value = valor) },
        {
            clave: 'descripcion',
            nombre: datos.idioma === 'en' ? 'descripción (en inglés)' : 'descripción',
            valor: datos.descripcion,
            poner: (valor) => (descripcion.value = valor),
        },
        { clave: 'cvss', nombre: 'puntuación CVSS', valor: datos.cvssPuntuacion, poner: (valor) => (cvss.value = valor) },
        { clave: 'vector', nombre: 'vector CVSS', valor: datos.cvssVector, poner: (valor) => (vector.value = valor) },
        { clave: 'cwe', nombre: 'CWE', valor: datos.cwe, poner: (valor) => (cwe.value = valor) },
        {
            clave: 'referencias',
            nombre: 'referencias',
            valor: datos.referencias.length > 0 ? datos.referencias.join('\n') : null,
            poner: (valor) => (referencias.value = valor.split('\n')),
        },
    ];

    for (const { clave, nombre, valor, poner } of campos) {
        if (valor === null) {
            continue;
        }

        if (textoDe(clave) === '') {
            poner(valor);
            nuevos[clave] = valor;
            hechos.push(nombre);
        } else if (textoDe(clave) !== valor) {
            sinTocar.push(nombre);
        }
    }

    rellenados.value = hechos;
    respetados.value = sinTocar;
    traidos.value = nuevos;
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
const derivada = computed((): 'critica' | 'alta' | 'media' | 'baja' | 'informativa' | null => {
    const texto = String(cvss.value).trim().replace(',', '.');

    if (texto === '') {
        return null;
    }

    const puntuacion = Number(texto);

    if (Number.isNaN(puntuacion) || puntuacion < 0 || puntuacion > 10) {
        return null;
    }

    if (puntuacion >= 9) return 'critica';
    if (puntuacion >= 7) return 'alta';
    if (puntuacion >= 4) return 'media';
    if (puntuacion > 0) return 'baja';

    return 'informativa';
});

const hayCvss = computed(() => String(cvss.value).trim() !== '');

/* ------------------------------------------------------------ Lo que sale de aquí */

const declarada = ref<string | undefined>(props.vulnerabilidad?.severidad ?? undefined);
const deteccion = ref<string>(props.vulnerabilidad?.fecha_deteccion ?? props.hoy);

/** La severidad que se va a guardar: la derivada si hay CVSS, la declarada si no. */
const severidad = computed(() => (hayCvss.value ? derivada.value : (declarada.value ?? null)));

/** El peso de `Severidad::peso()`, para la escala de cuatro pasos. */
const PESOS: Record<string, number> = { informativa: 0, baja: 1, media: 2, alta: 3, critica: 4 };

const etiquetaSeveridad = computed(
    () => props.severidades.find((opcion) => opcion.valor === severidad.value)?.etiqueta ?? null,
);

const dias = computed(() => (severidad.value ? (props.plazos[severidad.value] ?? null) : null));

const fechaLimite = computed((): string | null => {
    const fecha = /^\d{4}-\d{2}-\d{2}$/.test(deteccion.value) ? new Date(`${deteccion.value}T00:00:00Z`) : null;

    if (fecha === null || Number.isNaN(fecha.getTime()) || dias.value === null) {
        return null;
    }

    fecha.setUTCDate(fecha.getUTCDate() + dias.value);

    return fecha.toISOString().slice(0, 10);
});

const vencida = computed(() => fechaLimite.value !== null && fechaLimite.value < props.hoy);

/** Sólo cuenta la marca de KEV del CVE consultado, como los campos ocultos. */
const enKev = computed(() => (procedenciaVigente.value ? kevDesde.value : null));

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
        >
            <!-- Con `#resumen` al lado, el slot por defecto no puede ir en la
                 etiqueta: Vue sólo lo admite ahí cuando es el único. -->
            <template #default="{ errors }">
                <SeccionFormulario
                    titulo="Qué es"
                    ayuda="Con el CVE, «Traer de NVD» rellena lo que NVD sabe de ella y mira si está en el catálogo KEV de CISA. Sólo sale el identificador, y sólo se rellena lo que está vacío."
                >
                    <CampoTexto
                        v-model="cve"
                        nombre="cve"
                        etiqueta="CVE"
                        :error="errors.cve ?? consulta.errors.cve"
                        placeholder="CVE-2024-3094"
                        class="cifra"
                        :autofocus="!edicion"
                        :ayuda="
                            procedenciaVigente && nvdConsultadoEl
                                ? `Datos de NVD consultados el ${fechaLegible(nvdConsultadoEl)}.`
                                : 'Si no tiene —un sistema sin soporte, un hallazgo de auditoría—, se deja en blanco.'
                        "
                    >
                        <template #accion>
                            <Button type="button" variant="outline" :disabled="consulta.processing" @click="traer">
                                <RefreshCwIcon aria-hidden="true" :class="consulta.processing ? 'animate-spin' : undefined" />
                                {{ consulta.processing ? 'Consultando…' : 'Traer de NVD' }}
                            </Button>
                        </template>
                    </CampoTexto>
                    <!--
                        Para quien no sabe el código: el buscador de NVD en otra pestaña. La
                        búsqueda la hace su navegador; desde aquí no sale nada. Sin texto
                        precargado: el buscador nuevo de NVD no lo admite por la dirección.
                    -->
                    <p class="-mt-2 text-xs text-muted-foreground">
                        ¿No lo sabes?
                        <a
                            href="https://nvd.nist.gov/vuln/search"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-primary underline-offset-4 hover:underline"
                            >Búscalo en NVD<span class="sr-only"> (se abre en otra pestaña)</span></a
                        >
                        por producto y versión —«openssh 8.9», «fortios 7.2»—, copia el CVE-AAAA-NNNN y pulsa «Traer de NVD». Las
                        que se están explotando están en el
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
                            <template v-if="rellenados.length > 0">
                                {{ rellenados.length === 1 ? 'Rellenado 1 campo' : `Rellenados ${rellenados.length} campos` }}, marcados con
                                «NVD» junto a su etiqueta.
                            </template>
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
                        <CampoTexto
                            v-model="titulo"
                            nombre="titulo"
                            etiqueta="Título"
                            :error="errors.titulo"
                            requerido
                            :autofocus="edicion"
                            v-bind="procedencia('titulo')"
                        />
                    </FilaCampos>

                    <CampoTextarea
                        v-model="descripcion"
                        nombre="descripcion"
                        etiqueta="Descripción"
                        :filas="3"
                        :error="errors.descripcion"
                        v-bind="procedencia('descripcion')"
                    />

                    <CampoLista
                        v-model="referencias"
                        nombre="referencias"
                        etiqueta="Referencias"
                        :errores="errors"
                        placeholder="https://"
                        etiqueta-anadir="Añadir dirección"
                        ayuda="El aviso del fabricante, el parche, el análisis."
                        v-bind="procedencia('referencias')"
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
                            v-model="deteccion"
                            nombre="fecha_deteccion"
                            etiqueta="Detectada el"
                            tipo="date"
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
                            class="cifra"
                            v-bind="procedencia('cvss')"
                        />
                        <CampoTexto
                            v-model="cwe"
                            nombre="cwe"
                            etiqueta="CWE"
                            :error="errors.cwe"
                            placeholder="CWE-362"
                            class="cifra"
                            v-bind="procedencia('cwe')"
                        />
                    </FilaCampos>

                    <CampoTexto
                        v-model="vector"
                        nombre="cvss_vector"
                        etiqueta="Vector CVSS"
                        :error="errors.cvss_vector"
                        placeholder="CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H"
                        class="cifra"
                        v-bind="procedencia('vector')"
                    />

                    <CampoSelect
                        v-if="!hayCvss"
                        v-model="declarada"
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
                    <CampoSeleccionMultiple
                        v-model="activosElegidos"
                        nombre="activos"
                        etiqueta="Activos afectados"
                        :opciones="activos"
                        :error="errors.activos ?? errors['activos.0']"
                        vacio="No hay activos en el inventario."
                    />

                    <div class="flex flex-wrap gap-2">
                        <CampoRelacion
                            nombre="proveedor_id"
                            etiqueta="Proveedor"
                            :opciones="proveedores"
                            :valor-inicial="vulnerabilidad?.proveedor_id ? String(vulnerabilidad.proveedor_id) : null"
                            :error="errors.proveedor_id"
                            ayuda="Si el arreglo tiene que llegar de fuera: un parche del fabricante, un cambio del proveedor de nube."
                        />
                    </div>
                </SeccionFormulario>

                <SeccionFormulario titulo="Con qué se relaciona y quién la lleva">
                    <div class="flex flex-wrap gap-2">
                        <CampoRelacion
                            nombre="riesgo_id"
                            etiqueta="Riesgo"
                            :opciones="riesgos"
                            :valor-inicial="vulnerabilidad?.riesgo_id ? String(vulnerabilidad.riesgo_id) : null"
                            :error="errors.riesgo_id"
                            ayuda="El escenario del análisis de riesgos que la hace creíble, si lo hay."
                        />
                        <CampoRelacion
                            nombre="incidente_id"
                            etiqueta="Incidente"
                            :opciones="incidentes"
                            :valor-inicial="vulnerabilidad?.incidente_id ? String(vulnerabilidad.incidente_id) : null"
                            :error="errors.incidente_id"
                            ayuda="Si se descubrió por un incidente, o si llegó a explotarse."
                        />
                    </div>

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
            </template>

            <template #resumen>
                <div class="grid gap-2">
                    <p class="text-xs text-muted-foreground">Severidad</p>
                    <div v-if="severidad" class="flex items-center gap-2.5">
                        <span class="flex gap-0.5" role="img" :aria-label="`${PESOS[severidad]} de 4`">
                            <span
                                v-for="paso in 4"
                                :key="paso"
                                class="h-2 w-6 rounded-full transition-colors"
                                :class="paso <= PESOS[severidad] ? 'bg-primary' : 'bg-muted'"
                            />
                        </span>
                        <span class="text-base font-semibold">{{ etiquetaSeveridad }}</span>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Sale al escribir la puntuación CVSS, o se declara sin ella.</p>
                    <p v-if="severidad && hayCvss" class="text-xs text-muted-foreground">
                        Sale del CVSS <span class="cifra">{{ cvss }}</span>: 9 o más es crítica, de 7 a 8,9 alta, de 4 a 6,9 media y por
                        debajo de 4 baja.
                    </p>
                </div>

                <dl class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-x-3 gap-y-2.5 border-t pt-4 text-sm">
                    <dt class="text-muted-foreground">Plazo</dt>
                    <dd>
                        <template v-if="severidad === 'informativa'">Ninguno: es informativa.</template>
                        <template v-else-if="dias !== null"><span class="cifra">{{ dias }}</span> días, según la política</template>
                        <template v-else>—</template>
                    </dd>

                    <dt class="text-muted-foreground">Vence el</dt>
                    <dd>
                        <template v-if="fechaLimite">
                            <span class="font-medium">{{ fechaLegible(fechaLimite) }}</span>
                            <span v-if="vencida" class="block text-xs text-destructive">Llega ya fuera de plazo.</span>
                        </template>
                        <template v-else>—</template>
                    </dd>

                    <template v-if="enKev">
                        <dt class="text-muted-foreground">Prioridad</dt>
                        <dd>En el catálogo KEV de CISA desde el {{ fechaLegible(enKev) }}: por delante de las no explotadas.</dd>
                    </template>

                    <dt class="text-muted-foreground">Activos</dt>
                    <dd>
                        <template v-if="activosElegidos.length === 0">Ninguno todavía</template>
                        <template v-else>{{ activosElegidos.length === 1 ? '1 afectado' : `${activosElegidos.length} afectados` }}</template>
                    </dd>
                </dl>
            </template>
        </FormularioRecurso>
    </AppLayout>
</template>
