<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Plataforma\Enums\PeriodoFacturacion;
use App\Domain\Plataforma\Models\Plan;
use Illuminate\Support\Carbon;

/**
 * Lo que costaría pasar a un plan, y si se puede (punto 51).
 *
 * Lo calcula `PresupuestarCambioPlan` y lo leen dos sitios: la página de
 * planes, que lo enseña antes de confirmar, y `ContratarPlan`, que lo vuelve a
 * calcular dentro de la transacción y guarda exactamente eso. **Una sola
 * cuenta para las dos**: si la pantalla calculara por su lado, el importe que
 * se enseña y el que queda en el histórico podrían no ser el mismo.
 *
 * Los importes van en céntimos y sin IVA. `ajusteCentimos` lleva signo: positivo
 * es lo que se pagaría hoy, negativo el saldo que queda a favor al bajar.
 */
final readonly class PresupuestoCambioPlan
{
    /** Es el plan y el periodo que ya se tienen, y siguen vigentes. */
    public const ES_EL_ACTUAL = 'es_el_actual';

    /** Lo que se usa hoy no cabe en los límites del plan. */
    public const NO_CABE = 'no_cabe';

    public function __construct(
        public Plan $plan,
        public PeriodoFacturacion $periodo,
        /** Por qué no se puede, o nulo si se puede. */
        public ?string $bloqueo,
        public int $sobranCuentas,
        public int $sobranSistemas,
        /** Si empieza un periodo hoy; si no, se conserva la fecha de renovación. */
        public bool $periodoNuevo,
        /** Días que quedaban del periodo en curso y que se prorratean. */
        public int $diasRestantes,
        public int $ajusteCentimos,
        public Carbon $iniciaEn,
        public Carbon $venceEn,
        /** Lo que se pagará en cada renovación con este plan y este periodo. */
        public int $siguienteCobroCentimos,
    ) {}

    public function permitido(): bool
    {
        return $this->bloqueo === null;
    }

    public function importeHoyCentimos(): int
    {
        return max(0, $this->ajusteCentimos);
    }

    public function saldoAFavorCentimos(): int
    {
        return max(0, -$this->ajusteCentimos);
    }
}
