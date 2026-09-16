<?php

declare(strict_types=1);

namespace Database\Factories\Auditoria;

use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Implantacion\Models\Implantacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una línea de checklist sintética.
 *
 * Nace `pendiente` y sin congelar, que es como sale de `PrecargarChecklist`: los
 * dos campos congelados los escribe `CerrarAuditoria` y nadie más.
 *
 * @extends Factory<AuditoriaPunto>
 */
class AuditoriaPuntoFactory extends Factory
{
    protected $model = AuditoriaPunto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auditoria_id' => Auditoria::factory(),
            'implantacion_id' => Implantacion::factory(),
            'resultado' => ResultadoPunto::Pendiente->value,
            'nota' => null,
            'exigencia_congelada' => null,
            'estado_congelado' => null,
        ];
    }

    public function con(ResultadoPunto $resultado): self
    {
        return $this->state(fn (): array => ['resultado' => $resultado->value]);
    }
}
