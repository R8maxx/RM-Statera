<?php

declare(strict_types=1);

namespace Database\Factories\Contexto;

use App\Domain\Contexto\Enums\Ambito;
use App\Domain\Contexto\Enums\TipoParteInteresada;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Partes interesadas sintéticas. Ni una real de ningún cliente.
 *
 * Nace **vigente**, con el ámbito coherente con el tipo aunque la columna no lo
 * exija: una parte de prueba con un regulador marcado «interno» no rompe nada y
 * ensucia cualquier test que mire el reparto por ámbito.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion`.
 *
 * @extends Factory<ParteInteresada>
 */
class ParteInteresadaFactory extends Factory
{
    protected $model = ParteInteresada::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipo = fake()->randomElement(TipoParteInteresada::cases());

        return [
            'codigo' => sprintf('PI-%02d', fake()->unique()->numberBetween(1, 9999)),
            'nombre' => fake()->company(),
            'tipo' => $tipo->value,
            'ambito' => ($tipo->ambitoSugerido() ?? Ambito::Externo)->value,
            'descripcion' => fake()->sentence(),
            'responsable_id' => null,
            'analisis_alta_id' => AnalisisContextoFactory::new(),
            'analisis_baja_id' => null,
            'motivo_baja' => null,
        ];
    }

    public function deTipo(TipoParteInteresada $tipo): self
    {
        return $this->state(fn (): array => [
            'tipo' => $tipo->value,
            'ambito' => ($tipo->ambitoSugerido() ?? Ambito::Externo)->value,
        ]);
    }

    public function retirada(AnalisisContexto $analisis, string $motivo = 'Dejó de tener relación con la organización.'): self
    {
        return $this->state(fn (): array => [
            'analisis_baja_id' => $analisis->id,
            'motivo_baja' => $motivo,
        ]);
    }
}
