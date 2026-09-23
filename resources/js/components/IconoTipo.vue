<script setup lang="ts">
import {
    AppWindowIcon,
    ArchiveIcon,
    ArrowRightLeftIcon,
    BadgeCheckIcon,
    BanIcon,
    Building2Icon,
    CalendarClockIcon,
    CheckIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    ChevronsUpIcon,
    CircleAlertIcon,
    CircleCheckIcon,
    CircleDotDashedIcon,
    CircleHelpIcon,
    CircleIcon,
    CircleSlashIcon,
    CircleXIcon,
    ClockIcon,
    CloudLightningIcon,
    DatabaseIcon,
    EqualIcon,
    EyeIcon,
    FactoryIcon,
    FileCheckIcon,
    FileXIcon,
    GaugeIcon,
    GlobeIcon,
    GraduationCapIcon,
    HammerIcon,
    HandshakeIcon,
    HardDriveIcon,
    LandmarkIcon,
    LayoutTemplateIcon,
    LightbulbIcon,
    ListTodoIcon,
    LoaderCircleIcon,
    LockIcon,
    MessageCircleIcon,
    MessageSquareWarningIcon,
    MinusIcon,
    NetworkIcon,
    OctagonAlertIcon,
    PackageIcon,
    PaperclipIcon,
    PenLineIcon,
    PlugIcon,
    RepeatIcon,
    SearchCheckIcon,
    ServerIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    ShieldIcon,
    ShieldPlusIcon,
    ShieldXIcon,
    SwordsIcon,
    TargetIcon,
    Trash2Icon,
    TriangleAlertIcon,
    UserCheckIcon,
    UserRoundCheckIcon,
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
    BadgeCheck: BadgeCheckIcon,
    Ban: BanIcon,
    Building2: Building2Icon,
    CalendarClock: CalendarClockIcon,
    Check: CheckIcon,
    ChevronDown: ChevronDownIcon,
    ChevronUp: ChevronUpIcon,
    ChevronsUp: ChevronsUpIcon,
    Circle: CircleIcon,
    CircleAlert: CircleAlertIcon,
    CircleCheck: CircleCheckIcon,
    CircleDotDashed: CircleDotDashedIcon,
    CircleHelp: CircleHelpIcon,
    CircleSlash: CircleSlashIcon,
    CircleX: CircleXIcon,
    Clock: ClockIcon,
    CloudLightning: CloudLightningIcon,
    Database: DatabaseIcon,
    Equal: EqualIcon,
    Eye: EyeIcon,
    Factory: FactoryIcon,
    FileCheck: FileCheckIcon,
    FileX: FileXIcon,
    Gauge: GaugeIcon,
    Globe: GlobeIcon,
    GraduationCap: GraduationCapIcon,
    Hammer: HammerIcon,
    Handshake: HandshakeIcon,
    HardDrive: HardDriveIcon,
    Landmark: LandmarkIcon,
    LayoutTemplate: LayoutTemplateIcon,
    Lightbulb: LightbulbIcon,
    ListTodo: ListTodoIcon,
    LoaderCircle: LoaderCircleIcon,
    Lock: LockIcon,
    MessageCircle: MessageCircleIcon,
    MessageSquareWarning: MessageSquareWarningIcon,
    Minus: MinusIcon,
    Network: NetworkIcon,
    OctagonAlert: OctagonAlertIcon,
    Package: PackageIcon,
    Paperclip: PaperclipIcon,
    PenLine: PenLineIcon,
    Plug: PlugIcon,
    Repeat: RepeatIcon,
    SearchCheck: SearchCheckIcon,
    Server: ServerIcon,
    Shield: ShieldIcon,
    ShieldAlert: ShieldAlertIcon,
    ShieldCheck: ShieldCheckIcon,
    ShieldPlus: ShieldPlusIcon,
    ShieldX: ShieldXIcon,
    Swords: SwordsIcon,
    Target: TargetIcon,
    Trash2: Trash2Icon,
    TriangleAlert: TriangleAlertIcon,
    UserCheck: UserCheckIcon,
    UserRoundCheck: UserRoundCheckIcon,
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
