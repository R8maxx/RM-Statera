<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Activo\Models\RevisionInventario;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Datos sintéticos. Ni una desviación real de ningún cliente.
 *
 * Nace SIN desviaciones anotadas, que es el caso ambiguo —revisión limpia o
 * revisión a medias— y el que conviene tener a mano en los tests.
 *
 * @extends Factory<RevisionInventario>
 */
class RevisionInventarioFactory extends Factory
{
    protected $model = RevisionInventario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => Carbon::today()->subMonth(),
            'responsable_id' => null,
            'alcance' => 'Revisión de ejemplo',
            'altas' => 0,
            'bajas' => 0,
            'desviaciones' => null,
            'acciones' => null,
        ];
    }

    public function de(Organizacion $organizacion): self
    {
        return $this->state(fn (): array => ['organizacion_id' => $organizacion->id]);
    }

    public function el(Carbon $fecha): self
    {
        return $this->state(fn (): array => ['fecha' => $fecha->toDateString()]);
    }

    public function conHallazgos(string $desviaciones = 'Dos equipos sin nº de serie.'): self
    {
        return $this->state(fn (): array => [
            'desviaciones' => $desviaciones,
            'acciones' => 'Completar los datos que faltan antes de la próxima revisión.',
        ]);
    }
}
