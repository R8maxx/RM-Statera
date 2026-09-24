<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import TextoResaltado from '@/components/tabla/celdas/TextoResaltado.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { PencilIcon, SearchIcon, XIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

interface SeccionDeTipo {
    clave: string;
    etiqueta: string;
    contenido: string;
    personalizada: boolean;
}

interface TipoPlantilla {
    valor: string;
    etiqueta: string;
    familia: 'calculado' | 'redactado';
    secciones: SeccionDeTipo[];
    personalizadas: number;
    documentos: number;
    retoque: { en: string; por: string | null } | null;
}

const props = defineProps<{ tipos: TipoPlantilla[] }>();

/**
 * La búsqueda entra en los TEXTOS, no sólo en los títulos.
 *
 * Buscar sobre ocho tarjetas no vale el control que ocupa. Lo que de verdad
 * falta aquí son los cincuenta y seis huecos que hay detrás, repartidos por ocho
 * pantallas: «¿dónde escribí aquella frase sobre el alcance?» no se contestaba
 * sin abrirlas una a una.
 *
 * En cliente y no en servidor porque el corpus **está medido**: 10,9 kB de
 * fábrica, el hueco más largo de 671 caracteres. Mismo patrón que el buscador de
 * la ficha de una acción formativa. Si algún día una organización llena los
 * cincuenta y seis huecos hasta el tope, esto se convierte en una consulta.
 */
const busqueda = ref('');

const terminos = computed(() =>
    busqueda.value
        .trim()
        .split(/\s+/)
        .filter((termino) => termino.length > 0),
);

function contiene(texto: string, termino: string): boolean {
    return texto.toLowerCase().includes(termino);
}

/** Las secciones de un tipo que casan con lo buscado. */
function seccionesQueCoinciden(tipo: TipoPlantilla, termino: string): SeccionDeTipo[] {
    return tipo.secciones.filter(
        (seccion) => contiene(seccion.etiqueta, termino) || contiene(seccion.contenido, termino),
    );
}

const resultados = computed(() => {
    const termino = busqueda.value.trim().toLowerCase();

    if (termino === '') {
        return props.tipos.map((tipo) => ({ tipo, coincidencias: [] as SeccionDeTipo[] }));
    }

    return props.tipos
        .map((tipo) => ({ tipo, coincidencias: seccionesQueCoinciden(tipo, termino) }))
        // El nombre del documento también cuenta: quien escribe «política» busca
        // la plantilla, no una frase dentro de ella.
        .filter(({ tipo, coincidencias }) => coincidencias.length > 0 || contiene(tipo.etiqueta, termino));
});

const buscando = computed(() => busqueda.value.trim() !== '');

const totalCoincidencias = computed(() =>
    resultados.value.reduce((suma, { coincidencias }) => suma + coincidencias.length, 0),
);

/**
 * Los dos géneros de documento del producto, y la pantalla los mezclaba.
 *
 * Una Declaración de Aplicabilidad es una consulta congelada en un PDF y una
 * política no sale de ninguna consulta: `TipoDocumento::esRedactado()` ya lo
 * distingue en el dominio desde hace tiempo y aquí eran ocho tarjetas iguales.
 */
const familias = [
    {
        clave: 'calculado' as const,
        titulo: 'Documentos calculados',
        ayuda: 'Las tablas y las cifras salen de las implantaciones; aquí se escribe lo que las envuelve.',
    },
    {
        clave: 'redactado' as const,
        titulo: 'Documentos redactados',
        ayuda: 'No salen de ninguna consulta: el texto es el documento.',
    },
];

function deLaFamilia(familia: 'calculado' | 'redactado') {
    return resultados.value.filter(({ tipo }) => tipo.familia === familia);
}

/**
 * Un trozo de texto alrededor de lo buscado.
 *
 * Enseñar el hueco entero convertiría cada tarjeta en un muro; enseñar sólo el
 * nombre de la sección no dice si es la frase que se buscaba.
 */
function extracto(contenido: string, termino: string): string {
    const posicion = contenido.toLowerCase().indexOf(termino.toLowerCase());

    if (posicion === -1) {
        return contenido.slice(0, 120) + (contenido.length > 120 ? '…' : '');
    }

    const desde = Math.max(0, posicion - 45);
    const hasta = Math.min(contenido.length, posicion + termino.length + 75);

    return (desde > 0 ? '…' : '') + contenido.slice(desde, hasta).trim() + (hasta < contenido.length ? '…' : '');
}

const formatoFecha = new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'short', year: 'numeric' });

/** «Última vez el 21 sept 2026, por Ana M.», sin quien no conste. */
function retoque(tipo: TipoPlantilla): string {
    if (tipo.retoque === null) {
        return '';
    }

    const cuando = `Última vez el ${formatoFecha.format(new Date(tipo.retoque.en))}`;

    return tipo.retoque.por === null ? cuando : `${cuando}, por ${tipo.retoque.por}`;
}

function insignia(tipo: TipoPlantilla) {
    return tipo.personalizadas > 0
        ? { valor: 'propio', etiqueta: 'Personalizada', tono: 'exigible' }
        : { valor: 'plantilla', etiqueta: 'De fábrica', tono: 'marco' };
}
</script>

<template>
    <AppLayout titulo="Plantillas de documento">
        <CabeceraPagina
            titulo="Plantillas de documento"
            descripcion="Los textos con los que arrancan los documentos de la organización: la introducción, la metodología y las notas de cada tabla. Lo que no se toque sale con el texto que trae Statera."
        />

        <!--
            Debajo de la cabecera y no en su slot de acciones, que es `shrink-0`
            y no admite un control ancho. Mismo sitio que la barra de filtros del
            tablero y que el buscador de una acción formativa.

            Sin atajo de teclado: `/` y `⌘K` ya son de la paleta de comandos.
        -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-sm">
                <SearchIcon
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="busqueda"
                    type="text"
                    inputmode="search"
                    autocomplete="off"
                    placeholder="Buscar en los textos de las plantillas"
                    aria-label="Buscar en los textos de las plantillas"
                    class="pr-9 pl-9"
                />
                <button
                    v-if="buscando"
                    type="button"
                    class="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Limpiar la búsqueda"
                    @click="busqueda = ''"
                >
                    <XIcon class="size-3.5" />
                </button>
            </div>

            <p v-if="buscando" class="text-sm text-muted-foreground" role="status">
                {{ totalCoincidencias }}
                {{ totalCoincidencias === 1 ? 'coincidencia' : 'coincidencias' }}
                en {{ resultados.length }}
                {{ resultados.length === 1 ? 'plantilla' : 'plantillas' }}
            </p>
        </div>

        <EstadoVacio
            v-if="resultados.length === 0"
            :icono="SearchIcon"
            titulo="Ninguna plantilla dice eso"
            descripcion="Se ha buscado en el nombre de cada documento y en el texto de todos sus huecos."
        />

        <div v-else class="space-y-8">
            <section v-for="familia in familias" :key="familia.clave" v-show="deLaFamilia(familia.clave).length > 0">
                <h2 class="text-sm font-semibold tracking-[-0.01em]">
                    {{ familia.titulo }}
                </h2>
                <p class="mt-1 mb-3 text-sm text-muted-foreground">{{ familia.ayuda }}</p>

                <div class="grid gap-4 md:grid-cols-2">
                    <Card v-for="{ tipo, coincidencias } in deLaFamilia(familia.clave)" :key="tipo.valor">
                        <CardHeader>
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <CardTitle>
                                    <TextoResaltado :texto="tipo.etiqueta" :terminos="terminos" />
                                </CardTitle>
                                <CeldaBadge :valor="insignia(tipo)" />
                            </div>
                        </CardHeader>

                        <CardContent class="space-y-3">
                            <!--
                                Toda cifra con su denominador: «3 textos
                                personalizados» no dice lo mismo sobre 5 que
                                sobre 11.
                            -->
                            <p class="text-sm text-muted-foreground">
                                {{ tipo.personalizadas }} de {{ tipo.secciones.length }} textos personalizados ·
                                {{ tipo.documentos }}
                                {{ tipo.documentos === 1 ? 'documento creado' : 'documentos creados' }}
                            </p>

                            <!-- Dónde está lo que se buscaba, con su contexto. -->
                            <ul v-if="coincidencias.length > 0" class="space-y-2 border-l-2 border-border pl-3">
                                <li v-for="seccion in coincidencias" :key="seccion.clave" class="text-sm">
                                    <p class="font-medium">
                                        <TextoResaltado :texto="seccion.etiqueta" :terminos="terminos" />
                                    </p>
                                    <p v-if="seccion.contenido !== ''" class="text-muted-foreground">
                                        <TextoResaltado
                                            :texto="extracto(seccion.contenido, busqueda.trim())"
                                            :terminos="terminos"
                                        />
                                    </p>
                                    <p v-else class="text-muted-foreground italic">Este hueco está vacío.</p>
                                </li>
                            </ul>

                            <Button as-child variant="outline">
                                <Link :href="`/plantillas-documento/${tipo.valor}`">
                                    <PencilIcon class="size-4" />
                                    Editar los textos
                                </Link>
                            </Button>
                        </CardContent>

                        <!--
                            Quién y cuándo: el modelo lo guarda desde la primera
                            migración y la pantalla no lo enseñaba. En una
                            herramienta de cumplimiento es la primera pregunta.
                        -->
                        <CardFooter v-if="tipo.retoque" class="text-xs text-muted-foreground">
                            {{ retoque(tipo) }}
                        </CardFooter>
                    </Card>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
