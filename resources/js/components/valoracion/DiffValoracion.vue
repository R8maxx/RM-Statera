<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { computed } from 'vue';

type Previsualizacion = App.Http.Resources.Valoracion.PrevisualizacionValoracion;

/**
 * El diff del recálculo, antes de aplicarlo.
 *
 * Mismo papel que `catalogo:importar --dry-run`: el recálculo no modifica nada
 * en silencio. Los códigos se enseñan enteros porque «catorce medidas dejan de
 * aplicar» no es una frase sobre la que nadie pueda decidir.
 *
 * Los bloques van en neutro a propósito. La paleta `--estado-*` es semántica del
 * dominio —lo que le pasa a una implantación— y usarla aquí para decir «entra» o
 * «sale» le enseñaría al ojo dos significados para el mismo color.
 */
const props = defineProps<{ diff: Previsualizacion; guardando: boolean }>();

const emit = defineEmits<{ cancelar: []; confirmar: [] }>();

const bloques = computed(() => [
    {
        clave: 'creadas',
        titulo: 'Medidas nuevas',
        nota: 'Entran sin iniciar.',
        codigos: props.diff.creadas,
    },
    {
        clave: 'reactivadas',
        titulo: 'Vuelven a exigirse',
        nota: 'Recuperan el estado que tenían antes de dejar de aplicar.',
        codigos: props.diff.reactivadas,
    },
    {
        clave: 'exigencia',
        titulo: 'Cambian de nivel de refuerzo',
        nota: 'Siguen exigiéndose, pero no al mismo nivel: conservan su estado.',
        codigos: props.diff.cambianExigencia.map(
            (cambio) => `${cambio.codigo} · ${cambio.anterior} → ${cambio.nueva}`,
        ),
    },
    {
        clave: 'dejan',
        titulo: 'Dejan de exigirse',
        nota: 'No se borran: pasan a «no aplica» y conservan su histórico.',
        codigos: props.diff.dejanDeAplicar,
    },
].filter((bloque) => bloque.codigos.length > 0));
</script>

<template>
    <Dialog :open="true" @update:open="(abierto: boolean) => !abierto && emit('cancelar')">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Revisa el recálculo</DialogTitle>
                <DialogDescription>
                    Todavía no se ha guardado nada. Esto es lo que cambiaría en el conjunto exigible.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-5">
                <div class="rounded-xl border bg-superficie px-4 py-3">
                    <p class="text-xs text-muted-foreground">Categoría resultante</p>
                    <p class="text-base font-semibold tracking-tight">
                        {{ diff.categoria ?? 'Fuera del ámbito del ENS' }}
                    </p>
                    <p v-if="!diff.enAmbitoEns" class="mt-1 text-sm text-muted-foreground">
                        Las cinco dimensiones valoradas como «no aplica» dejan el sistema fuera del ENS, que no es
                        lo mismo que categoría básica: no se le exige ninguna medida.
                    </p>
                </div>

                <p v-if="!diff.hayCambios" class="text-sm text-muted-foreground">
                    El conjunto exigible no cambia: las {{ diff.sinCambios }} medidas de hoy siguen siendo las
                    mismas. Se guardarán los niveles y las justificaciones.
                </p>

                <section v-for="bloque in bloques" :key="bloque.clave">
                    <h3 class="text-sm font-medium">
                        {{ bloque.titulo }}
                        <span class="ml-1 text-muted-foreground">({{ bloque.codigos.length }})</span>
                    </h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ bloque.nota }}</p>

                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        <li
                            v-for="codigo in bloque.codigos"
                            :key="codigo"
                            class="rounded-full border bg-background px-2 py-0.5 font-mono text-xs"
                        >
                            {{ codigo }}
                        </li>
                    </ul>
                </section>

                <p v-if="diff.hayCambios" class="text-sm text-muted-foreground">
                    {{ diff.sinCambios }} medidas se quedan como están.
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="guardando" @click="emit('cancelar')">Cancelar</Button>
                <Button :disabled="guardando" @click="emit('confirmar')">
                    {{ guardando ? 'Guardando…' : 'Guardar y recalcular' }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
