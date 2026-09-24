<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Las cuentas de la organización (§ 4.19).
 *
 * **Un solo rojo, y no es de las cifras**: el auditor sin alcance, que ve lo
 * que no debería. Lo demás, como puestos: una invitación sin aceptar es lo que
 * pasa entre enviar un correo y que alguien lo lea, y una cuenta activa sin
 * segundo factor no puede escribir —`ExigirDosFactores` la manda al perfil—,
 * así que no expone nada. Las dos cifras van en una línea y sólo si no están a
 * cero.
 */
defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    invitadas: number;
    sinDosFactores: number;
    auditoresSinAlcance: number;
}>();
</script>

<template>
    <AppLayout ancho="completo" :titulo="recurso.etiquetas.plural">
        <CabeceraPagina :titulo="recurso.etiquetas.plural" :descripcion="recurso.etiquetas.descripcion" />

        <!--
            El único aviso de la pantalla: un auditor sin sistemas ve la
            organización entera, que es lo contrario de lo que pide el § 4.19.
            Ninguna cuenta nueva puede quedar así; una anterior, sí.
        -->
        <Aviso v-if="auditoresSinAlcance > 0" tono="error" titulo="Auditores sin alcance">
            {{ auditoresSinAlcance === 1 ? 'Una cuenta de auditor no tiene' : `${auditoresSinAlcance} cuentas de auditor no tienen` }}
            ningún sistema asignado, así que ve la organización entera. Edítala para decir qué audita y
            hasta cuándo.
        </Aviso>

        <Card v-if="invitadas > 0 || sinDosFactores > 0" size="sm">
            <CardContent class="flex flex-wrap items-baseline gap-x-6 gap-y-2 text-sm">
                <p v-if="invitadas > 0" class="text-muted-foreground">
                    <Cifra class="font-semibold text-foreground" :valor="invitadas" />
                    {{ invitadas === 1 ? 'invitación sin aceptar' : 'invitaciones sin aceptar' }}
                </p>
                <p v-if="sinDosFactores > 0" class="text-muted-foreground">
                    <Cifra class="font-semibold text-foreground" :valor="sinDosFactores" />
                    {{ sinDosFactores === 1 ? 'cuenta activa' : 'cuentas activas' }}
                    sin verificación en dos pasos: no podrán escribir hasta activarla
                </p>
            </CardContent>
        </Card>

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
