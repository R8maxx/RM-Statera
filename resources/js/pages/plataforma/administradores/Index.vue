<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFechaHora } from '@/lib/celdas';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Quién administra la plataforma (punto 49).
 *
 * Cada acción pide la contraseña de quien la hace en la misma petición: quien
 * encuentre una sesión abierta no puede darse un administrador ni quitárselo a
 * otro. Las reglas —nadie sobre sí mismo, nunca sin alguien de Administración—
 * son del servidor; aquí sólo se esconde lo que no tiene sentido ofrecer.
 */
interface Administrador {
    id: number;
    nombre: string;
    email: string;
    perfil: string | null;
    perfilEtiqueta: string | null;
    dosFactores: boolean;
    ultimoAcceso: string | null;
    organizacion: string | null;
    estado: { valor: string; etiqueta: string; tono: string; icono: string };
    esLaPropia: boolean;
}

interface Perfil {
    valor: string;
    etiqueta: string;
    descripcion: string;
}

const props = defineProps<{ administradores: Administrador[]; perfiles: Perfil[] }>();

const pagina = usePage();
const errorAdministrador = computed(() => (pagina.props.errors as Record<string, string | undefined>).administrador);

const cuando = (fecha: string | null): string => (fecha ? formatoFechaHora.format(new Date(fecha)) : 'Nunca');
const opcionesPerfil = computed(() => props.perfiles.map((perfil) => ({ valor: perfil.valor, etiqueta: perfil.etiqueta })));
const ayudaPerfil = (valor: string): string | undefined => props.perfiles.find((perfil) => perfil.valor === valor)?.descripcion;

/* Invitar. */
const invitando = ref(false);
const invitacion = useForm({ name: '', email: '', perfil: 'comercial', password: '' });

function invitar(): void {
    invitacion.post('/plataforma/administradores', {
        preserveScroll: true,
        onSuccess: () => {
            invitando.value = false;
            invitacion.reset();
        },
        onFinish: () => invitacion.reset('password'),
    });
}

/* Cambiar el perfil o retirar: el mismo diálogo, con la contraseña. */
const elegido = ref<Administrador | null>(null);
const accion = ref<'perfil' | 'retirar'>('perfil');
const gestion = useForm({ perfil: '', password: '' });

function abrir(administrador: Administrador, que: 'perfil' | 'retirar'): void {
    elegido.value = administrador;
    accion.value = que;
    gestion.reset();
    gestion.clearErrors();
    gestion.perfil = administrador.perfil ?? 'comercial';
}

function confirmar(): void {
    if (!elegido.value) {
        return;
    }

    const opciones = {
        preserveScroll: true,
        onSuccess: () => (elegido.value = null),
        onFinish: () => gestion.reset('password'),
    };

    if (accion.value === 'perfil') {
        gestion.put(`/plataforma/administradores/${elegido.value.id}/perfil`, opciones);
    } else {
        gestion.transform(({ password }) => ({ password })).post(`/plataforma/administradores/${elegido.value.id}/retirar`, opciones);
    }
}
</script>

<template>
    <AppLayout titulo="Administradores">
        <CabeceraPagina
            titulo="Administradores"
            descripcion="Quién administra Statera y con qué perfil. Administración lo puede todo; Gestión comercial lleva clientes, planes y suscripciones."
        >
            <template #acciones>
                <Button @click="invitando = true">Invitar a alguien</Button>
            </template>
        </CabeceraPagina>

        <Aviso v-if="errorAdministrador" tono="error">{{ errorAdministrador }}</Aviso>

        <Card class="py-0">
            <CardContent class="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="pl-6">Nombre</TableHead>
                            <TableHead>Perfil</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Dos pasos</TableHead>
                            <TableHead>Último acceso</TableHead>
                            <TableHead class="sr-only">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="administrador in administradores" :key="administrador.id">
                            <TableCell class="pl-6">
                                <span class="block font-medium">
                                    {{ administrador.nombre }}
                                    <span v-if="administrador.esLaPropia" class="text-xs font-normal text-muted-foreground">· Tú</span>
                                </span>
                                <span class="block text-muted-foreground">{{ administrador.email }}</span>
                                <span v-if="administrador.organizacion" class="block text-xs text-muted-foreground">
                                    Además, de {{ administrador.organizacion }}
                                </span>
                            </TableCell>
                            <TableCell>{{ administrador.perfilEtiqueta }}</TableCell>
                            <TableCell><CeldaBadge :valor="{ ...administrador.estado }" /></TableCell>
                            <TableCell>{{ administrador.dosFactores ? 'Activada' : 'Sin activar' }}</TableCell>
                            <TableCell class="cifra text-[13px]">{{ cuando(administrador.ultimoAcceso) }}</TableCell>
                            <TableCell class="pr-6 text-right">
                                <template v-if="!administrador.esLaPropia">
                                    <Button variant="ghost" size="sm" @click="abrir(administrador, 'perfil')">Cambiar perfil</Button>
                                    <Button variant="ghost" size="sm" @click="abrir(administrador, 'retirar')">Retirar</Button>
                                </template>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Dialog v-model:open="invitando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invitar a administrar la plataforma</DialogTitle>
                    <DialogDescription>
                        Le llegará un correo para fijar su contraseña, y tendrá que activar la verificación en dos pasos
                        para entrar.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="invitar">
                    <CampoTexto v-model="invitacion.name" nombre="name" etiqueta="Nombre" :error="invitacion.errors.name" requerido />
                    <CampoTexto
                        v-model="invitacion.email"
                        nombre="email"
                        etiqueta="Correo electrónico"
                        tipo="email"
                        :error="invitacion.errors.email"
                        requerido
                    />
                    <CampoSelect
                        v-model="invitacion.perfil"
                        nombre="perfil"
                        etiqueta="Perfil"
                        :opciones="opcionesPerfil"
                        :error="invitacion.errors.perfil"
                        :ayuda="ayudaPerfil(invitacion.perfil)"
                        requerido
                    />
                    <CampoTexto
                        v-model="invitacion.password"
                        nombre="password"
                        etiqueta="Tu contraseña"
                        tipo="password"
                        :error="invitacion.errors.password"
                        ayuda="Para confirmar que eres tú quien invita."
                        requerido
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="invitando = false">Cancelar</Button>
                        <Button type="submit" :disabled="invitacion.processing">Enviar la invitación</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="elegido !== null" @update:open="(abierto: boolean) => !abierto && (elegido = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ accion === 'perfil' ? `Cambiar el perfil de ${elegido?.nombre}` : `Retirar a ${elegido?.nombre}` }}
                    </DialogTitle>
                    <DialogDescription v-if="accion === 'retirar'">
                        Deja de administrar la plataforma.
                        <template v-if="elegido?.organizacion">Sigue siendo usuario de {{ elegido.organizacion }}.</template>
                        <template v-else>Como no es de ninguna organización, su cuenta se desactiva.</template>
                        Lo que hizo sigue a su nombre.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="confirmar">
                    <CampoSelect
                        v-if="accion === 'perfil'"
                        v-model="gestion.perfil"
                        nombre="perfil"
                        etiqueta="Perfil"
                        :opciones="opcionesPerfil"
                        :error="gestion.errors.perfil"
                        :ayuda="ayudaPerfil(gestion.perfil)"
                        requerido
                    />
                    <CampoTexto
                        v-model="gestion.password"
                        nombre="password"
                        etiqueta="Tu contraseña"
                        tipo="password"
                        :error="gestion.errors.password"
                        requerido
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="elegido = null">Cancelar</Button>
                        <Button type="submit" :variant="accion === 'retirar' ? 'destructive' : 'default'" :disabled="gestion.processing">
                            {{ accion === 'perfil' ? 'Cambiar el perfil' : 'Retirar' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
