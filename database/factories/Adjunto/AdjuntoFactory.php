<?php

declare(strict_types=1);

namespace Database\Factories\Adjunto;

use App\Domain\Adjunto\Models\Adjunto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adjunto>
 */
class AdjuntoFactory extends Factory
{
    protected $model = Adjunto::class;

    /**
     * `organizacion_id` NO se declara: lo rellena `PerteneceAOrganizacion` en el
     * evento `creating`, y sólo si viene a nulo. Un valor por defecto aquí lo
     * cortocircuita y la fila nace con un tenant que no es el del contexto, que
     * el `WITH CHECK` de la política rechaza con un error de privilegios que no
     * menciona la palabra «organización». Lo clava `FactoriesSinOrganizacionTest`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->slug(2).'.pdf';

        return [
            'titulo' => fake()->sentence(3),
            'nota' => null,
            'disco' => 'adjuntos',
            'ruta' => sprintf('1/2026/%s', $nombre),
            'nombre_fichero' => $nombre,
            'mime' => 'application/pdf',
            'tamano' => fake()->numberBetween(1024, 4_194_304),
            'hash_sha256' => hash('sha256', $nombre),
            'subido_por_id' => null,
        ];
    }
}
