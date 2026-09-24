<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useRecorrido } from '@/composables/useRecorrido';
import { Link } from '@inertiajs/vue3';
import { CheckIcon, GaugeIcon, PaperclipIcon, RouteIcon, ServerIcon } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * El panel cuando todavía no hay nada que resumir.
 *
 * **Sustituye a la tarjeta de cabecera, no se añade encima.** Sin esto, lo
 * primero que ve alguien que estrena Statera es un anillo al 0 %, una barra con
 * sus cuatro tramos vacíos y la frase «0 de 0 requisitos exigibles están
 * implantados» — un denominador que no significa nada, y cuatro ceros ocupando
 * el sitio más visible de la pantalla. La regla ya estaba escrita para el
 * inventario («un indicador a cero ya no ocupa una tarjeta») y el propio panel
 * se la saltaba.
 *
 * El orden de los tres pasos no es didáctico, es **de dependencia**: sin sistema
 * no hay cinco dimensiones que valorar, sin valoración no hay implantaciones, y
 * sin implantaciones no hay nada a lo que vincular una prueba. Por eso **sólo el
 * paso en curso ofrece acción**: un botón para registrar una evidencia antes de
 * que exista un sistema lleva a un callejón, y el que lo pulsa aprende que la
 * herramienta le manda a sitios que no sirven.
 *
 * En cuanto hay un requisito exigible esto desaparece y vuelve el panel de
 * siempre. No hay nada que descartar y no queda rastro.
 */

const props = defineProps<{
    sistemas: number;
    aplicables: number;
    evidencias: number;
}>();

const { abrir: abrirRecorrido } = useRecorrido();

type Paso = {
    clave: string;
    ancla: string;
    icono: LucideIcon;
    titulo: string;
    cuerpo: string;
    accion: { etiqueta: string; href: string };
    hecho: boolean;
};

const pasos = computed<Paso[]>(() => [
    {
        clave: 'sistema',
        ancla: 'paso-sistema',
        icono: ServerIcon,
        titulo: 'Da de alta un sistema',
        cuerpo:
            'Un sistema es el trozo de la organización que se somete a los marcos: una sede, una plataforma, un servicio. Es lo que delimita el alcance, y todo lo demás se mide contra él.',
        accion: { etiqueta: 'Dar de alta el primero', href: '/sistemas/crear' },
        hecho: props.sistemas > 0,
    },
    {
        clave: 'valoracion',
        ancla: 'paso-valoracion',
        icono: GaugeIcon,
        titulo: 'Valora sus cinco dimensiones',
        cuerpo:
            'Cuánto daño haría perder la confidencialidad, la integridad, la trazabilidad, la autenticidad o la disponibilidad. De esas cinco respuestas sale la categoría del sistema y la lista exacta de lo que se le exige: no hay que elegir controles a mano.',
        accion: { etiqueta: 'Valorar el sistema', href: '/sistemas' },
        hecho: props.aplicables > 0,
    },
    {
        clave: 'evidencia',
        ancla: 'paso-evidencia',
        icono: PaperclipIcon,
        titulo: 'Registra la primera prueba',
        cuerpo:
            'Un acta, una captura o una política se sube una vez y se vincula a todo lo que demuestra. La misma prueba puede sostener un control de la ISO y tres medidas del ENS a la vez, y ahí es donde esto deja de parecerse a una hoja de cálculo.',
        accion: { etiqueta: 'Registrar una evidencia', href: '/evidencias/crear' },
        hecho: props.evidencias > 0,
    },
]);

/** El primero sin hacer. Sólo ése ofrece acción. */
const enCurso = computed(() => pasos.value.findIndex((paso) => !paso.hecho));
</script>

<template>
    <Card>
        <CardContent class="py-2">
            <h2 class="text-sm font-medium">Por dónde se empieza</h2>
            <p class="mt-1 max-w-prose text-sm text-muted-foreground">
                Statera lleva la ISO/IEC 27001:2022 y el Esquema Nacional de Seguridad a la vez: cada prueba y
                cada tarea se apunta una vez y cuenta en los dos. Para que empiece a contar algo hacen falta tres
                cosas, y en este orden.
            </p>

            <!--
                Una lista con estado, y no tres tarjetas iguales. Tres cajas del
                mismo tamaño dirían que los tres pasos son intercambiables, y lo
                que hay que leer aquí es justamente que uno va después de otro.
            -->
            <ol class="mt-6">
                <li
                    v-for="(paso, numero) in pasos"
                    :key="paso.clave"
                    :data-recorrido="paso.ancla"
                    class="flex gap-4 rounded-lg px-3 transition-colors"
                    :class="numero === enCurso ? 'bg-accent py-3' : 'py-2'"
                >
                    <!--
                        El hilo es un hermano del círculo dentro de una columna
                        que se estira con la fila, no un absoluto con una altura
                        calculada a ojo: los tres pasos miden distinto —sólo el
                        que está en curso despliega su explicación— y con
                        `top`/`height` fijos el hilo del último salía colgando
                        por debajo, hacia nada.
                    -->
                    <div class="flex flex-col items-center">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full border"
                            :class="
                                paso.hecho
                                    ? 'border-transparent bg-estado-implantado-suave text-estado-implantado'
                                    : numero === enCurso
                                      ? 'border-transparent bg-primary text-primary-foreground'
                                      : 'border-border bg-card text-muted-foreground'
                            "
                        >
                            <CheckIcon v-if="paso.hecho" class="size-4" />
                            <component :is="paso.icono" v-else class="size-4" />
                        </span>

                        <span
                            v-if="numero < pasos.length - 1"
                            aria-hidden="true"
                            class="my-1.5 w-px flex-1"
                            :class="paso.hecho ? 'bg-estado-implantado/40' : 'bg-border'"
                        />
                    </div>

                    <div class="min-w-0 flex-1 pt-1.5">
                        <p class="text-sm font-medium" :class="paso.hecho && 'text-muted-foreground'">
                            {{ paso.titulo }}
                            <span v-if="paso.hecho" class="ml-1.5 text-xs font-normal text-estado-implantado">
                                Hecho
                            </span>
                        </p>

                        <!-- La explicación sólo se despliega donde hace falta.
                             Con los tres párrafos a la vez, el paso en curso deja
                             de distinguirse del que viene después. -->
                        <template v-if="numero === enCurso">
                            <p class="mt-1 max-w-prose text-sm text-muted-foreground">{{ paso.cuerpo }}</p>

                            <Button as-child size="sm" class="mt-3">
                                <Link :href="paso.accion.href">{{ paso.accion.etiqueta }}</Link>
                            </Button>
                        </template>
                    </div>
                </li>
            </ol>

            <div class="mt-6 flex items-center gap-2 border-t pt-4">
                <p class="flex-1 text-xs text-muted-foreground">
                    ¿Es la primera vez que ves una herramienta de cumplimiento?
                </p>
                <Button variant="outline" size="sm" @click="abrirRecorrido">
                    <RouteIcon />
                    Ver el recorrido
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
