<?php

declare(strict_types=1);

namespace Database\Factories\Comunicacion;

use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Comunicacion\Enums\SentidoComunicacion;
use App\Domain\Comunicacion\Enums\TipoRetroalimentacion;
use App\Domain\Comunicacion\Models\Comunicacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Comunicaciones sintéticas. Nace **emitida y suelta**, sin línea del plan, que
 * es la que menos arrastra. `recibida()` la convierte en retroalimentación.
 *
 * Para lo emitido que cumple una línea del plan, mejor `RegistrarComunicacion`:
 * es quien congela `cubre_hasta`.
 *
 * @extends Factory<Comunicacion>
 */
class ComunicacionFactory extends Factory
{
    protected $model = Comunicacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sentido' => SentidoComunicacion::Emitida->value,
            'fecha' => Carbon::today()->toDateString(),
            'asunto' => fake()->sentence(4),
            'canal' => CanalComunicacion::Correo->value,
        ];
    }

    public function recibida(TipoRetroalimentacion $tipo = TipoRetroalimentacion::Queja, ?string $respuesta = null): self
    {
        return $this->state(fn (): array => [
            'sentido' => SentidoComunicacion::Recibida->value,
            'tipo_recibida' => $tipo->value,
            'respuesta' => $respuesta,
        ]);
    }
}
