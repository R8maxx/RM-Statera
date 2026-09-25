<script setup lang="ts">
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import Aviso from '@/components/Aviso.vue';
import PiezaDeMarca from '@/components/organizacion/PiezaDeMarca.vue';
import CampoSwitch from '@/components/formulario/CampoSwitch.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref } from 'vue';

interface Organizacion {
    id: number;
    nombre: string;
    razon_social: string | null;
    cif: string | null;
    sector: string | null;
    domicilio: string | null;
    codigo_postal: string | null;
    municipio: string | null;
    provincia: string | null;
    url_base_etiquetas: string | null;
    sujeto_obligado_ens: boolean;
    proveedor_sector_publico: boolean;
    reevaluacion_proveedor_alta_meses: number;
    reevaluacion_proveedor_media_meses: number;
    reevaluacion_proveedor_baja_meses: number;
}

const props = defineProps<{
    organizacion: Organizacion;
    /** Las dos piezas, con su URL servida o nula. Las compone el servidor. */
    marca: { logo: string | null; simbolo: string | null };
    /** Derivado en el servidor: `Organizacion::leAplicaElEns()`. */
    leAplicaElEns: boolean;
    /** A dónde apunta hoy el QR de una etiqueta, con la base que hay guardada. */
    ejemploEtiqueta: string;
}>();

/*
 * Las dos banderas del ENS y la base de las etiquetas llevan estado en el
 * cliente porque su consecuencia se enseña EN VIVO, antes de guardar: cuál de
 * las dos hace que aplique el ENS, y a dónde apuntará la próxima pegatina.
 * El resto de campos no lo necesita y va por `valor-inicial`, leyéndose del
 * `FormData` como en todo el producto.
 */
const sujetoObligado = ref(props.organizacion.sujeto_obligado_ens);
const proveedorPublico = ref(props.organizacion.proveedor_sector_publico);
const baseEtiquetas = ref(props.organizacion.url_base_etiquetas ?? '');

const aplicaElEns = computed(() => sujetoObligado.value || proveedorPublico.value);

/*
 * La misma regla que `GeneradorEtiquetas::contenido()`, para que lo que se ve
 * antes de guardar sea lo que se va a imprimir. Se recorta la barra final por
 * lo mismo que allí, y la cadena vacía cae a la URL de la aplicación — que es
 * exactamente el caso que el `??` de antes no cubría.
 */
const destinoEtiqueta = computed(() => {
    const base = baseEtiquetas.value.trim().replace(/\/+$/, '');

    return base === '' ? props.ejemploEtiqueta : `${base}/activos/1`;
});

const cambioLaBase = computed(
    () => baseEtiquetas.value.trim() !== (props.organizacion.url_base_etiquetas ?? ''),
);
</script>

<template>
    <AppLayout titulo="La organización">
        <FormularioRecurso
            titulo="La organización"
            descripcion="Quién es la organización, dónde está y qué marco le aplica. Lo que se escribe aquí sale impreso en los documentos que se le entregan a un auditor."
            action="/organizacion"
            method="put"
            etiqueta-enviar="Guardar la ficha"
            url-cancelar="/panel"
            #default="{ errors }"
        >
            <SeccionFormulario
                titulo="Identificación"
                ayuda="El nombre comercial es el que ves en la aplicación; la razón social es la que firma. Un documento entregable lo firma una persona jurídica, así que es la razón social la que se imprime en la portada."
            >
                <FilaCampos>
                    <CampoTexto
                        nombre="nombre"
                        etiqueta="Nombre comercial"
                        :valor-inicial="organizacion.nombre"
                        :error="errors.nombre"
                        ayuda="El que aparece en el menú lateral y en los correos."
                        requerido
                    />

                    <CampoTexto
                        nombre="razon_social"
                        etiqueta="Razón social"
                        :valor-inicial="organizacion.razon_social ?? ''"
                        :error="errors.razon_social"
                        placeholder="Talleres Merino y Asociados, S.L."
                        ayuda="Si se deja en blanco, los documentos siguen imprimiendo el nombre comercial."
                    />

                    <CampoTexto
                        nombre="cif"
                        etiqueta="CIF"
                        :valor-inicial="organizacion.cif ?? ''"
                        :error="errors.cif"
                        class="cifra"
                        ayuda="Se guarda en mayúsculas y sin guiones. No se comprueba la letra: un NIE o un identificador extranjero no siguen la misma regla."
                    />

                    <CampoTexto
                        nombre="sector"
                        etiqueta="Sector"
                        :valor-inicial="organizacion.sector ?? ''"
                        :error="errors.sector"
                        placeholder="Servicios digitales"
                    />
                </FilaCampos>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Domicilio fiscal"
                ayuda="El del registro, no el de la oficina donde se trabaja si son distintos. Todavía no se imprime en ningún documento: llega con el membrete."
            >
                <CampoTexto
                    nombre="domicilio"
                    etiqueta="Domicilio"
                    :valor-inicial="organizacion.domicilio ?? ''"
                    :error="errors.domicilio"
                    placeholder="Calle del Tinte, 14"
                />

                <div class="grid gap-5 sm:grid-cols-[8rem_1fr_1fr]">
                    <FilaCampos :columnas="3">
                        <CampoTexto
                            nombre="codigo_postal"
                            etiqueta="Código postal"
                            :valor-inicial="organizacion.codigo_postal ?? ''"
                            :error="errors.codigo_postal"
                            inputmode="numeric"
                            class="cifra"
                        />

                        <CampoTexto
                            nombre="municipio"
                            etiqueta="Municipio"
                            :valor-inicial="organizacion.municipio ?? ''"
                            :error="errors.municipio"
                        />

                        <CampoTexto
                            nombre="provincia"
                            etiqueta="Provincia"
                            :valor-inicial="organizacion.provincia ?? ''"
                            :error="errors.provincia"
                        />
                    </FilaCampos>
                </div>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Marco aplicable"
                ayuda="Por qué le aplica el ENS a esta organización. Son dos motivos distintos y pueden darse los dos: la obligación legal es propia, y la de proveedor se hereda del cliente público."
            >
                <FilaCampos>
                    <CampoSwitch
                        v-model="sujetoObligado"
                        nombre="sujeto_obligado_ens"
                        etiqueta="Es sujeto obligado del ENS"
                        :error="errors.sujeto_obligado_ens"
                        ayuda="Administración pública, o entidad del sector público institucional."
                    />

                    <CampoSwitch
                        v-model="proveedorPublico"
                        nombre="proveedor_sector_publico"
                        etiqueta="Presta servicios al sector público"
                        :error="errors.proveedor_sector_publico"
                        ayuda="El ENS le llega por contrato aunque no sea sujeto obligado."
                    />
                </FilaCampos>

                <!--
                    Derivado y en vivo, sin campo propio: es el primer lector que
                    tiene `leAplicaElEns()`, declarado desde la primera migración
                    y nunca invocado.
                -->
                <Aviso v-if="aplicaElEns" tono="info" titulo="A esta organización le aplica el ENS">
                    La Declaración de Aplicabilidad del ENS lo imprime en portada. Cambiar estas casillas
                    <strong>no recategoriza ningún sistema</strong>: la categoría sale de valorar las cinco
                    dimensiones, y eso se hace sistema a sistema.
                </Aviso>

                <Aviso v-else tono="info" titulo="Sólo ISO 27001">
                    Sin ninguno de los dos motivos, al ENS no se le da cobertura legal aquí. Los sistemas del
                    ENS que ya existan siguen funcionando: esto declara el porqué, no lo que se exige.
                </Aviso>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Reevaluación de proveedores"
                ayuda="Cada cuántos meses se vuelve a comprobar el contrato de un proveedor, según su criticidad. Ni ISO 27001 ni el ENS fijan el plazo: es una decisión de la organización, y de aquí sale la fecha que avisa el calendario."
            >
                <FilaCampos>
                    <CampoTexto
                        nombre="reevaluacion_proveedor_alta_meses"
                        etiqueta="Criticidad alta"
                        tipo="number"
                        :valor-inicial="String(organizacion.reevaluacion_proveedor_alta_meses)"
                        :error="errors.reevaluacion_proveedor_alta_meses"
                        ayuda="En meses."
                        requerido
                    />
                    <CampoTexto
                        nombre="reevaluacion_proveedor_media_meses"
                        etiqueta="Criticidad media"
                        tipo="number"
                        :valor-inicial="String(organizacion.reevaluacion_proveedor_media_meses)"
                        :error="errors.reevaluacion_proveedor_media_meses"
                        ayuda="En meses."
                        requerido
                    />
                    <CampoTexto
                        nombre="reevaluacion_proveedor_baja_meses"
                        etiqueta="Criticidad baja"
                        tipo="number"
                        :valor-inicial="String(organizacion.reevaluacion_proveedor_baja_meses)"
                        :error="errors.reevaluacion_proveedor_baja_meses"
                        ayuda="En meses."
                        requerido
                    />
                </FilaCampos>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Marca"
                ayuda="El logo de tu organización, en su documentación y en el panel lateral. Statera se queda donde está: esto acompaña a la herramienta, no la sustituye."
            >
                <FilaCampos>
                    <PiezaDeMarca
                        pieza="logo"
                        etiqueta="Logo horizontal"
                        alto="4rem"
                        :url="marca.logo"
                        ayuda="Va arriba a la derecha en la portada del PDF y en el panel lateral. SVG, PNG, JPG o WebP, hasta 2 MB."
                    />

                    <PiezaDeMarca
                        pieza="simbolo"
                        etiqueta="Símbolo"
                        alto="4rem"
                        :url="marca.simbolo"
                        ayuda="Cuadrado o casi. Va en la cabecera de cada página del PDF, a 8 pt: un logo con el nombre dentro no se lee a ese tamaño."
                    />
                </FilaCampos>

                <Aviso tono="info" titulo="Se usan tal y como llegan">
                    No se recortan, no se recolorean y no se deforman: sólo se escalan. Ten en cuenta que el
                    documento es siempre de fondo claro, así que un logo pensado para fondo oscuro se verá mal
                    y la herramienta no lo va a corregir.
                </Aviso>
            </SeccionFormulario>

            <SeccionFormulario
                titulo="Etiquetas de activos"
                ayuda="A dónde lleva el QR de una pegatina al escanearlo. Es lo único de esta pantalla que ya está impreso en el mundo físico."
            >
                <CampoTexto
                    v-model="baseEtiquetas"
                    nombre="url_base_etiquetas"
                    etiqueta="Dirección base"
                    tipo="url"
                    :error="errors.url_base_etiquetas"
                    placeholder="https://sgsi.ejemplo.es"
                    ayuda="En blanco usa la dirección de la propia aplicación, que es lo correcto mientras no haya un dominio propio."
                />

                <div class="rounded-xl border p-4">
                    <p class="text-sm font-medium">Una etiqueta llevará a</p>
                    <p class="cifra mt-1 text-sm break-all text-muted-foreground">{{ destinoEtiqueta }}</p>
                </div>

                <Aviso v-if="cambioLaBase" tono="error" titulo="Las etiquetas ya impresas no cambian">
                    Las pegatinas que estén puestas en el parque siguen apuntando a la dirección anterior.
                    Cambiar esto obliga a reimprimirlas, o a que la dirección vieja siga respondiendo.
                </Aviso>
            </SeccionFormulario>
        </FormularioRecurso>
    </AppLayout>
</template>
