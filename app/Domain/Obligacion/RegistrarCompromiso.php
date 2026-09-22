<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use App\Domain\Obligacion\Models\Compromiso;

/**
 * Da de alta un compromiso propio: algo a lo que la organización se obliga sin
 * que se lo exija ningún marco.
 *
 * **Sin transiciones**, a diferencia de tareas, mejoras o no conformidades. Un
 * compromiso no tiene ciclo de vida: o está activo o se retiró, y lo que sí lleva
 * histórico es lo otro —cada vez que se cumplió—, que es la pregunta del auditor.
 * Copiar la tabla de transiciones «por simetría» daría un registro con dos filas
 * para siempre.
 */
final class RegistrarCompromiso
{
    public function __construct(private readonly CodigoCompromiso $codigos) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos): Compromiso
    {
        $compromiso = Compromiso::query()->create([
            ...$atributos,
            'codigo' => $atributos['codigo'] ?? $this->codigos->siguiente(),
        ]);

        return $compromiso->refresh();
    }
}
