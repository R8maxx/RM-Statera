<?php

declare(strict_types=1);

namespace App\Domain\Conformidad;

use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Excepciones\ConformidadNoPermitida;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Evidencia\Models\Evidencia;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El tercer paso: el distintivo está publicado en la web de la organización.
 *
 * **Se registra, no se sirve.** El distintivo lo publica la organización junto a
 * su Declaración de Conformidad (CCN-STIC 809); Statera apunta dónde, desde
 * cuándo y, si se aporta, la evidencia —normalmente una captura— de que estaba
 * ahí. No abre ninguna ruta pública: la herramienta entra en el alcance del SGSI
 * y una página sin sesión sería la primera puerta de fuera hacia dentro.
 *
 * La evidencia se resuelve por el modelo para que pase por el scope de
 * organización: la de otro cliente no existe.
 */
final class RegistrarPublicacionDistintivo
{
    public function __construct(private readonly RegistroTransicionesConformidad $registro) {}

    public function __invoke(
        Conformidad $conformidad,
        string $url,
        Carbon $fecha,
        ?int $evidenciaId = null,
        ?User $usuario = null,
    ): Conformidad {
        $actual = $conformidad->estado;

        if (! $actual->permite(EstadoConformidad::Publicada)) {
            throw ConformidadNoPermitida::transicion($actual, EstadoConformidad::Publicada);
        }

        $url = trim($url);

        if (preg_match('#^https?://\S+$#i', $url) !== 1) {
            throw ConformidadNoPermitida::urlNoValida();
        }

        if ($fecha->isAfter(Carbon::today())
            || ($conformidad->fecha_declaracion !== null && $fecha->isBefore($conformidad->fecha_declaracion))) {
            throw ConformidadNoPermitida::fechaNoValida();
        }

        $evidencia = $evidenciaId === null ? null : Evidencia::query()->findOrFail($evidenciaId);

        return DB::transaction(function () use ($conformidad, $actual, $url, $fecha, $evidencia, $usuario): Conformidad {
            $conformidad->update([
                'estado' => EstadoConformidad::Publicada->value,
                'distintivo_url' => $url,
                'distintivo_publicado_en' => $fecha->copy()->startOfDay(),
                'distintivo_evidencia_id' => $evidencia?->id,
            ]);

            $this->registro->registrar($conformidad, $actual, EstadoConformidad::Publicada, $usuario, "Publicado en {$url}.");

            return $conformidad->refresh();
        });
    }
}
