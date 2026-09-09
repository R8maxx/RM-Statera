<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\Models\Evidencia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Datos sintéticos. Ni un fichero ni un dato real de ningún cliente.
 *
 * Por defecto la evidencia es de URL y no de fichero: así los tests que no van
 * de almacenamiento no necesitan un disco falso, y la restricción de la base
 * —o fichero o URL, exactamente uno— se respeta sin ceremonia.
 *
 * @extends Factory<Evidencia>
 */
class EvidenciaFactory extends Factory
{
    protected $model = Evidencia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => 'Evidencia '.fake()->unique()->numberBetween(1, 9999),
            'tipo' => fake()->randomElement(TipoEvidencia::cases())->value,
            'descripcion' => null,
            'url_externa' => 'https://ejemplo.interno/pruebas/'.fake()->uuid(),
            'fecha_obtencion' => Carbon::today()->subMonths(2),
            'fecha_caducidad' => null,
            'periodicidad_renovacion' => null,
            'responsable_id' => null,
        ];
    }

    public function deTipo(TipoEvidencia $tipo): self
    {
        return $this->state(fn (): array => ['tipo' => $tipo->value]);
    }

    /** Con fichero en lugar de URL. Necesita `Storage::fake('evidencias')`. */
    public function conFichero(string $nombre = 'captura.png'): self
    {
        return $this->state(fn (): array => [
            'url_externa' => null,
            'disco' => 'evidencias',
            'ruta' => '1/2026/'.fake()->uuid().'.png',
            'nombre_fichero' => $nombre,
            'mime' => 'image/png',
            'tamano' => 2048,
            'hash_sha256' => hash('sha256', $nombre),
        ]);
    }

    public function caducada(): self
    {
        return $this->state(fn (): array => ['fecha_caducidad' => Carbon::today()->subDay()]);
    }

    public function conPeriodicidad(PeriodicidadRenovacion $periodicidad): self
    {
        return $this->state(fn (): array => ['periodicidad_renovacion' => $periodicidad->value]);
    }
}
