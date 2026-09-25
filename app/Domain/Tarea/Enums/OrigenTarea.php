<?php

declare(strict_types=1);

namespace App\Domain\Tarea\Enums;

/**
 * De dónde sale una tarea.
 *
 * **Se declara entero y se van cableando según llegan sus módulos.** Cinco son
 * los de § 4.7; hoy existen `BrechaImplantacion`, `Riesgo` y `NoConformidad`, e
 * incidente y revisión por la dirección siguen esperando a § 4.10 y § 4.15. Mismo
 * criterio con el que se carga el Anexo II completo usando el subconjunto de
 * categoría básica: el modelo entero desde el principio y los datos que haya.
 *
 * **`NoConformidad` no está en § 4.7 y es el que de verdad usa una auditoría.**
 * La especificación enumera «hallazgo», y una tarea no cuelga nunca de un
 * hallazgo: cuelga de la **no conformidad que lo trata**, que es quien tiene la
 * causa raíz, el responsable, el plazo y la verificación de eficacia. Entre el
 * hallazgo y la tarea hay exactamente un registro (§ 4.13), y saltárselo dejaría
 * el trabajo correctivo sin nada que explique por qué se hace — que es justo lo
 * que este campo existe para contestar. `Hallazgo` se queda declarado y sin
 * ofrecerse, y ahora por ese motivo y no porque falte su módulo.
 *
 * **`Mejora` es el cuarto que no está en § 4.7**, y llega con la cláusula 10.1.
 * **No se apunta a `NoConformidad`**, que es el que más se le parece: una acción
 * correctiva ataca la causa de algo que incumple, y una actuación de mejora
 * materializa algo que se puede hacer mejor sin que nada incumpla. La 10.1 y la
 * 10.2 son dos cláusulas distintas precisamente por eso, y colapsarlas haría que
 * el reparto por origen del plan contara como reactivo un trabajo voluntario —
 * que es justo lo que ese reparto existe para distinguir.
 *
 * **`Objetivo` es el tercero que no está en § 4.7**, y llega con la cláusula 6.2.
 * «Qué se hará» es lo primero que esa cláusula pide de la planificación de un
 * objetivo de seguridad, y esas actuaciones no son ninguna de las ocho cosas
 * anteriores. **No son una brecha de implantación**, que es lo que más se le
 * parece: una brecha es una medida exigible que no está implantada, con su
 * requisito detrás, y un objetivo no cuelga de ningún requisito — puede cumplirse
 * sin mover una sola implantación, y los más interesantes son así.
 *
 * **`Contexto` tampoco está en § 4.7, y es el segundo que se añade a conciencia.**
 * Una debilidad del DAFO —«el software de los puestos no está inventariado»— es
 * trabajo que hay que hacer y no es ninguna de las demás. No es
 * un hallazgo, que sale de auditar contra un requisito; ni un riesgo, que tiene
 * probabilidad, impacto y una decisión de tratamiento detrás. Puede acabar
 * generando un riesgo, y entonces la tarea de ese riesgo será otra tarea.
 *
 * `disponible()` es un `match` exhaustivo y no una comparación con `||`: era el
 * único sitio del enum donde olvidarse de un caso nuevo no lo señalaba nadie.
 *
 * `Propia` **no está en la especificación y se añade a conciencia.** Los cinco de
 * § 4.7 dan por supuesto que toda tarea nace de otro registro, y muchas no: «pedir
 * presupuesto del antivirus» no es un hallazgo, ni un riesgo, ni un incidente.
 * Sin un valor para eso, quien apunta una tarea a mano elige el que menos mal le
 * suena y el campo deja de significar nada, que es justo lo contrario de por qué
 * existe.
 *
 * **`Continuidad` es el quinto que no está en § 4.7, y llega con el § 4.11.** Una
 * prueba de un plan de continuidad que sale parcial o fallida deja trabajo
 * correctivo directo —«revisar el guion de failover»—, y no espera a ninguna no
 * conformidad, exactamente igual que `Incidente`: puede que la prueba no llegue a
 * abrir ninguna. A diferencia de `Incidente`, aquí sí hay un vínculo de verdad
 * —la pivote `prueba_continuidad_tarea`—, porque `op.cont.3` necesita poder
 * contar cuánto trabajo dejó cada prueba y no sólo la etiqueta.
 *
 * **`Proveedor` es el sexto, y llega con el § 4.9.** Lo que una evaluación apta
 * con condiciones deja pendiente —firmar el encargo de tratamiento, pedir el
 * informe de auditoría del proveedor— es trabajo con responsable y plazo, y no es
 * ninguna de las demás. Como `Continuidad`, lleva vínculo de verdad: la pivote
 * `proveedor_tarea`.
 *
 * **`Vulnerabilidad` es el séptimo, con el registro del invariante 8.** Aplicar
 * un parche o sustituir un equipo sin soporte es trabajo con responsable y plazo
 * —el de remediación, que la tarea hereda—, con su pivote
 * `vulnerabilidad_tarea`.
 */
enum OrigenTarea: string
{
    case Hallazgo = 'hallazgo';
    case NoConformidad = 'no_conformidad';
    case Mejora = 'mejora';
    case Riesgo = 'riesgo';
    case BrechaImplantacion = 'brecha_implantacion';
    case Contexto = 'contexto';
    case Objetivo = 'objetivo';
    case Incidente = 'incidente';
    case RevisionDireccion = 'revision_direccion';
    case Continuidad = 'continuidad';
    case Proveedor = 'proveedor';
    case Vulnerabilidad = 'vulnerabilidad';
    case Propia = 'propia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Hallazgo => 'Hallazgo de auditoría',
            self::NoConformidad => 'Acción correctiva',
            self::Mejora => 'Oportunidad de mejora',
            self::Riesgo => 'Tratamiento de un riesgo',
            self::BrechaImplantacion => 'Requisito pendiente',
            self::Contexto => 'Cuestión del contexto',
            self::Objetivo => 'Objetivo de seguridad',
            self::Incidente => 'Incidente',
            self::RevisionDireccion => 'Revisión por la dirección',
            self::Continuidad => 'Prueba de continuidad',
            self::Proveedor => 'Proveedor',
            self::Vulnerabilidad => 'Vulnerabilidad',
            self::Propia => 'Iniciativa propia',
        };
    }

    /**
     * Si hoy se puede crear una tarea con este origen.
     *
     * Los orígenes cuyo módulo no existe se declaran pero no se ofrecen: una
     * lista desplegable con cuatro opciones que no llevan a ninguna parte enseña
     * el mapa de lo que falta en lugar de dejar hacer el trabajo de hoy.
     */
    public function disponible(): bool
    {
        return match ($this) {
            self::BrechaImplantacion, self::Continuidad, self::Contexto, self::Incidente, self::Mejora,
            self::NoConformidad, self::Objetivo, self::Propia, self::Proveedor, self::RevisionDireccion, self::Vulnerabilidad,
            self::Riesgo => true,
            /*
             * `Hallazgo` sigue sin ofrecerse, y desde el § 4.13 **por otro
             * motivo**: no es que falte su módulo —llegó con el § 4.12—, es que
             * entre un hallazgo y una tarea hay un eslabón por medio. El trabajo
             * correctivo cuelga de la no conformidad que trata el hallazgo, y
             * ofrecerlo aquí sería dejar apuntar acciones correctivas sin causa
             * raíz, sin responsable y sin verificación de eficacia detrás.
             *
             * **`RevisionDireccion` se ofrece desde el § 4.15**, y no hizo falta
             * ninguna migración para ello: el valor estaba en el `CHECK` de
             * `tareas.origen` desde la primera, porque el enum se declaró entero y
             * lo que faltaba era su módulo. Es la diferencia con `objetivo` y
             * `mejora`, que sí eran valores nuevos y sí la necesitaron.
             *
             * **`Incidente` se ofrece desde el § 4.10**, y tampoco hizo falta
             * migración, por lo mismo: el valor estaba en el `CHECK` desde la
             * primera. Y a diferencia de `Hallazgo`, aquí no hay eslabón por
             * medio: contener un incidente produce trabajo directo —«revisar las
             * reglas del filtro de correo»— que no espera a ninguna no
             * conformidad, porque puede que no llegue a haberla.
             */
            self::Hallazgo => false,
        };
    }

    /** @return list<self> */
    public static function disponibles(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $origen): bool => $origen->disponible()));
    }

    /**
     * El tono con el que se pinta el reparto por origen del panel.
     *
     * **Tres tonos para los nueve orígenes, y no una familia `origen:*` con nueve
     * colores.** Se valoró y no sale: nueve colores distinguibles no existen en
     * la paleta —los únicos siete medidos son los `--tipo-*`, y un origen no es
     * un tipo de activo—, y el reparto se pinta con `GraficaBarras`, donde **cada
     * barra lleva su etiqueta escrita**. Con el nombre al lado, el color no tiene
     * que identificar: puede decir otra cosa, y aquí dice la que importa.
     *
     * Y la que importa es **de dónde sale el trabajo**: ámbar lo reactivo —algo
     * falló y esto es la respuesta—, azul lo planificado —una brecha, un riesgo
     * que se trata, una salida de la revisión por la dirección— y gris la
     * iniciativa propia. Eso es lo que se va a mirar: un plan que es todo ámbar
     * es una organización apagando fuegos, y uno que es todo azul es una que se
     * adelanta. Un arcoíris de nueve colores no contesta eso.
     *
     * **Ninguno gasta rojo.** Una acción correctiva no es un incumplimiento: es
     * exactamente lo que hay que hacer con uno. El rojo de esta tabla es de la
     * columna de plazo, y gastarlo aquí haría que dejara de saltar a la vista.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Hallazgo, self::NoConformidad, self::Incidente, self::Continuidad, self::Vulnerabilidad => 'en_progreso',
            self::BrechaImplantacion, self::Riesgo, self::Contexto, self::Objetivo, self::Mejora, self::RevisionDireccion,
            self::Proveedor => 'planificado',
            self::Propia => 'no_iniciado',
        };
    }
}
