<script setup lang="ts">
import { computed } from 'vue';

/**
 * El reloj de la AEPD dibujado como barra: de la detección al límite de las 72 h
 * y de ahí a lo que lo cierra —la notificación, o ahora si todavía no la hay—.
 *
 * **Tres tramos y un solo rojo.** Lo que pasó dentro de plazo va en el ámbar de
 * `en_progreso`, que es trabajo urgente y no un incumplimiento; lo que queda de
 * plazo, en `muted`; y sólo lo que se pasó del límite va en `destructive`. «En
 * plazo» y «fuera de plazo» no se colapsan: pondría en rojo a quien lo está
 * haciendo bien.
 *
 * **El inicio, cuando no se sabe, se dice.** Un trazo discontinuo delante de la
 * detección: la diferencia entre empezar y detectarse es la primera cifra de un
 * informe de incidente, y una barra que arrancara en la detección la escondería.
 *
 * Las proporciones salen de los instantes del servidor, no del reloj del
 * navegador, para que la barra y las horas que dice el texto no discrepen.
 */
export interface Reloj {
    detectado: string;
    limite: string;
    fin: string;
    finEsNotificacion: boolean;
    horasFueraDePlazo: number | null;
}

const props = defineProps<{ reloj: Reloj; inicioConocido: boolean }>();

const formato = new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

const tramos = computed(() => {
    const detectado = Date.parse(props.reloj.detectado);
    const limite = Date.parse(props.reloj.limite);
    const fin = Date.parse(props.reloj.fin);

    return {
        enPlazo: Math.max(0, Math.min(fin, limite) - detectado),
        restante: Math.max(0, limite - fin),
        fuera: Math.max(0, fin - limite),
    };
});

/**
 * Dónde cae cada hito, en tanto por ciento del tramo que se dibuja. El que acaba
 * la barra —el límite o el fin, el que llegue después— va al 100 %; el otro, en
 * su sitio, sujeto lejos de los extremos para que su rótulo no pise los de al
 * lado.
 */
const hitos = computed(() => {
    const detectado = Date.parse(props.reloj.detectado);
    const limite = Date.parse(props.reloj.limite);
    const fin = Date.parse(props.reloj.fin);
    const tramo = Math.max(limite, fin) - detectado || 1;
    const sitio = (instante: number): number => Math.min(82, Math.max(18, ((instante - detectado) / tramo) * 100));

    const finHito = {
        clave: 'fin',
        rotulo: props.reloj.finEsNotificacion ? 'Notificada' : 'Ahora',
        cuando: formato.format(new Date(fin)),
        instante: fin,
    };
    const limiteHito = { clave: 'limite', rotulo: 'Límite', cuando: formato.format(new Date(limite)), instante: limite };
    const [intermedio, ultimo] = fin > limite ? [limiteHito, finHito] : [finHito, limiteHito];

    return {
        detectado: { rotulo: 'Detectado', cuando: formato.format(new Date(detectado)) },
        intermedio: { ...intermedio, sitio: sitio(intermedio.instante) },
        ultimo,
    };
});

const descripcion = computed(() => {
    const { detectado, intermedio, ultimo } = hitos.value;
    const fuera = props.reloj.horasFueraDePlazo;

    return `Detectado el ${detectado.cuando}; ${intermedio.rotulo.toLowerCase()}, ${intermedio.cuando}; ${ultimo.rotulo.toLowerCase()}, ${ultimo.cuando}${
        fuera ? `: ${fuera} h fuera de plazo` : ''
    }.`;
});
</script>

<template>
    <div class="flex gap-1" role="img" :aria-label="descripcion">
        <div v-if="!inicioConocido" class="w-10 shrink-0 space-y-2" aria-hidden="true">
            <div class="flex h-2.5 items-center">
                <span class="w-full border-t-2 border-dashed border-border" />
            </div>
            <p class="text-xs text-muted-foreground">Inicio ?</p>
        </div>

        <div class="min-w-0 flex-1 space-y-2" aria-hidden="true">
            <div class="flex h-2.5 gap-0.5">
                <span
                    v-if="tramos.enPlazo > 0"
                    class="h-full rounded-l-full bg-estado-en-progreso/40"
                    :class="{ 'rounded-r-full': tramos.restante === 0 && tramos.fuera === 0 }"
                    :style="{ flexGrow: tramos.enPlazo }"
                />
                <span
                    v-if="tramos.restante > 0"
                    class="h-full rounded-r-full bg-muted"
                    :class="{ 'rounded-l-full': tramos.enPlazo === 0 }"
                    :style="{ flexGrow: tramos.restante }"
                />
                <span
                    v-if="tramos.fuera > 0"
                    class="h-full rounded-r-full bg-destructive"
                    :style="{ flexGrow: tramos.fuera }"
                />
            </div>

            <div class="relative h-8 text-xs text-muted-foreground">
                <span class="absolute left-0 flex flex-col">
                    <span class="font-medium text-foreground">{{ hitos.detectado.rotulo }}</span>
                    <span class="cifra">{{ hitos.detectado.cuando }}</span>
                </span>
                <span
                    class="absolute flex -translate-x-1/2 flex-col items-center"
                    :style="{ left: `${hitos.intermedio.sitio}%` }"
                >
                    <span class="font-medium text-foreground">{{ hitos.intermedio.rotulo }}</span>
                    <span class="cifra">{{ hitos.intermedio.cuando }}</span>
                </span>
                <span class="absolute right-0 flex flex-col items-end">
                    <span class="font-medium text-foreground">{{ hitos.ultimo.rotulo }}</span>
                    <span class="cifra">{{ hitos.ultimo.cuando }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
