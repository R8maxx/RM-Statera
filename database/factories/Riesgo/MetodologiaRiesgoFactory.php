<?php

declare(strict_types=1);

namespace Database\Factories\Riesgo;

use App\Domain\Riesgo\MetodologiaDeFabrica;
use App\Domain\Riesgo\Models\MetodologiaRiesgo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Una metodología guardada, que por defecto es la de fábrica con otro nombre.
 *
 * El nombre cambia a propósito: una fila idéntica a la de fábrica es justo la que
 * `GuardarMetodologia` borra, así que una factoría que la reprodujera literalmente
 * crearía filas que la aplicación no habría dejado crear.
 *
 * @extends Factory<MetodologiaRiesgo>
 */
class MetodologiaRiesgoFactory extends Factory
{
    protected $model = MetodologiaRiesgo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Metodología de la organización',
            'referencia' => 'MAGERIT v3',
            'escala_probabilidad' => MetodologiaDeFabrica::probabilidad()->aArray(),
            'escala_impacto' => MetodologiaDeFabrica::impacto()->aArray(),
            'umbral_aceptacion' => MetodologiaDeFabrica::UMBRAL_ACEPTACION,
            'umbral_critico' => MetodologiaDeFabrica::UMBRAL_CRITICO,
            'periodicidad_revision_meses' => MetodologiaDeFabrica::PERIODICIDAD_MESES,
            'aprobada_por_id' => null,
            'aprobada_en' => null,
            'notas' => null,
        ];
    }

    public function conUmbrales(int $aceptacion, int $critico): self
    {
        return $this->state(fn (): array => [
            'umbral_aceptacion' => $aceptacion,
            'umbral_critico' => $critico,
        ]);
    }

    /** Firmada por la dirección. El `CHECK` acopla firma y fecha: van las dos. */
    public function aprobada(?User $por = null, ?Carbon $cuando = null): self
    {
        return $this->state(fn (): array => [
            'aprobada_por_id' => $por instanceof User ? $por->id : User::factory(),
            'aprobada_en' => $cuando ?? Carbon::today(),
        ]);
    }
}
