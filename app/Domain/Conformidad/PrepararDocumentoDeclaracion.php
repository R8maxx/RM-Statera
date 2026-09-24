<?php

declare(strict_types=1);

namespace App\Domain\Conformidad;

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * La serie de la Declaración de Conformidad de un sistema: la encuentra o la crea.
 *
 * **Una serie por sistema, y se reutiliza en cada renovación.** La declaración de
 * 2028 es la v2 de la de 2026, no un documento nuevo: así el control de versiones
 * del PDF enseña la anterior con su huella, que es lo que el auditor compara.
 *
 * Nace **pública** y no de uso interno, a diferencia del resto de documentos
 * calculados: la Declaración de Conformidad se publica en la web junto al
 * distintivo (CCN-STIC 809), y un sello «uso interno» en un papel que se cuelga
 * en internet se contradice a sí mismo.
 *
 * Nace además con los textos base de la organización, como cualquier documento
 * creado desde su formulario (`MaterializarSecciones`).
 */
final class PrepararDocumentoDeclaracion
{
    public function __construct(private readonly MaterializarSecciones $materializar) {}

    public function __invoke(Sistema $sistema, ?User $responsable = null): Documento
    {
        $existente = self::delSistema($sistema);

        if ($existente !== null) {
            return $existente;
        }

        return DB::transaction(function () use ($sistema, $responsable): Documento {
            $documento = Documento::query()->create([
                'sistema_id' => $sistema->id,
                'tipo' => TipoDocumento::DeclaracionConformidadEns->value,
                'codigo' => $this->codigoLibre('DDC-'.$sistema->codigo),
                'titulo' => 'Declaración de Conformidad con el ENS — '.$sistema->nombre,
                'clasificacion' => ClasificacionDocumental::Publico->value,
                'responsable_id' => $responsable?->id,
            ]);

            ($this->materializar)($documento);

            return $documento->refresh();
        });
    }

    /** La serie ya creada de este sistema, si la hay. */
    public static function delSistema(Sistema $sistema): ?Documento
    {
        return Documento::query()
            ->where('sistema_id', $sistema->id)
            ->where('tipo', TipoDocumento::DeclaracionConformidadEns->value)
            ->orderBy('id')
            ->first();
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
