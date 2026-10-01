<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion;

use App\Domain\Comunicacion\Enums\SentidoComunicacion;
use App\Domain\Comunicacion\Excepciones\ComunicacionInvalida;
use App\Domain\Comunicacion\Models\Comunicacion;
use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Registra algo que se comunicó o que se recibió.
 *
 * **`cubre_hasta` se congela aquí**, con la cadencia vigente en este momento,
 * cuando lo emitido cumple una previsión periódica. Es la misma regla que
 * `RegistrarCumplimiento`: subir la periodicidad de anual a semestral en marzo no
 * repinta como fuera de plazo lo que en enero estaba al día.
 *
 * **Una fecha futura no entra**: una comunicación es un hecho.
 *
 * Lo recibido no cumple ningún plan y lleva su tipo; lo emitido no lleva tipo ni
 * respuesta. La base lo impone con `CHECK`; aquí se dice en castellano.
 */
final class RegistrarComunicacion
{
    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(
        SentidoComunicacion $sentido,
        Carbon $fecha,
        array $atributos,
        ?User $usuario = null,
        ?ComunicacionPrevista $prevista = null,
    ): Comunicacion {
        if ($fecha->isAfter(Carbon::today())) {
            throw ComunicacionInvalida::enElFuturo($fecha);
        }

        if ($sentido === SentidoComunicacion::Recibida) {
            if ($prevista !== null) {
                throw ComunicacionInvalida::recibidaConPrevista();
            }

            if (($atributos['tipo_recibida'] ?? null) === null) {
                throw ComunicacionInvalida::recibidaSinTipo();
            }
        } else {
            unset($atributos['tipo_recibida'], $atributos['respuesta']);
        }

        if ($prevista?->estaRetirada() === true) {
            throw ComunicacionInvalida::previstaRetirada($prevista->codigo);
        }

        $cadencia = $prevista?->cadencia();

        $comunicacion = Comunicacion::query()->create([
            ...$atributos,
            'sentido' => $sentido->value,
            'comunicacion_prevista_id' => $prevista?->id,
            // Si no se dice por dónde, por el canal que el plan declaró.
            'canal' => $atributos['canal'] ?? $prevista?->canal->value,
            'fecha' => $fecha->toDateString(),
            'cubre_hasta' => $cadencia?->despuesDe($fecha)->toDateString(),
            'registrada_por_id' => $usuario?->id,
        ]);

        return $comunicacion->refresh();
    }
}
