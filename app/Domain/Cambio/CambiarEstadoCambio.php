<?php

declare(strict_types=1);

namespace App\Domain\Cambio;

use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Excepciones\TransicionDeCambioNoPermitida;
use App\Domain\Cambio\Models\CambioSgsi;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de estado de un cambio del SGSI, con su histórico, sus fechas y su firma.
 *
 * **Las fechas y la firma las pone el dominio y nunca el formulario**, como en
 * `CambiarEstadoObjetivo`: la base acopla el estado con el plazo, la firma, la
 * fecha de implantación, la de cierre y la revisión mediante `CHECK`, y dejar
 * que las escriba quien llame es que el día que un importador apruebe un cambio
 * falle con un error de restricción que no dice qué faltaba.
 *
 * **Aprobar exige plazo**, porque un cambio planificado sin fecha no está
 * planificado. **Revisar exige decir si sirvió**, y ese texto va a la columna
 * `revision` además de al histórico. **Descartar y reabrir exigen motivo.**
 *
 * **Volver a propuesto suelta la firma entera**, y reabrir no la reescribe: la
 * misma regla que en objetivos. El permiso de aprobar lo comprueba el
 * controlador, porque la ruta es una sola.
 */
final class CambiarEstadoCambio
{
    public function __construct(private readonly RegistroTransicionesCambio $registro) {}

    public function __invoke(
        CambioSgsi $cambio,
        EstadoCambio $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): CambioSgsi {
        $actual = $cambio->estado;
        $nota = $nota !== null && trim($nota) !== '' ? trim($nota) : null;

        if ($actual === $nuevo) {
            return $cambio;
        }

        if (! $actual->permite($nuevo)) {
            throw new TransicionDeCambioNoPermitida($actual, $nuevo);
        }

        if ($nuevo->esComprometido() && $cambio->fecha_prevista === null) {
            throw new TransicionDeCambioNoPermitida(
                $actual,
                $nuevo,
                'Un cambio aprobado tiene que decir para cuándo: sin fecha no está planificado.',
            );
        }

        // Una firma sin firmante es la que el `CHECK` de coherencia rechaza; se
        // dice aquí en castellano.
        if ($nuevo === EstadoCambio::Aprobado && $cambio->aprobado_por_id === null && $usuario === null) {
            throw new TransicionDeCambioNoPermitida($actual, $nuevo, 'Aprobar un cambio exige saber quién lo firma.');
        }

        if ($nuevo->exigeNota($actual) && $nota === null) {
            throw new TransicionDeCambioNoPermitida($actual, $nuevo, $this->motivoQueFalta($nuevo));
        }

        return DB::transaction(function () use ($cambio, $actual, $nuevo, $usuario, $nota): CambioSgsi {
            $cambio->update($this->columnas($cambio, $nuevo, $usuario, $nota));

            $this->registro->registrar($cambio, $actual, $nuevo, $usuario, $nota);

            return $cambio->refresh();
        });
    }

    private function motivoQueFalta(EstadoCambio $nuevo): string
    {
        return match ($nuevo) {
            EstadoCambio::Descartado => 'Descartar un cambio exige decir por qué: es una decisión que el auditor puede cuestionar.',
            EstadoCambio::Revisado => 'Revisar un cambio exige decir si consiguió lo que pretendía.',
            default => 'Reabrir exige decir qué cambia: sin eso, el histórico no explica el ir y venir.',
        };
    }

    /**
     * Las columnas que dependen del estado. Ver la cabecera.
     *
     * @return array<string, mixed>
     */
    private function columnas(CambioSgsi $cambio, EstadoCambio $nuevo, ?User $usuario, ?string $nota): array
    {
        $hoy = Carbon::today();

        return match ($nuevo) {
            // Vuelve al borrador: deja de estar firmado, hecho y cerrado.
            EstadoCambio::Propuesto => [
                'estado' => $nuevo->value,
                'aprobado_por_id' => null,
                'aprobado_en' => null,
                'fecha_implantacion' => null,
                'fecha_cierre' => null,
                'revision' => null,
            ],

            // La firma sólo se estampa si no había una.
            EstadoCambio::Aprobado => [
                'estado' => $nuevo->value,
                'aprobado_por_id' => $cambio->aprobado_por_id ?? $usuario?->id,
                'aprobado_en' => $cambio->aprobado_en ?? Carbon::now(),
                'fecha_implantacion' => null,
                'fecha_cierre' => null,
            ],

            // Volver de revisado a implantado conserva el día en que se hizo.
            EstadoCambio::Implantado => [
                'estado' => $nuevo->value,
                'fecha_implantacion' => $cambio->fecha_implantacion ?? $hoy,
                'fecha_cierre' => null,
                'revision' => null,
            ],

            EstadoCambio::Revisado => [
                'estado' => $nuevo->value,
                'fecha_cierre' => $hoy,
                'revision' => $nota,
            ],

            EstadoCambio::Descartado => [
                'estado' => $nuevo->value,
                'fecha_cierre' => $hoy,
            ],
        };
    }
}
