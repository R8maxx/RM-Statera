<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ConmutadorPanel from '@/components/panel/ConmutadorPanel.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type Vista = App.Http.Resources.Panel.VistaPanel;

/**
 * El título de las tres vistas del panel, con el conmutador en el hueco de las
 * acciones.
 *
 * El panel era la única pantalla del producto sin `<h1>` ni filete: arrancaba
 * con la barra de pestañas, y quien llegaba con un lector de pantalla no sabía
 * en qué pantalla estaba. Es además la primera que se ve al entrar.
 *
 * La descripción es la pregunta de la vista que viaja con `VistaPanel`, la
 * misma que ya servía de `title` en cada pestaña: así las tres dicen qué
 * contestan sin escribirlo aquí tres veces.
 */
const props = withDefaults(defineProps<{ vistas: Vista[]; conmutador?: boolean }>(), { conmutador: true });

const pagina = usePage();
const actual = computed(() => new URL(pagina.url, 'http://localhost').pathname);
const pregunta = computed(() => props.vistas.find((vista) => vista.href === actual.value)?.pregunta ?? null);
</script>

<template>
    <CabeceraPagina titulo="Panel" :descripcion="conmutador ? pregunta : null">
        <template v-if="conmutador" #acciones>
            <ConmutadorPanel :vistas="vistas" />
        </template>
    </CabeceraPagina>
</template>
