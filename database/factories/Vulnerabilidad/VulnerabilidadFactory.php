<?php

declare(strict_types=1);

namespace Database\Factories\Vulnerabilidad;

use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\OrigenVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\Severidad;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Vulnerabilidades sintéticas. Ni una real de ningún cliente, y ningún CVE de
 * verdad: los tests no dependen de que un identificador exista.
 *
 * Nace **abierta, sin CVSS y de severidad media**, que es el estado que no
 * arrastra ningún `CHECK`: el de CVSS exige que la severidad case con la
 * puntuación, y los de aceptada y cerrada exigen su firma. `fecha_limite` se
 * deja a nulo: la pone `PlazoRemediacion`, o el test a mano.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion`.
 *
 * @extends Factory<Vulnerabilidad>
 */
class VulnerabilidadFactory extends Factory
{
    protected $model = Vulnerabilidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 9999);

        return [
            'codigo' => sprintf('VUL-2026-%04d', $numero),
            'titulo' => "Vulnerabilidad sintética {$numero}",
            'descripcion' => null,
            'cve' => null,
            'cvss_puntuacion' => null,
            'cvss_vector' => null,
            'severidad' => Severidad::Media->value,
            'origen' => OrigenVulnerabilidad::Escaneo->value,
            'fecha_deteccion' => Carbon::today()->subDays(3),
            'fecha_limite' => null,
            'estado' => EstadoVulnerabilidad::Abierta->value,
            'responsable_id' => null,
        ];
    }
}
