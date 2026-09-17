<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad\Enums;

/**
 * El ciclo de la cláusula 10.2, con un estado por cada cosa que pide.
 *
 * `Abierta` es «está registrada y nadie la ha cogido»; `EnTratamiento`, que hay
 * acciones correctivas en marcha; `Cerrada`, que el tratamiento terminó; y
 * `Verificada`, que alguien comprobó **que funcionó**. Ese último paso es el que
 * la norma pide y el que más se olvida, y por eso es un estado y no una casilla:
 * una no conformidad cerrada sin verificar tiene que poder contarse aparte, que
 * es justo lo que el auditor pregunta.
 *
 * **La verificación fallida no es un estado, es la vuelta a `EnTratamiento`.** Un
 * `no_eficaz` se quedaría puesto sobre una no conformidad que sigue viva, y en
 * cuanto alguien lo mirara por encima volvería a contarse como cerrada. El
 * precedente exacto es `EstadoAuditoria::Cerrada → EnCurso`: corregir se puede,
 * a escondidas no, y por eso la vuelta es una transición con su fecha, su autor y
 * su nota.
 *
 * `Anulada` es la que se registró y no era: un duplicado, o un hallazgo que al
 * mirarlo de cerca no incumplía nada. **Exige motivo**, igual que `descartada` en
 * tareas y `rechazado` en documentos, y no se borra la fila: borrarla dejaría el
 * hallazgo sin rastro de qué se decidió con él.
 */
enum EstadoNoConformidad: string
{
    case Abierta = 'abierta';
    case EnTratamiento = 'en_tratamiento';
    case Cerrada = 'cerrada';
    case Verificada = 'verificada';
    case Anulada = 'anulada';

    /**
     * A qué estados se puede pasar desde éste.
     *
     * De `Cerrada` se vuelve a `EnTratamiento` —la verificación que sale mal— y de
     * `Verificada` también, que es la puerta de siempre: los tres triggers de
     * inmutabilidad del producto dejan una, y aquí no hay trigger pero el
     * razonamiento es el mismo. Lo que no se puede es volver a `Abierta`: decir
     * que una no conformidad que ya se trató está sin coger es reescribir el
     * pasado, igual que devolver una auditoría a `planificada`.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Abierta => [self::EnTratamiento, self::Cerrada, self::Anulada],
            self::EnTratamiento => [self::Cerrada, self::Anulada],
            self::Cerrada => [self::Verificada, self::EnTratamiento],
            self::Verificada => [self::EnTratamiento],
            // Se anuló por error: se reabre y se vuelve a tratar.
            self::Anulada => [self::Abierta],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /**
     * Si ha dejado de estar abierta.
     *
     * Es el mismo reparto que `EstadoTarea::esCerrada()`, y el que decide qué
     * cuenta el indicador del panel: una no conformidad anulada no está
     * pendiente, está cerrada con su motivo en el histórico.
     */
    public function esCerrada(): bool
    {
        return $this === self::Cerrada || $this === self::Verificada || $this === self::Anulada;
    }

    /** Si el `CHECK` de la base exige `fecha_verificacion`. */
    public function exigeVerificacion(): bool
    {
        return $this === self::Verificada;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::EnTratamiento => 'En tratamiento',
            self::Cerrada => 'Pendiente de verificar',
            self::Verificada => 'Verificada',
            self::Anulada => 'Anulada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Abierta => 'CircleAlert',
            // Hay algo puesto en marcha para arreglarlo.
            self::EnTratamiento => 'Wrench',
            // Hecho y esperando a que alguien compruebe que sirvió.
            self::Cerrada => 'Clock',
            // El sello, no el visto: lo que se comprobó fue la eficacia.
            self::Verificada => 'BadgeCheck',
            self::Anulada => 'CircleSlash',
        };
    }

    /**
     * El vocabulario de siempre, con una decisión que conviene declarar.
     *
     * **`Cerrada` gasta el violeta de `en_revision`, y es el segundo badge de
     * estado que lo hace.** Hasta ahora el token tenía un solo dueño —la versión
     * de un documento esperando firma— y CLAUDE.md lo decía así. Los dos
     * significan exactamente lo mismo: hecho y a la espera de que alguien con
     * potestad lo confirme. Un token con dos dueños que quieren decir lo mismo
     * sigue significando algo; el violeta se rompe cuando pasa a ser decoración,
     * no cuando lo usa el segundo flujo de revisión del producto. Y DESIGN.md lo
     * reservaba literalmente a los flujos de revisión y auditoría: éste es los
     * dos a la vez.
     *
     * **Ninguno gasta rojo, ni siquiera `Abierta`.** La gravedad la lleva el tipo
     * del hallazgo —`TipoHallazgo::NcMayor` sí es rojo— y el plazo la lleva su
     * columna, como en tareas. Pintar de rojo el estado dejaría el registro entero
     * en rojo por estar haciendo su trabajo, y entonces la mayor vencida, que es
     * lo que hay que atender hoy, dejaría de saltar a la vista.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Abierta => 'no_iniciado',
            self::EnTratamiento => 'en_progreso',
            self::Cerrada => 'en_revision',
            self::Verificada => 'implantado',
            self::Anulada => 'no_aplica',
        };
    }
}
