<?php

declare(strict_types=1);

namespace Database\Factories\Conformidad;

use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Enums\ViaConformidad;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Conformidades sintéticas. Ni una real de ningún cliente.
 *
 * Nace **en preparación y de categoría básica**, que es el único estado que no
 * arrastra nada: sin versión, sin fechas y sin distintivo, que los `CHECK` de
 * coherencia acoplan al estado. La autoevaluación se crea cerrada y **del mismo
 * sistema**: una de otro sería un dato que la tabla no impide pero que el dominio
 * nunca produce.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto. Lo clava `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<Conformidad>
 */
class ConformidadFactory extends Factory
{
    protected $model = Conformidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sistema_id' => Sistema::factory(),
            'via' => ViaConformidad::Declaracion->value,
            'categoria' => CategoriaEns::Basica->value,
            'estado' => EstadoConformidad::EnPreparacion->value,
            'auditoria_id' => static fn (array $atributos): int => Auditoria::factory()
                ->deTipo(TipoAuditoria::Autoevaluacion)
                ->cerrada()
                ->paraSistema((int) $atributos['sistema_id'])
                ->create()
                ->id,
            'documento_version_id' => null,
            'fecha_declaracion' => null,
            'vigente_hasta' => null,
            'distintivo_url' => null,
            'distintivo_publicado_en' => null,
            'distintivo_evidencia_id' => null,
            'entidad_certificadora' => null,
            'numero_certificado' => null,
        ];
    }

    public function paraSistema(int $sistemaId): self
    {
        return $this->state(fn (): array => ['sistema_id' => $sistemaId]);
    }

    /**
     * Declarada, con la versión que la respalda y su vigencia bienal.
     *
     * La versión la pone quien llama: una declarada sin versión no se puede
     * insertar, y fabricar un documento aquí escondería de qué sistema es.
     */
    public function declarada(int $versionId, ?Carbon $fecha = null): self
    {
        $fecha ??= Carbon::today();

        return $this->state(fn (): array => [
            'estado' => EstadoConformidad::Declarada->value,
            'documento_version_id' => $versionId,
            'fecha_declaracion' => $fecha,
            'vigente_hasta' => $fecha->copy()->addMonthsNoOverflow(Conformidad::VIGENCIA_MESES),
        ]);
    }

    public function publicada(int $versionId, ?Carbon $fecha = null): self
    {
        $fecha ??= Carbon::today();

        return $this->declarada($versionId, $fecha)->state(fn (): array => [
            'estado' => EstadoConformidad::Publicada->value,
            'distintivo_url' => 'https://www.ejemplo.test/seguridad/ens',
            'distintivo_publicado_en' => $fecha,
        ]);
    }
}
