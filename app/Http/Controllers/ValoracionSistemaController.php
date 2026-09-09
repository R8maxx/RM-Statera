<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Sistema\AplicarValoracion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;
use App\Http\Requests\GuardarValoracionRequest;
use App\Http\Resources\Valoracion\PrevisualizacionValoracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La valoración de las cinco dimensiones, que es la única entrada del motor.
 *
 * Pantalla propia y no un bloque más del formulario de sistema: cambiar el
 * nombre de un sistema no tiene consecuencias, y cambiar una dimensión puede
 * dejar de exigir catorce medidas. Lo segundo se confirma; lo primero no.
 *
 * De ahí los dos verbos: `simular` contesta qué pasaría —sin escribir nada— y
 * `update` lo aplica. Es el mismo reparto que `catalogo:importar --dry-run`.
 */
class ValoracionSistemaController extends Controller
{
    public function edit(Sistema $sistema): Response
    {
        $sistema->load('marco', 'valoraciones');

        $valoracion = $sistema->valoracion();

        /** @var array<string, ?string> $justificaciones */
        $justificaciones = $sistema->valoraciones
            ->mapWithKeys(fn (ValoracionDimension $fila): array => [
                $fila->dimension->value => $fila->justificacion,
            ])
            ->all();

        return Inertia::render('sistemas/Valoracion', [
            'sistema' => [
                'id' => $sistema->id,
                'codigo' => $sistema->codigo,
                'nombre' => $sistema->nombre,
                'marco' => $sistema->marco?->nombre,
                'categoria' => $sistema->categoria()?->etiqueta(),
            ],
            // Cuántas implantaciones hay hoy, para que el diff de la
            // previsualización se lea sobre algo y no en el vacío.
            'exigiblesHoy' => $sistema->implantaciones()
                ->where('aplica', true)
                ->count(),
            'valorada' => $sistema->valoraciones->isNotEmpty(),
            'dimensiones' => array_map(
                static fn (Dimension $dimension): array => [
                    'clave' => $dimension->value,
                    'nombre' => $dimension->nombre(),
                    'pregunta' => $dimension->pregunta(),
                    'nivel' => $valoracion->nivelDe($dimension)->value,
                    'justificacion' => $justificaciones[$dimension->value] ?? '',
                ],
                Dimension::cases(),
            ),
            // Cada nivel viaja con su peso y con la categoría que le
            // corresponde para que el cliente pueda enseñar la categoría
            // derivada mientras se valora, sin reescribir en TypeScript la
            // correspondencia del Anexo I: vive en `NivelDimension::aCategoria()`
            // y se envía como dato. El servidor la recalcula igualmente al
            // guardar; esto es sólo lo que se ve.
            'niveles' => array_map(
                static fn (NivelDimension $nivel): array => [
                    'valor' => $nivel->value,
                    'etiqueta' => $nivel->etiqueta(),
                    'peso' => $nivel->peso(),
                    'categoria' => $nivel->aCategoria()?->etiqueta(),
                ],
                NivelDimension::cases(),
            ),
        ]);
    }

    /**
     * Qué pasaría al guardar. No escribe nada.
     *
     * Responde JSON, no Inertia: es una pregunta, no una navegación, y el
     * cliente la hace con `useHttp`. La validación es la misma que la del
     * guardado, así que cuando la previsualización llega, lo que se confirma ya
     * está validado.
     */
    public function simular(
        GuardarValoracionRequest $request,
        Sistema $sistema,
        AplicarValoracion $aplicar,
    ): JsonResponse {
        $resultado = $aplicar->simular($sistema, $request->valoracion(), $request->justificaciones());

        return response()->json(PrevisualizacionValoracion::desde($resultado));
    }

    public function update(
        GuardarValoracionRequest $request,
        Sistema $sistema,
        AplicarValoracion $aplicar,
    ): RedirectResponse {
        $resultado = $aplicar->aplicar($sistema, $request->valoracion(), $request->justificaciones());

        Inertia::flash('exito', $this->resumir($sistema, $resultado->resumen()));

        return to_route('sistemas.index');
    }

    /**
     * Lo que deja de exigirse se nombra siempre, y se dice que no se ha
     * borrado: es la diferencia entre un recálculo y una pérdida de traza.
     *
     * @param  array{creadas: int, reactivadas: int, dejan_de_aplicar: int, cambian_exigencia: int, sin_cambios: int}  $resumen
     */
    private function resumir(Sistema $sistema, array $resumen): string
    {
        $categoria = $sistema->fresh()?->categoria()?->etiqueta();

        $mensaje = $categoria === null
            ? "Valoración de {$sistema->codigo} guardada: el sistema queda fuera del ámbito del ENS."
            : "Valoración de {$sistema->codigo} guardada: categoría {$categoria}.";

        $partes = [];

        if ($resumen['creadas'] > 0) {
            $partes[] = "{$resumen['creadas']} medidas nuevas";
        }

        if ($resumen['reactivadas'] > 0) {
            $partes[] = "{$resumen['reactivadas']} reactivadas";
        }

        if ($resumen['cambian_exigencia'] > 0) {
            $partes[] = "{$resumen['cambian_exigencia']} cambian de nivel";
        }

        if ($resumen['dejan_de_aplicar'] > 0) {
            $partes[] = "{$resumen['dejan_de_aplicar']} dejan de aplicar (no se han borrado)";
        }

        return $partes === []
            ? "{$mensaje} No cambia ninguna medida."
            : $mensaje.' '.ucfirst(implode(', ', $partes)).'.';
    }
}
