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

    /** El plan de adecuación: calculado como las dos declaraciones, y también con sistema. */
    public function plan(): self
    {
        return $this->deTipo(TipoDocumento::PlanAdecuacionEns)
            ->state(fn (): array => ['codigo' => 'PLA-ENS-01']);
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

    /**
     * El análisis del contexto: **calculado y sin sistema**.
     *
     * Es el único estado que ejercita la frontera nueva. Hasta él, «calculado» y
     * «exige sistema» eran lo mismo, y un test que quisiera comprobar que el
     * `CHECK` reescrito deja pasar un calculado de ámbito organizativo no tenía
     * con qué hacerlo.
     */
    public function analisisContexto(): self
    {
        return $this->deTipo(TipoDocumento::AnalisisContexto)->state(fn (): array => [
            'codigo' => 'CTX-SGSI-01',
            'titulo' => 'Análisis del contexto de la organización',
            'sistema_id' => null,
            'periodicidad_revision_meses' => 12,
        ]);
    }

    /**
     * El acta de revisión por la dirección: **calculada y sin sistema**, como el
     * análisis del contexto.
     *
     * Es el segundo documento de ámbito organizativo, y el que confirma que la
     * frontera que abrió aquél no era un caso aislado: lo que la dirección revisa
     * es el sistema de gestión entero, no un sistema concreto.
     */
    public function actaRevision(): self
    {
        return $this->deTipo(TipoDocumento::ActaRevision)->state(fn (): array => [
            'codigo' => 'ACT-REV-01',
            'titulo' => 'Acta de revisión por la dirección',
            'sistema_id' => null,
            'periodicidad_revision_meses' => 12,
        ]);
    }

    /**
     * El plan de continuidad: documento redactado, de la organización entera.
     *
     * Sin sistema a propósito, como `politica()`: un plan de continuidad puede
     * cubrir servicios de varios sistemas a la vez.
     */
    public function planContinuidad(): self
    {
        return $this->deTipo(TipoDocumento::PlanContinuidad)->state(fn (): array => [
            'codigo' => 'PLN-CONT-01',
            'titulo' => 'Plan de continuidad',
            'sistema_id' => null,
        ]);
    }

    /**
     * La Declaración de Conformidad del ENS: calculada y **con sistema**, como la
     * DdA. El sistema lo pone quien llama con `paraSistema()`, porque la
     * conformidad que la respalda es de ese sistema y no de uno cualquiera.
     */
    public function declaracionConformidad(): self
    {
        return $this->deTipo(TipoDocumento::DeclaracionConformidadEns)->state(fn (): array => [
            'codigo' => 'DDC-ENS-01',
            'titulo' => 'Declaración de Conformidad con el ENS',
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
