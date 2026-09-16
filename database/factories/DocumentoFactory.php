<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos sintéticos. Ni un documento ni un dato real de ningún cliente.
 *
 * `sistema_id` se deja al llamante: el `CHECK` de la tabla exige sistema para
 * las dos declaraciones, y crearlo aquí a ciegas produciría un sistema del marco
 * equivocado la mitad de las veces.
 *
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'DOC-'.fake()->unique()->numberBetween(1, 9999),
            'titulo' => 'Declaración de Aplicabilidad',
            'tipo' => TipoDocumento::SoaIso->value,
            'clasificacion' => ClasificacionDocumental::UsoInterno->value,
            'responsable_id' => null,
            'notas' => null,
        ];
    }

    public function deTipo(TipoDocumento $tipo): self
    {
        return $this->state(fn (): array => [
            'tipo' => $tipo->value,
            'titulo' => $tipo->etiqueta(),
        ]);
    }

    public function soa(): self
    {
        return $this->deTipo(TipoDocumento::SoaIso)->state(fn (): array => ['codigo' => 'SOA-SGSI-01']);
    }

    public function dda(): self
    {
        return $this->deTipo(TipoDocumento::DdaEns)->state(fn (): array => ['codigo' => 'DDA-ENS-01']);
    }

    /**
     * Una política: documento redactado, de la organización entera.
     *
     * Sin sistema a propósito —el `CHECK` sólo lo exige para las declaraciones— y
     * con acuse exigido y periodicidad, que es el caso que de verdad ejercita el
     * § 4.5: nadie acusa recibo de una SoA.
     */
    public function politica(): self
    {
        return $this->deTipo(TipoDocumento::Politica)->state(fn (): array => [
            'codigo' => 'POL-SEG-01',
            'titulo' => 'Política de Seguridad de la Información',
            'periodicidad_revision_meses' => 12,
            'exige_acuse' => true,
        ]);
    }

    public function conPeriodicidad(?int $meses): self
    {
        return $this->state(fn (): array => ['periodicidad_revision_meses' => $meses]);
    }

    public function paraSistema(int $sistemaId): self
    {
        return $this->state(fn (): array => ['sistema_id' => $sistemaId]);
    }
}
