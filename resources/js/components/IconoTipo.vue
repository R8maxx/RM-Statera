<script setup lang="ts">
import {
    AppWindowIcon,
    ArchiveIcon,
    ArrowRightLeftIcon,
    BanIcon,
    Building2Icon,
    CalendarClockIcon,
    CheckIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    ChevronsUpIcon,
    CircleIcon,
    CircleCheckIcon,
    CircleDotDashedIcon,
    CircleHelpIcon,
    CircleSlashIcon,
    ClockIcon,
    DatabaseIcon,
    EqualIcon,
    FileCheckIcon,
    GlobeIcon,
    HammerIcon,
    HardDriveIcon,
    LayoutTemplateIcon,
    ListTodoIcon,
    LoaderCircleIcon,
    LockIcon,
    MinusIcon,
    NetworkIcon,
    PackageIcon,
    PaperclipIcon,
    PenLineIcon,
    PlugIcon,
    ShieldAlertIcon,
    Trash2Icon,
    TriangleAlertIcon,
    UsersIcon,
    WrenchIcon,
    XIcon,
} from '@lucide/vue';
import { computed, type Component } from 'vue';

/**
 * El icono de un concepto del dominio, resuelto por nombre.
 *
 * Lo usan los badges de estado, la tipología de activos, las fuentes de un
 * vencimiento y los botones que llevan a un estado. **El nombre lo decide el
 * dominio** —`EstadoTarea::icono()` y compañía—; aquí sólo se resuelve.
 *
 * Mapa explícito, y no un `import *`: importar `@lucide/vue` entero para
 * resolver el nombre en ejecución arrastraría el paquete al bundle.
 *
 * **Este icono no es decoración.** DESIGN.md §3 lo dice de los estados —«nunca
 * comunicar un estado sólo con color: color + icono + texto»— y de los tipos de
 * activo, donde la peor pareja queda en ΔE 5.2 y es el icono el que carga con la
 * identidad. Los dos grises del dominio, `no_iniciado` y `no_aplica`, están a
 * ΔE 2.3 con protanopía: sin icono, son el mismo badge.
 *
 * Resuelve a nada si el nombre no está en el mapa, que es un fallo silencioso.
 * Lo cierra `tests/Unit/Diseno/IconosTest.php`, que comprueba que todo nombre
 * que el servidor puede emitir está aquí — y que aquí no sobra ninguno.
 */
const iconos: Record<string, Component> = {
    AppWindow: AppWindowIcon,
    Archive: ArchiveIcon,
    ArrowRightLeft: ArrowRightLeftIcon,
    Ban: BanIcon,
    Building2: Building2Icon,
    CalendarClock: CalendarClockIcon,
    Check: CheckIcon,
    ChevronDown: ChevronDownIcon,
    ChevronUp: ChevronUpIcon,
    ChevronsUp: ChevronsUpIcon,
    Circle: CircleIcon,
    CircleCheck: CircleCheckIcon,
    CircleDotDashed: CircleDotDashedIcon,
    CircleHelp: CircleHelpIcon,
    CircleSlash: CircleSlashIcon,
    Clock: ClockIcon,
    Database: DatabaseIcon,
    Equal: EqualIcon,
    FileCheck: FileCheckIcon,
    Globe: GlobeIcon,
    Hammer: HammerIcon,
    HardDrive: HardDriveIcon,
    LayoutTemplate: LayoutTemplateIcon,
    ListTodo: ListTodoIcon,
    LoaderCircle: LoaderCircleIcon,
    Lock: LockIcon,
    Minus: MinusIcon,
    Network: NetworkIcon,
    Package: PackageIcon,
    Paperclip: PaperclipIcon,
    PenLine: PenLineIcon,
    Plug: PlugIcon,
    ShieldAlert: ShieldAlertIcon,
    Trash2: Trash2Icon,
    TriangleAlert: TriangleAlertIcon,
    Users: UsersIcon,
    Wrench: WrenchIcon,
    X: XIcon,
};

const props = withDefaults(defineProps<{ nombre?: string | null; clase?: string }>(), {
    nombre: null,
    clase: 'size-3.5',
});

const icono = computed(() => (props.nombre ? iconos[props.nombre] : undefined));
</script>

<template>
    <component :is="icono" v-if="icono" :class="clase" aria-hidden="true" />
</template>
