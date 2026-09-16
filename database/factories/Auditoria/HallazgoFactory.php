<?php

declare(strict_types=1);

namespace Database\Factories\Auditoria;

use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Auditoria\Models\Hallazgo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hallazgos sintéticos.
 *
 * Nace **sin punto**, que es el caso que § 2.2 no contemplaba y que hay que poder
 * montar: un hallazgo sobre el sistema de gestión y no sobre una medida concreta.
 *
 * @extends Factory<Hallazgo>
 */
class HallazgoFactory extends Factory
{
    protected $model = Hallazgo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auditoria_id' => Auditoria::factory(),
            'auditoria_punto_id' => null,
            'tipo' => TipoHallazgo::Observacion->value,
            'descripcion' => 'Hallazgo de prueba.',
        ];
    }

    public function deTipo(TipoHallazgo $tipo): self
    {
        return $this->state(fn (): array => ['tipo' => $tipo->value]);
    }

    public function sobre(AuditoriaPunto $punto): self
    {
        return $this->state(fn (): array => [
            'auditoria_id' => $punto->auditoria_id,
            'auditoria_punto_id' => $punto->id,
        ]);
    }
}
