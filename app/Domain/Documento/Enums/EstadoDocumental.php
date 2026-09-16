<?php

declare(strict_types=1);

namespace App\Domain\Documento\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El ciclo de vida de una versión de documento.
 *
 * **No confundir con `EstadoGeneracion`**, que es el ciclo de vida del TRABAJO
 * que produce el PDF —encolada, generando, generada, fallida—. Esto es otra cosa:
 * es lo que la organización dice del documento. Que el PDF se haya generado bien
 * no significa que nadie lo haya firmado.
 *
 * **Aprobar es lo que emite.** `Aprobado` es el único estado que lleva número, y
 * llegar a él es lo que congela el PDF y lo mueve a `emitidas/`. El motivo no es
 * de flujo sino físico: la portada se congela en `instantanea` al generar y el
 * trigger vuelve la fila inmutable en cuanto tiene número, así que una firma
 * posterior no podría salir impresa en el documento que se entrega.
 *
 * **Cinco y no los cuatro de la § 2.2.** La especificación no tiene ninguno para
 * «la dirección lo ha mirado y ha dicho que no», y sin él una versión tumbada se
 * queda en «pendiente de firma» para siempre. `Rechazado` exige motivo, igual que
 * `EstadoTarea::Descartada`: descartar es una decisión, y una decisión sin motivo
 * escrito no se puede auditar.
 *
 * `Obsoleto` es el hermano de `EstadoImplantacion::NoAplica`: **lo pone el
 * sistema**, al aprobarse la versión siguiente, y nunca una persona. Por eso no
 * figura en ninguna lista de transiciones permitidas.
 */
#[TypeScript]
enum EstadoDocumental: string
{
    case Borrador = 'borrador';
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case Obsoleto = 'obsoleto';

    /**
     * A qué estados se puede pasar desde éste por decisión de una persona.
     *
     * Se permite volver a `Borrador` desde revisión y desde rechazo, porque las
     * dos vueltas atrás son reales: retirar algo de revisión para seguir
     * escribiéndolo, y retomar lo que la dirección tumbó. Lo que no se permite es
     * salir de `Aprobado` o de `Obsoleto`: uno está entregado y el otro es
     * histórico.
     *
     * Y de `Rechazado` se va **también** a `EnRevision` directamente, sin pasar
     * por `Borrador`: «lo he corregido, míralo otra vez» es el camino normal
     * después de un rechazo, y obligar a un paso intermedio que nadie entiende
     * sólo añade un botón más para llegar al mismo sitio.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Borrador => [self::EnRevision],
            self::EnRevision => [self::Aprobado, self::Rechazado, self::Borrador],
            self::Rechazado => [self::EnRevision, self::Borrador],

            // Sale de aquí solo, cuando se aprueba la versión siguiente.
            self::Aprobado => [],

            self::Obsoleto => [],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Si la fila lleva número y por tanto es inmutable y está entregada. */
    public function estaEmitida(): bool
    {
        return $this === self::Aprobado || $this === self::Obsoleto;
    }

    /** Si todavía se puede regenerar el PDF sobre esta fila. */
    public function esRegenerable(): bool
    {
        return ! $this->estaEmitida();
    }

    public function esTerminal(): bool
    {
        return $this === self::Obsoleto;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EnRevision => 'En revisión',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
            self::Obsoleto => 'Obsoleto',
        };
    }

    /**
     * El icono con el que se reconoce sin leer la etiqueta.
     *
     * `Rechazado` y `Obsoleto` comparten tono gris, así que el icono es lo único
     * que los separa: `FileX` es una decisión y `Archive` es historia.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Borrador => 'PenLine',
            self::EnRevision => 'Eye',
            self::Aprobado => 'FileCheck',
            self::Rechazado => 'FileX',
            self::Obsoleto => 'Archive',
        };
    }

    /**
     * El tono del dominio con el que se pinta, que no es un color.
     *
     * `EnRevision` estrena `--estado-en-revision`, el violeta que DESIGN.md tenía
     * declarado **sin flujo detrás**: éste es el flujo. Es además el único sitio
     * donde el violeta entra en un badge de estado, y entra porque revisar y
     * auditar es justo lo que ese acento tiene reservado.
     *
     * `Rechazado` NO gasta rojo. Que la dirección tumbe una versión es una
     * decisión legítima, no un incumplimiento — el mismo criterio por el que
     * `DecisionRiesgo` no se pinta de alarma cuando se acepta un riesgo alto.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Borrador => 'no_iniciado',
            self::EnRevision => 'en_revision',
            self::Aprobado => 'implantado',
            self::Rechazado, self::Obsoleto => 'no_aplica',
        };
    }
}
