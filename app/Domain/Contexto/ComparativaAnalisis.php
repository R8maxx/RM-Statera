<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;

/**
 * Qué entró y qué salió en una revisión del contexto.
 *
 * **Es la razón entera de que el módulo lleve análisis versionados.** La cláusula
 * 9.3 pide «cambios de contexto» como entrada obligatoria de la revisión por la
 * dirección, y sin esto la respuesta sería releer dos DAFO enteros y compararlos a
 * ojo.
 *
 * Sale directamente de `analisis_alta_id` y `analisis_baja_id`, sin diferenciar
 * dos instantáneas. Es lo que hace que sea barato y exacto: una cuestión no
 * «aparece» entre dos revisiones, la da de alta un análisis concreto, y eso está
 * escrito en su fila.
 *
 * **Lo que no contesta es qué cambió por dentro**: si alguien reescribió la
 * descripción de una cuestión que sigue en las dos revisiones, aquí no sale. Está
 * en las dos instantáneas y se puede comparar, pero eso es una pantalla de diff y
 * no existe todavía. Queda declarado, que es lo que este proyecto hace con lo que
 * aún no puede afirmar.
 */
final readonly class ComparativaAnalisis
{
    /**
     * @return array{
     *     cuestionesAltas: list<CuestionContexto>,
     *     cuestionesBajas: list<CuestionContexto>,
     *     partesAltas: list<ParteInteresada>,
     *     partesBajas: list<ParteInteresada>,
     * }
     */
    public function para(AnalisisContexto $analisis): array
    {
        return [
            'cuestionesAltas' => $analisis->cuestionesDadasDeAlta()
                ->with('responsable')->orderBy('codigo')->get()->all(),
            'cuestionesBajas' => $analisis->cuestionesRetiradas()
                ->with('responsable')->orderBy('codigo')->get()->all(),
            'partesAltas' => $analisis->partesDadasDeAlta()
                ->orderBy('codigo')->get()->all(),
            'partesBajas' => $analisis->partesRetiradas()
                ->orderBy('codigo')->get()->all(),
        ];
    }

    /** Si la revisión no movió nada, para poder decirlo en vez de enseñar cuatro listas vacías. */
    public function sinCambios(AnalisisContexto $analisis): bool
    {
        $comparativa = $this->para($analisis);

        return $comparativa['cuestionesAltas'] === []
            && $comparativa['cuestionesBajas'] === []
            && $comparativa['partesAltas'] === []
            && $comparativa['partesBajas'] === [];
    }
}
