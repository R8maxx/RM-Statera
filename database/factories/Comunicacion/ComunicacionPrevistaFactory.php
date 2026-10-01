<?php

declare(strict_types=1);

namespace Database\Factories\Comunicacion;

use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Líneas sintéticas del plan de comunicación. Ni una real de ningún cliente.
 *
 * Nace **sin cadencia** —«cuando proceda»—, que es lo único que no arrastra
 * nada: ni fecha desde la que contar ni entrada en el calendario. `cada()` le
 * pone la suya.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion`.
 *
 * @extends Factory<ComunicacionPrevista>
 */
class ComunicacionPrevistaFactory extends Factory
{
    protected $model = ComunicacionPrevista::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('PC-%02d', fake()->unique()->numberBetween(1, 9999)),
            'titulo' => fake()->sentence(4),
            'canal' => CanalComunicacion::Correo->value,
            'responsable_id' => null,
            'periodicidad_meses' => null,
            'computa_desde' => null,
        ];
    }

    /** Con cadencia, contando desde `$desde` (hoy por defecto). */
    public function cada(int $meses, ?Carbon $desde = null): self
    {
        return $this->state(fn (): array => [
            'periodicidad_meses' => $meses,
            'computa_desde' => ($desde ?? Carbon::today())->toDateString(),
        ]);
    }

    public function retirada(): self
    {
        return $this->state(fn (): array => [
            'retirada_en' => Carbon::today()->toDateString(),
            'motivo_retirada' => 'Ya no hace falta.',
        ]);
    }
}
