<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Datos sintéticos. Ni una tarea real de ningún cliente.
 *
 * Por defecto la tarea nace abierta y sin fecha de cierre, que es lo que exige el
 * `CHECK` de coherencia: cerrada y sin fecha, o abierta y con ella, son las dos
 * contradicciones que la base no deja escribir.
 *
 * @extends Factory<Tarea>
 */
class TareaFactory extends Factory
{
    protected $model = Tarea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => 'Tarea '.fake()->unique()->numberBetween(1, 9999),
            'descripcion' => null,
            'origen' => OrigenTarea::Propia->value,
            'estado' => EstadoTarea::Pendiente->value,
            'prioridad' => PrioridadTarea::Media->value,
            'responsable_id' => null,
            'fecha_limite' => null,
            'fecha_cierre' => null,
            'coste_estimado' => null,
            'notas' => null,
        ];
    }

    public function enEstado(EstadoTarea $estado): self
    {
        return $this->state(fn (): array => [
            'estado' => $estado->value,
            // El `CHECK` acopla las dos columnas: una cerrada sin fecha de cierre
            // no se puede insertar, y es la restricción que más se olvida.
            'fecha_cierre' => $estado->esCerrada() ? Carbon::today() : null,
        ]);
    }

    public function conOrigen(OrigenTarea $origen): self
    {
        return $this->state(fn (): array => ['origen' => $origen->value]);
    }

    public function conPrioridad(PrioridadTarea $prioridad): self
    {
        return $this->state(fn (): array => ['prioridad' => $prioridad->value]);
    }

    public function paraElDia(string|Carbon $fecha): self
    {
        return $this->state(fn (): array => ['fecha_limite' => $fecha]);
    }

    /** Vencida de verdad: con plazo pasado y todavía abierta. */
    public function vencida(int $dias = 1): self
    {
        return $this->state(fn (): array => [
            'estado' => EstadoTarea::Pendiente->value,
            'fecha_cierre' => null,
            'fecha_limite' => Carbon::today()->subDays($dias),
        ]);
    }

    public function de(User $usuario): self
    {
        return $this->state(fn (): array => ['responsable_id' => $usuario->id]);
    }
}
