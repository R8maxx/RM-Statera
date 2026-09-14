<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSwitch from '@/components/formulario/CampoSwitch.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import MatrizRiesgo from '@/components/riesgo/MatrizRiesgo.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';

/**
 * Con qué mide los riesgos la organización.
 *
 * Los cinco escalones de cada escala son fijos: se editan su etiqueta y su
 * descripción. `EscalaRiesgo` admite de dos a diez, así que ampliarlo más adelante
 * no rompe nada; hoy no hay caso de uso que lo justifique y un editor de listas
 * repetibles por escala sería complejidad sin nadie detrás.
 */

interface Escalon {
    valor: number;
    etiqueta: string;
    descripcion: string | null;
}

interface Banda {
    desde: number;
    hasta: number;
    etiqueta: string;
    tono: string;
    icono: string;
}

defineProps<{
    metodologia: {
        nombre: string;
        referencia: string | null;
        escala_probabilidad: Escalon[];
        escala_impacto: Escalon[];
        umbral_aceptacion: number;
        umbral_critico: number;
        periodicidad_revision_meses: number;
        notas: string | null;
    };
    esDeFabrica: boolean;
    estaAprobada: boolean;
    aprobadaPor: string | null;
    aprobadaEn: string | null;
    bandas: Record<string, Banda>;
    riesgoMaximo: number;
}>();

const fecha = (valor: string | null): string => (valor ? formatoFecha.format(new Date(valor)) : '—');
</script>

<template>
    <AppLayout titulo="Metodología de riesgos">
        <CabeceraPagina
            titulo="Metodología de análisis de riesgos"
            descripcion="Las dos escalas con las que se mide y las dos líneas que deciden qué hay que tratar y qué es inasumible. ISO 27001 pide que los fije la organización y que la dirección los apruebe."
        />

        <div class="space-y-6">
            <!--
                Los tres avisos que hay que leer ANTES de tocar un campo. En bloque
                y no como toast: se quedan a la vista mientras se decide.
            -->
            <Aviso v-if="esDeFabrica" tono="info" titulo="Ésta es la metodología de partida de Statera">
                Tu organización no ha guardado ninguna, así que se está usando la de fábrica y
                <strong>se irá actualizando con el producto</strong>. En cuanto guardes algo distinto,
                deja de aplicarse y pasa a mandar la tuya. Guardar sin cambiar nada no crea ninguna fila:
                «no lo he tocado» y «no hay fila» son lo mismo.
            </Aviso>

            <Aviso v-else-if="!estaAprobada" tono="info" titulo="Nadie la ha aprobado todavía">
                La cláusula 6.1.2 de ISO 27001 pide que los criterios de riesgo los establezca la
                organización, y el auditor pide el papel firmado. Mientras no lo esté, los documentos que
                se apoyen en este análisis lo declararán como limitación.
            </Aviso>

            <Aviso tono="info" titulo="Cambiar la metodología no revalúa lo ya medido">
                Cada valoración se lleva dentro la escala con la que se hizo, así que un riesgo medido en
                marzo se sigue leyendo con la escala de marzo. Es lo que hace que el histórico sea
                comparable; si no, un número viejo se quedaría sin unidades. Lo que sí cambia es lo que se
                mida a partir de ahora, y <strong>tocar las escalas o los umbrales invalida la aprobación
                anterior</strong>: la dirección firmó unos números concretos, no un formulario.
            </Aviso>

            <Card v-if="estaAprobada">
                <CardHeader>
                    <CardTitle>Aprobada</CardTitle>
                    <CardDescription>Quién fijó el apetito de riesgo de la organización, y cuándo.</CardDescription>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">Aprobada por</dt>
                            <dd>{{ aprobadaPor ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Fecha</dt>
                            <dd class="cifra">{{ fecha(aprobadaEn) }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Cómo queda repartida la matriz</CardTitle>
                    <CardDescription>
                        Las bandas no son quintiles: salen de los dos umbrales, así que el nivel que se pinta
                        en la tabla y el indicador de «por encima del umbral» no pueden decir cosas
                        distintas sobre el mismo riesgo. El riesgo máximo que permiten estas dos escalas es
                        <span class="cifra">{{ riesgoMaximo }}</span
                        >.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="max-w-md">
                        <MatrizRiesgo
                            :bandas="bandas"
                            :probabilidad="metodologia.escala_probabilidad"
                            :impacto="metodologia.escala_impacto"
                        />
                    </div>
                </CardContent>
            </Card>

            <FormularioRecurso
                titulo="Definir la metodología"
                descripcion="Los escalones son cinco y cinco. Lo que se escribe aquí es cómo los entiende tu organización: si dos personas eligen el mismo escalón ante el mismo caso, el análisis es comparable consigo mismo."
                action="/riesgos/metodologia"
                method="put"
                etiqueta-enviar="Guardar metodología"
                url-cancelar="/riesgos"
                #default="{ errors }"
            >
                <SeccionFormulario titulo="Identificación">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <CampoTexto
                            nombre="nombre"
                            etiqueta="Nombre"
                            :valor-inicial="metodologia.nombre"
                            :error="errors.nombre"
                            requerido
                        />
                        <CampoTexto
                            nombre="referencia"
                            etiqueta="Referencia"
                            :valor-inicial="metodologia.referencia ?? ''"
                            :error="errors.referencia"
                            placeholder="MAGERIT v3, ISO/IEC 27005:2022"
                            ayuda="En qué se basa. El auditor lo pregunta y es más corto contestarlo aquí."
                        />
                    </div>
                </SeccionFormulario>

                <SeccionFormulario
                    titulo="Escala de probabilidad"
                    ayuda="Cada cuánto cabe esperar que ocurra. Describirlo con frecuencias concretas —«una vez al año», «varias veces al año»— es lo que hace que dos personas elijan lo mismo."
                >
                    <div v-for="(escalon, i) in metodologia.escala_probabilidad" :key="escalon.valor" class="flex gap-3">
                        <span
                            class="cifra mt-2 flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs"
                            aria-hidden="true"
                        >
                            {{ escalon.valor }}
                        </span>

                        <!--
                            El número viaja aparte: el usuario no lo edita —la escala
                            es contigua de 1 a n y `EscalaRiesgo` lo exige— pero el
                            servidor lo necesita para reconstruirla.
                        -->
                        <input type="hidden" :name="`escala_probabilidad[${i}][valor]`" :value="escalon.valor" />

                        <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-[12rem_1fr]">
                            <CampoTexto
                                :nombre="`escala_probabilidad[${i}][etiqueta]`"
                                :etiqueta="`Etiqueta del escalón ${escalon.valor} de probabilidad`"
                                etiqueta-oculta
                                :valor-inicial="escalon.etiqueta"
                                :error="errors[`escala_probabilidad.${i}.etiqueta`]"
                                requerido
                            />
                            <CampoTexto
                                :nombre="`escala_probabilidad[${i}][descripcion]`"
                                :etiqueta="`Descripción del escalón ${escalon.valor} de probabilidad`"
                                etiqueta-oculta
                                :valor-inicial="escalon.descripcion ?? ''"
                                :error="errors[`escala_probabilidad.${i}.descripcion`]"
                                placeholder="Cabe esperarlo una vez al año"
                            />
                        </div>
                    </div>
                </SeccionFormulario>

                <SeccionFormulario
                    titulo="Escala de impacto"
                    ayuda="Qué perjuicio causaría. Conviene describirlo en términos de servicio, obligación legal y tiempo de recuperación, que son los tres que usa el Anexo I del ENS para valorar el perjuicio."
                >
                    <div v-for="(escalon, i) in metodologia.escala_impacto" :key="escalon.valor" class="flex gap-3">
                        <span
                            class="cifra mt-2 flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs"
                            aria-hidden="true"
                        >
                            {{ escalon.valor }}
                        </span>

                        <input type="hidden" :name="`escala_impacto[${i}][valor]`" :value="escalon.valor" />

                        <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-[12rem_1fr]">
                            <CampoTexto
                                :nombre="`escala_impacto[${i}][etiqueta]`"
                                :etiqueta="`Etiqueta del escalón ${escalon.valor} de impacto`"
                                etiqueta-oculta
                                :valor-inicial="escalon.etiqueta"
                                :error="errors[`escala_impacto.${i}.etiqueta`]"
                                requerido
                            />
                            <CampoTexto
                                :nombre="`escala_impacto[${i}][descripcion]`"
                                :etiqueta="`Descripción del escalón ${escalon.valor} de impacto`"
                                etiqueta-oculta
                                :valor-inicial="escalon.descripcion ?? ''"
                                :error="errors[`escala_impacto.${i}.descripcion`]"
                                placeholder="Interrumpe un servicio o expone información interna"
                            />
                        </div>
                    </div>
                </SeccionFormulario>

                <SeccionFormulario
                    titulo="Las dos líneas"
                    ayuda="El apetito de riesgo de la organización. Por encima del de aceptación hay que tratar el riesgo; en el crítico o por encima, la organización ha declarado que no lo asume."
                >
                    <div class="grid gap-5 sm:grid-cols-3">
                        <CampoTexto
                            nombre="umbral_aceptacion"
                            etiqueta="Umbral de aceptación"
                            tipo="number"
                            min="2"
                            step="1"
                            inputmode="numeric"
                            :valor-inicial="metodologia.umbral_aceptacion"
                            :error="errors.umbral_aceptacion"
                            requerido
                        />
                        <CampoTexto
                            nombre="umbral_critico"
                            etiqueta="Umbral crítico"
                            tipo="number"
                            min="2"
                            step="1"
                            inputmode="numeric"
                            :valor-inicial="metodologia.umbral_critico"
                            :error="errors.umbral_critico"
                            requerido
                        />
                        <CampoTexto
                            nombre="periodicidad_revision_meses"
                            etiqueta="Reevaluar cada (meses)"
                            tipo="number"
                            min="1"
                            max="60"
                            step="1"
                            inputmode="numeric"
                            :valor-inicial="metodologia.periodicidad_revision_meses"
                            :error="errors.periodicidad_revision_meses"
                            requerido
                        />
                    </div>

                    <CampoTextarea
                        nombre="notas"
                        etiqueta="Notas"
                        :valor-inicial="metodologia.notas ?? ''"
                        :error="errors.notas"
                        :filas="3"
                        ayuda="Qué se decidió y por qué. Es lo que se enseña cuando alguien pregunta de dónde salen estos números."
                    />
                </SeccionFormulario>

                <SeccionFormulario
                    titulo="Aprobación"
                    ayuda="Firmar es un acto de quien está conectado ahora mismo, por eso no hay un campo donde escribir el nombre del aprobador: permitiría firmar en nombre de otro."
                >
                    <CampoSwitch
                        nombre="aprobar"
                        etiqueta="Aprobar esta metodología en mi nombre"
                        :error="errors.aprobar"
                        ayuda="Queda registrado con tu nombre y la fecha de hoy. Una metodología aprobada se conserva aunque coincida con la de fábrica: la firma es lo que el auditor pide."
                    />
                </SeccionFormulario>
            </FormularioRecurso>
        </div>
    </AppLayout>
</template>
