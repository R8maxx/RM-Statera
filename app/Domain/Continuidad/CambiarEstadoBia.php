<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Excepciones\TransicionDeBiaNoPermitida;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El ciclo de un BIA, con su histórico y sus fechas.
 *
 * **Aprobar sella tres cosas a la vez**: quién aprobó, cuándo, y cuándo toca
 * revisarlo — doce meses después, sin que nadie tenga que acordarse de
 * ponerlo. **Salir de `aprobado` deshace las dos primeras y conserva la
 * tercera**: un BIA que deja de estar vigente sigue teniendo una fecha de
 * revisión pendiente, que es justo la que le recuerda a alguien que hay que
 * volver a aprobarlo o darlo por obsoleto de verdad.
 *
 * **La fecha la pone el dominio y nunca el formulario**, igual que en
 * incidentes, tareas, no conformidades, objetivos y mejoras: la base acopla
 * `estado` con `fecha_aprobacion` en las dos direcciones.
 *
 * **Pasar a obsoleto, o volver a borrador desde aprobado, exige nota
 * escrita.** Avanzar de borrador a aprobado no la pide: eso es el gesto normal
 * de cerrar el análisis, y pedir un texto para él sería puro trámite.
 */
final class CambiarEstadoBia
{
    public function __construct(private readonly RegistroTransicionesBia $registro) {}

    public function __invoke(
        BiaServicio $bia,
        EstadoBia $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): BiaServicio {
        $actual = $bia->estado;

        if ($actual === $nuevo) {
            return $bia;
        }

        if (! $actual->admite($nuevo)) {
            throw TransicionDeBiaNoPermitida::entre($actual, $nuevo);
        }

        $this->exigirMotivo($actual, $nuevo, $nota);

        return DB::transaction(function () use ($bia, $actual, $nuevo, $usuario, $nota): BiaServicio {
            $bia->update($this->atributosDelPaso($nuevo, $usuario));

            $this->registro->registrar($bia, $actual, $nuevo, $usuario, $nota);

            return $bia->refresh();
        });
    }

    /**
     * Los atributos que sella cada paso.
     *
     * **Quién aprueba es el usuario que ejecuta la acción**, no el responsable
     * del BIA: son dos roles distintos, y colapsarlos dejaría sin decir quién
     * dio realmente el visto bueno.
     *
     * @return array<string, mixed>
     */
    private function atributosDelPaso(EstadoBia $nuevo, ?User $usuario): array
    {
        if ($nuevo === EstadoBia::Aprobado) {
            return [
                'estado' => $nuevo->value,
                'aprobado_por_id' => $usuario?->id,
                'fecha_aprobacion' => Carbon::today(),
                'fecha_revision' => Carbon::today()->addYear(),
            ];
        }

        // Salir de aprobado: se suelta quién y cuándo, y se conserva la
        // revisión pendiente. Ver el segundo párrafo de la cabecera.
        return [
            'estado' => $nuevo->value,
            'aprobado_por_id' => null,
            'fecha_aprobacion' => null,
        ];
    }

    /**
     * **Volver a borrador desde aprobado, o pasar a obsoleto, exige decir por
     * qué; avanzar de borrador a aprobado no.**
     *
     * @throws TransicionDeBiaNoPermitida
     */
    private function exigirMotivo(EstadoBia $actual, EstadoBia $nuevo, ?string $nota): void
    {
        if ($nota !== null && trim($nota) !== '') {
            return;
        }

        $exigeMotivo = $nuevo === EstadoBia::Obsoleto
            || ($actual === EstadoBia::Aprobado && $nuevo === EstadoBia::Borrador);

        if ($exigeMotivo) {
            throw TransicionDeBiaNoPermitida::sinMotivo($nuevo);
        }
    }
}
