<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import { computed } from 'vue';

type ValorEtiquetado = App.Http.Resources.Definicion.ValorEtiquetado;

const props = defineProps<{ valor: ValorEtiquetado | null }>();

/**
 * Los colores se declaran una sola vez en `resources/css/app.css`. Aquí sólo se
 * traduce el tono del dominio a su par de tokens, y lo que no está en la lista
 * cae en el neutro en lugar de inventarse una clase.
 *
 * `punto: null` para lo que no es un estado: el marco de un requisito no tiene
 * grados, y un punto de color delante sugeriría que sí.
 */
const tonos: Record<string, { badge: string; punto: string | null }> = {
    no_iniciado: { badge: 'bg-estado-no-iniciado-suave text-estado-no-iniciado', punto: 'bg-estado-no-iniciado' },
    planificado: { badge: 'bg-estado-planificado-suave text-estado-planificado', punto: 'bg-estado-planificado' },
    en_progreso: { badge: 'bg-estado-en-progreso-suave text-estado-en-progreso', punto: 'bg-estado-en-progreso' },
    implantado: { badge: 'bg-estado-implantado-suave text-estado-implantado', punto: 'bg-estado-implantado' },
    no_aplica: { badge: 'bg-estado-no-aplica-suave text-estado-no-aplica', punto: 'bg-estado-no-aplica' },
    borrador: { badge: 'bg-estado-no-iniciado-suave text-estado-no-iniciado', punto: 'bg-estado-no-iniciado' },
    activo: { badge: 'bg-estado-implantado-suave text-estado-implantado', punto: 'bg-estado-implantado' },
    archivado: { badge: 'bg-estado-no-aplica-suave text-estado-no-aplica', punto: 'bg-estado-no-aplica' },

    /*
     * El ciclo de vida de un activo reutiliza los tokens de estado en vez de
     * traerse colores nuevos: son las mismas cinco etapas leídas de otra manera
     * —planificado, vivo, en obras, apagado— y un sexto color no añadiría
     * información, sólo ruido. `retirado` y `dado_de_baja` comparten tono porque
     * lo que los distingue —si hay constancia del borrado seguro— se dice con
     * `caducada` en la propia etiqueta, no con un matiz de gris.
     */
    en_produccion: { badge: 'bg-estado-implantado-suave text-estado-implantado', punto: 'bg-estado-implantado' },
    en_mantenimiento: { badge: 'bg-estado-en-progreso-suave text-estado-en-progreso', punto: 'bg-estado-en-progreso' },
    retirado: { badge: 'bg-estado-no-aplica-suave text-estado-no-aplica', punto: 'bg-estado-no-aplica' },
    dado_de_baja: { badge: 'bg-estado-no-aplica-suave text-estado-no-aplica', punto: 'bg-estado-no-aplica' },

    /*
     * La categoría del ENS es ordinal —básica < media < alta—, así que sube en
     * énfasis, no cambia de significado. `alta` estaba en rojo `destructive`, y
     * eso mentía: una categoría alta no es un error, es un sistema que exige
     * más. El rojo se reserva para lo que va mal.
     */
    basica: { badge: 'bg-muted text-muted-foreground', punto: 'bg-muted-foreground' },
    media: { badge: 'bg-accent text-accent-foreground', punto: 'bg-primary/70' },
    alta: { badge: 'bg-primary/15 text-primary ring-1 ring-primary/25', punto: 'bg-primary' },

    /* Exigencia: un refuerzo es «aplica, y además esto», y se ve que pesa más. */
    exigible: { badge: 'bg-muted text-foreground', punto: 'bg-muted-foreground' },
    reforzado: { badge: 'bg-accent text-accent-foreground', punto: 'bg-primary' },

    /*
     * Una evidencia caducada es de las pocas cosas del dominio que sí van mal:
     * el requisito que probaba se ha quedado sin prueba y el auditor lo verá.
     * Es el uso que DESIGN.md §3 reserva al rojo, y por eso no lo tiene ningún
     * otro badge.
     */
    caducada: { badge: 'bg-destructive/10 text-destructive', punto: 'bg-destructive' },

    /* Procedencia, no estado: chip neutro y monoespaciado, sin punto. */
    marco: { badge: 'cifra bg-muted text-muted-foreground', punto: null },

    /*
     * Tipología de activos. Familia aparte de los estados y con prefijo propio
     * para que no se mezclen por accidente: un tipo nunca es un estado.
     *
     * Las clases van escritas enteras y no compuestas (`bg-tipo-${x}-suave`)
     * porque Tailwind analiza el fichero como texto: una clase construida en
     * tiempo de ejecución no se genera y el badge sale sin fondo.
     *
     * Sin punto: estos badges llevan el ICONO del tipo, que es lo que carga la
     * identidad cuando el color no basta para separar nueve categorías
     * (DESIGN.md §3: la peor pareja queda en ΔE 5.2).
     */
    'tipo:servicios': { badge: 'bg-tipo-servicios-suave text-tipo-servicios', punto: null },
    'tipo:datos': { badge: 'bg-tipo-datos-suave text-tipo-datos', punto: null },
    'tipo:software': { badge: 'bg-tipo-software-suave text-tipo-software', punto: null },
    'tipo:hardware': { badge: 'bg-tipo-hardware-suave text-tipo-hardware', punto: null },
    'tipo:comunicaciones': { badge: 'bg-tipo-comunicaciones-suave text-tipo-comunicaciones', punto: null },
    'tipo:soportes': { badge: 'bg-tipo-soportes-suave text-tipo-soportes', punto: null },
    'tipo:equipamiento_auxiliar': {
        badge: 'bg-tipo-equipamiento-auxiliar-suave text-tipo-equipamiento-auxiliar',
        punto: null,
    },
    'tipo:instalaciones': { badge: 'bg-tipo-instalaciones-suave text-tipo-instalaciones', punto: null },
    'tipo:personal': { badge: 'bg-tipo-personal-suave text-tipo-personal', punto: null },
};

const tono = computed(
    () => tonos[props.valor?.tono ?? ''] ?? { badge: 'bg-muted text-muted-foreground', punto: 'bg-muted-foreground' },
);
</script>

<template>
    <span
        v-if="valor"
        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-colors duration-200"
        :class="tono.badge"
    >
        <!--
            El punto sí codifica estado: es el respaldo de quien no distingue
            estos verdes de estos ámbares, y la única razón por la que aquí un
            punto de color está justificado.
        -->
        <span v-if="tono.punto" class="size-1.5 rounded-full" :class="tono.punto" aria-hidden="true" />

        <!--
            Y cuando el valor trae icono, va en lugar del punto: es lo que
            separa nueve tipos de activo que el color solo no llega a separar.
        -->
        <IconoTipo v-else-if="valor.icono" :nombre="valor.icono" />

        {{ valor.etiqueta }}
    </span>
    <span v-else class="text-muted-foreground" aria-label="sin valor">—</span>
</template>
