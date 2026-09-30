<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion;

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use App\Domain\RevisionDireccion\Excepciones\ActaNoPreparable;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * El acta de una revisión: la encuentra o la crea.
 *
 * **Una por revisión, y lo garantiza la base** (`documentos_revision_direccion_unica`).
 * Calcada de `PrepararInformeAuditoria` y por el mismo motivo: la revisión de 2027
 * tiene su propia acta y la de 2026 conserva la suya. Si hay que corregir la de
 * 2026, se reabre la revisión, se corrige, se vuelve a aprobar y se emite una
 * versión nueva **del mismo documento**, que enseña la anterior con su huella.
 *
 * **Sólo con la revisión aprobada.** Antes, el acta imprimiría unas entradas que
 * todavía no se han congelado. El generador lo vuelve a comprobar al construir,
 * porque entre preparar y generar se puede reabrir.
 *
 * Nace con los textos base de la organización y con la periodicidad anual de
 * revisión que llevaba la serie: lo que vence no es la reunión sino la revisión
 * de su acta, y eso lo recoge `Fuente::Documento`.
 */
final class PrepararActa
{
    public function __construct(private readonly MaterializarSecciones $materializar) {}

    public function __invoke(RevisionDireccion $revision, ?User $responsable = null): Documento
    {
        $existente = $revision->acta()->first();

        if ($existente !== null) {
            return $existente;
        }

        if ($revision->estado !== EstadoRevision::Aprobada) {
            throw ActaNoPreparable::sinAprobar($revision);
        }

        return DB::transaction(function () use ($revision, $responsable): Documento {
            $documento = Documento::query()->create([
                'sistema_id' => null,
                'revision_direccion_id' => $revision->id,
                'tipo' => TipoDocumento::ActaRevision->value,
                'codigo' => $this->codigoLibre('ACT-'.$revision->codigo),
                'titulo' => 'Acta de revisión por la dirección — '.$revision->codigo,
                'clasificacion' => ClasificacionDocumental::UsoInterno->value,
                'responsable_id' => $responsable?->id,
                'periodicidad_revision_meses' => 12,
            ]);

            ($this->materializar)($documento);

            return $documento->refresh();
        });
    }

    /** El código propuesto, o el primero libre con sufijo si ya lo usa otro documento. */
    private function codigoLibre(string $base): string
    {
        $codigo = $base;
        $sufijo = 2;

        while (Documento::query()->where('codigo', $codigo)->exists()) {
            $codigo = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $codigo;
    }
}
