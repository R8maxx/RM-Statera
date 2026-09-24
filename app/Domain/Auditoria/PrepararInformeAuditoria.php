<?php

declare(strict_types=1);

namespace App\Domain\Auditoria;

use App\Domain\Auditoria\Enums\EstadoAuditoria;
use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Auditoria\Excepciones\InformeNoPreparable;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * El informe de una auditoría: lo encuentra o lo crea.
 *
 * **Uno por auditoría, y lo garantiza la base** (`documentos_auditoria_unica`). No
 * es una serie que se renueva, como la Declaración de Conformidad: la auditoría
 * de 2027 tiene su propio informe, y la de 2026 conserva el suyo. Si hay que
 * corregir el de 2026, se reabre la auditoría, se corrige, se cierra y se emite
 * una versión nueva **del mismo documento**, que enseña la anterior con su
 * huella.
 *
 * **Sólo con la auditoría cerrada.** Antes, el informe diría cosas que la
 * checklist todavía puede cambiar. El generador lo vuelve a comprobar al
 * construir, porque entre preparar y generar se puede reabrir.
 *
 * Nace con los textos base de la organización, como cualquier documento creado
 * desde su formulario (`MaterializarSecciones`).
 */
final class PrepararInformeAuditoria
{
    public function __construct(private readonly MaterializarSecciones $materializar) {}

    public function __invoke(Auditoria $auditoria, ?User $responsable = null): Documento
    {
        $existente = $auditoria->informe()->first();

        if ($existente !== null) {
            return $existente;
        }

        if (! $auditoria->tipo->admiteInforme()) {
            throw InformeNoPreparable::externa($auditoria);
        }

        if ($auditoria->estado !== EstadoAuditoria::Cerrada) {
            throw InformeNoPreparable::sinCerrar($auditoria);
        }

        return DB::transaction(function () use ($auditoria, $responsable): Documento {
            $documento = Documento::query()->create([
                'sistema_id' => $auditoria->sistema_id,
                'auditoria_id' => $auditoria->id,
                'tipo' => TipoDocumento::InformeAuditoria->value,
                'codigo' => $this->codigoLibre('INF-'.$auditoria->codigo),
                'titulo' => ($auditoria->tipo === TipoAuditoria::Autoevaluacion
                    ? 'Informe de autoevaluación — '
                    : 'Informe de auditoría interna — ').$auditoria->codigo,
                'clasificacion' => ClasificacionDocumental::UsoInterno->value,
                'responsable_id' => $responsable?->id,
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
