<?php

declare(strict_types=1);

namespace App\Domain\Conformidad;

use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Excepciones\ConformidadNoPermitida;
use App\Domain\Conformidad\Models\Conformidad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Retira una declaración: en preparación, declarada o con el distintivo puesto.
 *
 * **Exige motivo**, y la regla vive aquí y no en el `FormRequest` porque vale
 * también para un importador. Retirar la conformidad de un sistema es de lo poco
 * que el auditor va a preguntar sí o sí —«¿por qué dejó de estar declarado?»—, y
 * la respuesta tiene que estar en el histórico.
 *
 * **No borra la fila** ni lo que cuelga de ella: la declaración retirada sigue
 * siendo la que respaldó al sistema entre dos fechas, y el PDF firmado sigue
 * siendo el que se entregó.
 */
final class RetirarConformidad
{
    public function __construct(private readonly RegistroTransicionesConformidad $registro) {}

    public function __invoke(Conformidad $conformidad, string $motivo, ?User $usuario = null): Conformidad
    {
        $actual = $conformidad->estado;

        if (! $actual->permite(EstadoConformidad::Retirada)) {
            throw ConformidadNoPermitida::transicion($actual, EstadoConformidad::Retirada);
        }

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ConformidadNoPermitida::sinMotivo();
        }

        return DB::transaction(function () use ($conformidad, $actual, $motivo, $usuario): Conformidad {
            $conformidad->update(['estado' => EstadoConformidad::Retirada->value]);

            $this->registro->registrar($conformidad, $actual, EstadoConformidad::Retirada, $usuario, $motivo);

            return $conformidad->refresh();
        });
    }
}
