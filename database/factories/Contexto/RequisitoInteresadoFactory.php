<?php

declare(strict_types=1);

namespace Database\Factories\Contexto;

use App\Domain\Contexto\Enums\NaturalezaRequisito;
use App\Domain\Contexto\Models\RequisitoInteresado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requisitos de partes interesadas sintéticos. Ni uno real de ningún cliente.
 *
 * Nace como **expectativa**, que es la naturaleza que no obliga: así una fila de
 * prueba no entra por defecto en el recuento de «obligaciones sin cubrir», que es
 * el indicador que este módulo lleva al panel.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion`.
 *
 * @extends Factory<RequisitoInteresado>
 */
class RequisitoInteresadoFactory extends Factory
{
    protected $model = RequisitoInteresado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parte_interesada_id' => ParteInteresadaFactory::new(),
            'descripcion' => fake()->sentence(),
            'naturaleza' => NaturalezaRequisito::Expectativa->value,
            'es_climatico' => false,
            'referencia' => null,
            'como_se_atiende' => null,
        ];
    }

    public function deNaturaleza(NaturalezaRequisito $naturaleza): self
    {
        return $this->state(fn (): array => ['naturaleza' => $naturaleza->value]);
    }

    public function legal(string $referencia = 'RD 311/2022, anexo II'): self
    {
        return $this->state(fn (): array => [
            'naturaleza' => NaturalezaRequisito::Legal->value,
            'referencia' => $referencia,
        ]);
    }
}
