<?php

declare(strict_types=1);

namespace App\Domain\Conformidad;

use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Enums\ViaConformidad;
use App\Domain\Conformidad\Excepciones\ConformidadNoPermitida;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Abre la declaración de conformidad de un sistema de categoría básica.
 *
 * Es el paso que une la autoevaluación con el documento: a partir de aquí la
 * Declaración de Conformidad del sistema se puede generar, y lo que imprime sale
 * de **esta** fila —la categoría congelada y la autoevaluación elegida—, no de lo
 * que el sistema diga el día que alguien pulse «Generar».
 *
 * Las precondiciones son las de `RequisitosDeDeclaracion`, y se vuelven a pedir
 * aquí aunque la ficha ya las haya enseñado: entre pintar la pantalla y pulsar el
 * botón alguien puede haber reabierto la autoevaluación.
 *
 * El `refresh()` de siempre, porque `estado` lo pone la base, y la primera
 * transición con `estado_anterior` nulo.
 */
final class IniciarDeclaracion
{
    public function __construct(
        private readonly RequisitosDeDeclaracion $requisitos,
        private readonly RegistroTransicionesConformidad $registro,
    ) {}

    public function __invoke(Sistema $sistema, ?User $usuario = null): Conformidad
    {
        $comprobacion = $this->requisitos->para($sistema);

        if ($comprobacion->categoria !== null && ! ViaConformidad::paraCategoria($comprobacion->categoria)->implementada()) {
            throw ConformidadNoPermitida::viaNoImplementada();
        }

        if (! $comprobacion->sePuedeIniciar()) {
            throw ConformidadNoPermitida::bloqueada($comprobacion->bloqueos);
        }

        $autoevaluacion = $comprobacion->autoevaluacion;
        $categoria = $comprobacion->categoria;

        // `sePuedeIniciar()` ya lo garantiza; esto es para que PHPStan lo sepa.
        assert($autoevaluacion !== null && $categoria !== null);

        return DB::transaction(function () use ($sistema, $autoevaluacion, $categoria, $usuario): Conformidad {
            $conformidad = Conformidad::query()->create([
                'sistema_id' => $sistema->id,
                'via' => ViaConformidad::paraCategoria($categoria)->value,
                'categoria' => $categoria->value,
                'estado' => EstadoConformidad::EnPreparacion->value,
                'auditoria_id' => $autoevaluacion->id,
            ]);
            $conformidad->refresh();

            $this->registro->registrar(
                $conformidad,
                null,
                $conformidad->estado,
                $usuario,
                "Sobre la autoevaluación {$autoevaluacion->codigo}, cerrada el {$autoevaluacion->fecha_cierre?->format('d/m/Y')}.",
            );

            return $conformidad;
        });
    }
}
