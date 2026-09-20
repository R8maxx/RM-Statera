<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Las siete entradas de la cláusula 9.3.2, en el orden en que la norma las
 * enumera.
 *
 * **El orden es el de la norma y no el que quedaría mejor.** Un auditor recorre
 * la 9.3.2 de la a) a la g) con el acta delante; reordenarlas le obliga a buscar
 * cada una.
 *
 * **Un cero es una entrada recogida, no una entrada que falte.** Una organización
 * puede llegar a su primera revisión sin auditorías en el periodo, y eso es lo
 * que la revisión tiene que decir. Por eso aquí no se esconde ninguna cifra a
 * cero, al revés que en la tira de indicadores del panel.
 *
 * Las entradas llegan como el árbol que se congela en la instantánea, así que el
 * tipo es abierto a propósito: lo que este componente pinta y lo que el acta
 * imprime son literalmente la misma estructura, y fijarla aquí con una interfaz
 * obligaría a mantenerla en dos sitios.
 */
interface Entradas {
    [clave: string]: Record<string, unknown> | undefined;
}

const props = defineProps<{ entradas: Entradas }>();

/** Lo que haya bajo una clave, siempre como objeto: la instantánea puede venir a medias. */
function bloque(clave: string): Record<string, any> {
    const valor = props.entradas[clave];

    return valor && typeof valor === 'object' ? (valor as Record<string, any>) : {};
}

function lista(origen: Record<string, any>, clave: string): Record<string, any>[] {
    const valor = origen[clave];

    return Array.isArray(valor) ? valor : [];
}

const previas = computed(() => bloque('accionesPrevias'));
const contexto = computed(() => bloque('contexto'));
const partes = computed(() => bloque('partesInteresadas'));
const desempeno = computed(() => bloque('desempeno'));
const riesgos = computed(() => bloque('riesgos'));
const mejoras = computed(() => bloque('mejoras'));

const auditorias = computed(() => lista(desempeno.value.auditorias ?? {}, 'detalle'));
const objetivos = computed(() => lista(desempeno.value.objetivos ?? {}, 'detalle'));
</script>

<template>
    <div class="space-y-8">
        <!-- a) -->
        <section class="space-y-2">
            <h3 class="text-sm font-semibold">a) Acciones de revisiones previas</h3>

            <p v-if="!previas.revision" class="text-sm text-muted-foreground">
                Es la primera revisión registrada: no hay acciones previas que comprobar.
            </p>
            <template v-else>
                <p class="text-sm text-muted-foreground">
                    De la {{ previas.revision.codigo }}, celebrada el {{ previas.revision.fecha }}:
                    <Cifra class="font-medium text-foreground" :valor="previas.abiertas ?? 0" />
                    de {{ lista(previas, 'acciones').length }} siguen abiertas.
                </p>
                <ul class="divide-y divide-border">
                    <li
                        v-for="(accion, i) in lista(previas, 'acciones')"
                        :key="i"
                        class="flex flex-wrap items-center gap-2 py-2 text-sm"
                    >
                        <CeldaBadge
                            :valor="{
                                valor: accion.estado,
                                etiqueta: accion.estado,
                                tono: accion.tono,
                                icono: null,
                            }"
                        />
                        <span>{{ accion.titulo }}</span>
                        <span v-if="accion.responsable" class="text-xs text-muted-foreground">
                            {{ accion.responsable }}
                        </span>
                    </li>
                </ul>
            </template>
        </section>

        <!-- b) -->
        <section class="space-y-2">
            <h3 class="text-sm font-semibold">b) Cambios en las cuestiones internas y externas</h3>

            <p v-if="!contexto.analisis" class="text-sm text-muted-foreground">
                No hay ningún análisis del contexto aprobado. Esta entrada se aporta fuera de la
                herramienta, o se aprueba el análisis en
                <Link href="/contexto" class="underline underline-offset-4">Contexto</Link>.
            </p>
            <template v-else>
                <p class="text-sm text-muted-foreground">
                    {{ contexto.analisis.etiqueta }}, aprobado el {{ contexto.analisis.fecha }}, con
                    <Cifra class="font-medium text-foreground" :valor="contexto.cuestiones ?? 0" />
                    cuestiones vigentes.
                </p>
                <p v-if="contexto.clima" class="text-sm text-muted-foreground">
                    El cambio climático se ha determinado
                    {{ contexto.clima.pertinente ? 'pertinente' : 'no pertinente' }} para la
                    organización.
                </p>
            </template>
        </section>

        <!-- c) y e) -->
        <section class="space-y-2">
            <h3 class="text-sm font-semibold">
                c) y e) Partes interesadas: necesidades y retroalimentación
            </h3>

            <EstadoVacio
                v-if="lista(partes, 'partes').length === 0"
                titulo="Sin partes interesadas"
                descripcion="La cláusula 4.2 pide determinarlas, y la 9.3 revisar si han cambiado."
            />
            <ul v-else class="divide-y divide-border">
                <li
                    v-for="(parte, i) in lista(partes, 'partes')"
                    :key="i"
                    class="flex flex-wrap items-baseline gap-2 py-2 text-sm"
                >
                    <span class="font-medium">{{ parte.nombre }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ parte.tipo }} · {{ parte.ambito }} · {{ parte.requisitos }}
                        {{ parte.requisitos === 1 ? 'requisito' : 'requisitos' }}
                    </span>
                </li>
            </ul>

            <!--
                La limitación se dice aquí y no sólo en el PDF: quien prepara la
                reunión tiene que saber que esta entrada la aporta él.
            -->
            <p class="text-sm text-muted-foreground">
                La retroalimentación —quejas, encuestas, comunicaciones recibidas— no se registra en
                Statera y se aporta fuera de aquí.
            </p>
        </section>

        <!-- d) -->
        <section class="space-y-3">
            <h3 class="text-sm font-semibold">d) Desempeño y eficacia del sistema de gestión</h3>

            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-muted-foreground">No conformidades abiertas</dt>
                    <dd class="text-lg font-semibold">
                        <Cifra :valor="desempeno.noConformidades?.abiertas ?? 0" />
                        <span class="text-sm font-normal text-muted-foreground">
                            de {{ desempeno.noConformidades?.total ?? 0 }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Sin verificar la eficacia</dt>
                    <dd class="text-lg font-semibold">
                        <Cifra :valor="desempeno.noConformidades?.sinVerificar ?? 0" />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Indicadores fuera de objetivo</dt>
                    <dd class="text-lg font-semibold">
                        <Cifra :valor="desempeno.indicadores?.fueraDeObjetivo ?? 0" />
                        <span class="text-sm font-normal text-muted-foreground">
                            de {{ desempeno.indicadores?.activos ?? 0 }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Con el periodo sin medir</dt>
                    <dd class="text-lg font-semibold">
                        <Cifra :valor="desempeno.indicadores?.periodoSinMedir ?? 0" />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Auditorías en el periodo</dt>
                    <dd class="text-lg font-semibold">
                        <Cifra :valor="desempeno.auditorias?.total ?? 0" />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Objetivos en curso</dt>
                    <dd class="text-lg font-semibold">
                        <Cifra :valor="desempeno.objetivos?.vivos ?? 0" />
                        <span class="text-sm font-normal text-muted-foreground">
                            de {{ desempeno.objetivos?.total ?? 0 }}
                        </span>
                    </dd>
                </div>
            </dl>

            <ul v-if="auditorias.length > 0" class="divide-y divide-border">
                <li
                    v-for="(auditoria, i) in auditorias"
                    :key="i"
                    class="flex flex-wrap items-center gap-2 py-2 text-sm"
                >
                    <span class="cifra">{{ auditoria.codigo }}</span>
                    <CeldaBadge
                        :valor="{
                            valor: auditoria.estado,
                            etiqueta: auditoria.estado,
                            tono: auditoria.tono,
                            icono: null,
                        }"
                    />
                    <span class="text-xs text-muted-foreground">
                        {{ auditoria.tipo }} · {{ auditoria.fecha }} · {{ auditoria.hallazgos }}
                        {{ auditoria.hallazgos === 1 ? 'hallazgo' : 'hallazgos' }}
                    </span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No se celebró ninguna auditoría dentro del periodo revisado.
            </p>

            <ul v-if="objetivos.length > 0" class="divide-y divide-border">
                <li
                    v-for="(objetivo, i) in objetivos"
                    :key="i"
                    class="flex flex-wrap items-center gap-2 py-2 text-sm"
                >
                    <span class="cifra">{{ objetivo.codigo }}</span>
                    <CeldaBadge
                        :valor="{
                            valor: objetivo.estado,
                            etiqueta: objetivo.estado,
                            tono: objetivo.tono,
                            icono: null,
                        }"
                    />
                    <span>{{ objetivo.titulo }}</span>
                    <span class="text-xs text-muted-foreground">{{ objetivo.avance }}</span>
                </li>
            </ul>
        </section>

        <!-- f) -->
        <section class="space-y-2">
            <h3 class="text-sm font-semibold">f) Apreciación de riesgos y estado del tratamiento</h3>

            <p class="text-sm text-muted-foreground">
                <Cifra class="font-medium text-foreground" :valor="riesgos.total ?? 0" />
                riesgos registrados ·
                <Cifra class="font-medium text-foreground" :valor="riesgos.sobreUmbral ?? 0" />
                sobre el umbral ·
                <Cifra class="font-medium text-foreground" :valor="riesgos.sinAceptar ?? 0" />
                sin aceptar ·
                <Cifra class="font-medium text-foreground" :valor="riesgos.revisionVencida ?? 0" />
                con la reevaluación vencida
            </p>
        </section>

        <!-- g) -->
        <section class="space-y-2">
            <h3 class="text-sm font-semibold">g) Oportunidades de mejora continua</h3>

            <p class="text-sm text-muted-foreground">
                <Cifra class="font-medium text-foreground" :valor="mejoras.total ?? 0" />
                registradas ·
                <Cifra class="font-medium text-foreground" :valor="mejoras.abiertas ?? 0" />
                abiertas ·
                <Cifra class="font-medium text-foreground" :valor="mejoras.sinEmpezar ?? 0" />
                sin empezar
            </p>

            <ul v-if="lista(mejoras, 'detalle').length > 0" class="divide-y divide-border">
                <li
                    v-for="(mejora, i) in lista(mejoras, 'detalle')"
                    :key="i"
                    class="flex flex-wrap items-center gap-2 py-2 text-sm"
                >
                    <span class="cifra">{{ mejora.codigo }}</span>
                    <CeldaBadge
                        :valor="{
                            valor: mejora.estado,
                            etiqueta: mejora.estado,
                            tono: mejora.tono,
                            icono: null,
                        }"
                    />
                    <span>{{ mejora.titulo }}</span>
                    <span class="text-xs text-muted-foreground">{{ mejora.origen }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
