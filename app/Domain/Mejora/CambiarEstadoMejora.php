<?php

declare(strict_types=1);

namespace App\Domain\Mejora;

use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Excepciones\TransicionDeMejoraNoPermitida;
use App\Domain\Mejora\Models\Mejora;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de estado de una oportunidad de mejora, con su histórico y su fecha de
 * cierre.
 *
 * **La fecha la pone el dominio y nunca el formulario**, igual que en tareas, en
 * no conformidades y en objetivos: la base acopla `estado` con `fecha_cierre` en
 * las dos direcciones, así que dejar que la escriba quien llame significa que el
 * día que una mejora se cierre desde un importador la inserción falle con un error
 * de restricción que no menciona la palabra «cierre».
 *
 * **Una sola transición exige motivo: descartar.** Es la decisión que el auditor
 * puede cuestionar —«¿por qué se decidió no hacer esto?»— y la regla vive aquí y
 * no en el `FormRequest` porque vale también para un importador. Implantar no lo
 * exige: lo que se hizo lo cuentan sus tareas.
 */
final class CambiarEstadoMejora
{
    public function __construct(private readonly RegistroTransicionesMejora $registro) {}

    public function __invoke(
        Mejora $mejora,
        EstadoMejora $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): Mejora {
        $actual = $mejora->estado;

        if ($actual === $nuevo) {
            return $mejora;
        }

        if (! $actual->permite($nuevo)) {
            throw new TransicionDeMejoraNoPermitida($actual, $nuevo);
        }

        $this->exigirMotivo($actual, $nuevo, $nota);

        return DB::transaction(function () use ($mejora, $actual, $nuevo, $usuario, $nota): Mejora {
            $mejora->update([
                'estado' => $nuevo->value,
                'fecha_cierre' => $nuevo->esCerrada() ? Carbon::today() : null,
            ]);

            $this->registro->registrar($mejora, $actual, $nuevo, $usuario, $nota);

            return $mejora->refresh();
        });
    }

    /**
     * @throws TransicionDeMejoraNoPermitida
     */
    private function exigirMotivo(EstadoMejora $actual, EstadoMejora $nuevo, ?string $nota): void
    {
        if ($nota !== null && trim($nota) !== '') {
            return;
        }

        if ($nuevo === EstadoMejora::Descartada) {
            throw new TransicionDeMejoraNoPermitida(
                $actual,
                $nuevo,
                'Descartar una mejora exige decir por qué: un registro lleno de descartadas sin explicación '
                .'no es mejora continua, es un buzón abandonado.',
            );
        }
    }
}
