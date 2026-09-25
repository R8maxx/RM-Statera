<?php

declare(strict_types=1);

namespace App\Domain\Proveedor;

use App\Domain\Proveedor\Enums\ResultadoClausula;
use App\Domain\Proveedor\Enums\ResultadoEvaluacion;
use App\Domain\Proveedor\Excepciones\OperacionDeProveedorNoPermitida;
use App\Domain\Proveedor\Models\ClausulaContractual;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Proveedor\Models\ProveedorEvaluacion;
use App\Domain\Proveedor\Models\ProveedorEvaluacionClausula;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra una evaluación del contrato y deja al proveedor en el estado que dice
 * el resultado (§ 4.9, A.5.20, `op.ext.1`).
 *
 * **Contesta todas las cláusulas vigentes**, sin excepción: una evaluación que
 * se salta la del borrado al terminar el contrato no es una evaluación, es una
 * opinión. «No aplica» es una respuesta; el silencio, no.
 *
 * **Apto con cláusulas incumplidas no se admite.** Si se acepta el contrato con
 * una cláusula que no se cumple, eso es «apto con condiciones», y las
 * condiciones van escritas. Es la misma línea que obliga a decir por qué se
 * reabre un incidente.
 *
 * La criticidad se congela en la evaluación: es la que decidió cuándo tocaba la
 * siguiente.
 */
final class RegistrarEvaluacion
{
    public function __construct(private readonly CambiarEstadoProveedor $estado) {}

    /**
     * @param  array<int, array{resultado: ResultadoClausula, nota: ?string}>  $clausulas  Por id de cláusula.
     */
    public function __invoke(
        Proveedor $proveedor,
        User $quien,
        Carbon $fecha,
        ResultadoEvaluacion $resultado,
        ?string $conclusiones,
        array $clausulas,
    ): ProveedorEvaluacion {
        if (! $proveedor->estado->seReevalua()) {
            throw OperacionDeProveedorNoPermitida::retirado();
        }

        /** @var list<int> $vigentes */
        $vigentes = ClausulaContractual::query()->vigentes()->pluck('id')->all();

        if (array_diff($vigentes, array_keys($clausulas)) !== []) {
            throw OperacionDeProveedorNoPermitida::clausulasIncompletas();
        }

        $incumple = array_filter($clausulas, static fn (array $una): bool => $una['resultado'] === ResultadoClausula::NoCumple);

        if ($resultado === ResultadoEvaluacion::Apto && $incumple !== []) {
            throw OperacionDeProveedorNoPermitida::aptoConIncumplimientos();
        }

        return DB::transaction(function () use ($proveedor, $quien, $fecha, $resultado, $conclusiones, $clausulas, $vigentes): ProveedorEvaluacion {
            $evaluacion = ProveedorEvaluacion::query()->create([
                'proveedor_id' => $proveedor->id,
                'fecha' => $fecha,
                'resultado' => $resultado,
                'criticidad' => $proveedor->criticidad(),
                'conclusiones' => $conclusiones,
                'evaluada_por_id' => $quien->id,
            ]);

            foreach ($vigentes as $clausulaId) {
                ProveedorEvaluacionClausula::query()->create([
                    'evaluacion_id' => $evaluacion->id,
                    'clausula_id' => $clausulaId,
                    'resultado' => $clausulas[$clausulaId]['resultado'],
                    'nota' => $clausulas[$clausulaId]['nota'],
                ]);
            }

            $this->estado->aplicar(
                $proveedor,
                $resultado->estadoResultante(),
                $quien,
                "Evaluación del {$fecha->format('d/m/Y')}: ".mb_strtolower($resultado->etiqueta()).'.',
            );

            return $evaluacion;
        });
    }
}
