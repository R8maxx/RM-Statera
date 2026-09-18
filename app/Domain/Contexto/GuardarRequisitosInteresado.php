<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\Models\RequisitoInteresado;
use Illuminate\Support\Facades\DB;

/**
 * Guarda entera la lista de lo que exige o espera una parte interesada.
 *
 * **Una sola ruta para añadir, editar, reordenar y borrar**, como la lista de
 * comprobación de una tarea. Lo que se está escribiendo aquí es «qué nos pide este
 * regulador», y eso se piensa de una vez mirando la lista completa: partirlo en
 * cuatro rutas obligaría a guardar cuatro veces para dejar dicho lo que se pensó
 * una.
 *
 * **Un `id` que no es de esta parte interesada se trata como una línea nueva**, no
 * como un error y desde luego no como una edición. Lo que llega del cliente no
 * manda sobre a quién pertenece una fila — es la misma regla que ya está escrita
 * para las subtareas, y es lo que impide reescribir el requisito legal de otra
 * organización pasando su identificador.
 *
 * **Las líneas que desaparecen de la lista se borran**, a diferencia de las
 * cuestiones y las partes, que se retiran con motivo. La diferencia es real: una
 * cuestión del DAFO es un juicio fechado que el análisis siguiente tiene que poder
 * explicar, y un requisito es una línea de la ficha de su parte. Lo que hace que
 * no se pierda nada es que la instantánea del análisis aprobado ya guardó la
 * lista tal como estaba al firmar.
 */
final class GuardarRequisitosInteresado
{
    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    public function __invoke(ParteInteresada $parte, array $lineas): void
    {
        DB::transaction(function () use ($parte, $lineas): void {
            $existentes = $parte->requisitos()->get()->keyBy('id');
            $conservados = [];

            foreach ($lineas as $linea) {
                $id = isset($linea['id']) ? (int) $linea['id'] : null;
                $atributos = [
                    'descripcion' => trim((string) $linea['descripcion']),
                    'naturaleza' => $linea['naturaleza'],
                    'es_climatico' => (bool) ($linea['es_climatico'] ?? false),
                    'referencia' => $this->oNulo($linea['referencia'] ?? null),
                    'como_se_atiende' => $this->oNulo($linea['como_se_atiende'] ?? null),
                ];

                $existente = $id !== null ? $existentes->get($id) : null;

                if ($existente instanceof RequisitoInteresado) {
                    $existente->update($atributos);
                    $conservados[] = $existente->id;

                    continue;
                }

                $nuevo = $parte->requisitos()->create($atributos);
                $conservados[] = $nuevo->id;
            }

            /*
             * Lo que ya no está en la lista se va, y con él sus vínculos a
             * implantaciones por la cascada de la pivote. Es lo correcto: el
             * vínculo dice «esta medida cubre este requisito» y sin el requisito
             * no dice nada.
             */
            $parte->requisitos()
                ->whereNotIn('id', $conservados)
                ->get()
                ->each(static fn (RequisitoInteresado $requisito) => $requisito->delete());
        });
    }

    private function oNulo(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }
}
