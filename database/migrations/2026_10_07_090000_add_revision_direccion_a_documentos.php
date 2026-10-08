<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El acta deja de ser una serie y pasa a colgar de **su** revisión:
 * `documentos.revision_direccion_id`.
 *
 * Hasta aquí había un solo documento «Acta de revisión por la dirección» cuyas
 * versiones imprimían siempre la última revisión aprobada. Eso mezclaba dos
 * reuniones distintas en la misma serie —la v2 del acta era la reunión de otro
 * año, no una corrección de la v1— y hacía imposible regenerar el acta de una
 * revisión anterior. Y contradecía lo que el propio módulo declara: **cada
 * revisión es un acto con su fecha**, no un estado de cosas que se sustituye.
 * Es el argumento exacto con el que el informe de auditoría estrenó
 * `auditoria_id`, y esta migración lo calca:
 *
 * - **El vínculo va en `documentos` y no en `revisiones_direccion`**: un acta
 *   aprobada es inmutable —su trigger compara la fila entera—, y el documento se
 *   prepara justo después de aprobarla.
 * - **Índice único parcial**: un acta por revisión.
 * - **`CHECK` en las dos direcciones**: ni acta sin revisión ni revisión colgada
 *   de una SoA.
 * - **`ON DELETE NO ACTION`**, el de `constrained()`: borrar una revisión con
 *   acta lo impide antes el controlador, con mensaje.
 *
 * ### Los documentos de acta que ya existían
 *
 * Cada uno se ata a la revisión que imprimió su última versión emitida, si la
 * hay; si no, a la última aprobada de su organización, que es la que habría
 * impreso; y si no hay ninguna aprobada, a la más reciente —sin aprobar no se
 * genera, así que no imprime nada que no deba—. Una organización sin revisiones
 * y con un acta sin emitir se queda sin ella: no hay nada que atar y no había nada
 * que perder. **Con algo emitido, la migración para**: un PDF entregado no se
 * borra para que cuadre un `CHECK`.
 *
 * Se hace por `ContextoOrganizacion::comoMantenimiento()`: sin él, RLS deniega
 * por defecto y los `UPDATE` afectan a cero filas sin fallar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table): void {
            $table->foreignId('revision_direccion_id')->nullable()->after('auditoria_id')->constrained('revisiones_direccion');
        });

        app(ContextoOrganizacion::class)->comoMantenimiento(function (): void {
            $actas = DB::table('documentos')->where('tipo', 'acta_revision')->orderBy('id')->get(['id', 'organizacion_id', 'codigo']);

            foreach ($actas as $acta) {
                $revisionId = $this->revisionPara((int) $acta->id, (int) $acta->organizacion_id);

                if ($revisionId === null) {
                    $emitidas = DB::table('documento_versiones')->where('documento_id', $acta->id)->whereNotNull('numero')->exists();

                    if ($emitidas) {
                        throw new RuntimeException(sprintf(
                            'El acta %s tiene versiones emitidas y su organización no tiene ninguna revisión por la dirección a la que atarla. Resuélvelo a mano antes de migrar.',
                            $acta->codigo,
                        ));
                    }

                    // Versiones, secciones y cuerpo caen en cascada. Un cumplimiento
                    // de obligación que lo cite lo impide su clave foránea, y así
                    // tiene que ser.
                    DB::table('documentos')->where('id', $acta->id)->delete();

                    continue;
                }

                if (DB::table('documentos')->where('revision_direccion_id', $revisionId)->exists()) {
                    throw new RuntimeException(sprintf(
                        'El acta %s y otra acta caen sobre la misma revisión por la dirección. Resuélvelo a mano antes de migrar.',
                        $acta->codigo,
                    ));
                }

                DB::table('documentos')->where('id', $acta->id)->update(['revision_direccion_id' => $revisionId]);
            }
        });

        DB::statement('CREATE UNIQUE INDEX documentos_revision_direccion_unica ON documentos (revision_direccion_id) WHERE revision_direccion_id IS NOT NULL');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_revision_direccion_check CHECK ((tipo = 'acta_revision') = (revision_direccion_id IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_revision_direccion_check');
        DB::statement('DROP INDEX documentos_revision_direccion_unica');

        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revision_direccion_id');
        });
    }

    /** La revisión que le toca a un acta que ya existía: ver la cabecera. */
    private function revisionPara(int $documentoId, int $organizacionId): ?int
    {
        $impresa = DB::table('documento_versiones')
            ->where('documento_id', $documentoId)
            ->whereNotNull('numero')
            ->orderByDesc('numero')
            ->selectRaw("instantanea #>> '{extras,revision,codigo}' as codigo")
            ->value('codigo');

        $revisiones = fn () => DB::table('revisiones_direccion')->where('organizacion_id', $organizacionId);

        if (is_string($impresa)) {
            $id = $revisiones()->where('codigo', $impresa)->value('id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        $id = $revisiones()->where('estado', 'aprobada')->orderByDesc('fecha')->orderByDesc('id')->value('id')
            ?? $revisiones()->orderByDesc('fecha')->orderByDesc('id')->value('id');

        return $id === null ? null : (int) $id;
    }
};
