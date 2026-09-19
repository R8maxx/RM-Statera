<?php

declare(strict_types=1);

namespace App\Domain\Metrica;

use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Sella la cifra de un periodo.
 *
 * Es el único camino por el que entra una fila en `mediciones`, y concentra las
 * tres reglas que no pueden estar en el formulario porque valen también para el
 * comando programado y para un importador:
 *
 * 1. **Un periodo se mide una vez.** Volver a medirlo **corrige**, no acumula:
 *    con dos filas del mismo trimestre, «¿cuántas mediciones llevamos?» depende
 *    de cuál de las dos mires, y la gráfica pinta dos puntos sobre la misma
 *    abscisa. Lo garantiza además un índice único.
 *
 * 2. **El objetivo se congela la primera vez y una corrección no lo mueve.** El
 *    veredicto de un periodo pertenece al listón que estaba puesto cuando se
 *    cerró; arreglar un dígito mal tecleado en abril no puede cambiar contra qué
 *    se juzgó marzo. Es la misma línea que separa corregir de reescribir.
 *
 * 3. **`medida_en` es cuándo se tomó, no cuándo se guardó.** Un indicador que se
 *    mide a mano el día 5 y se apunta el día 20 tiene las dos fechas, y la que
 *    vale para «¿se midió a tiempo?» es la primera.
 */
final readonly class RegistrarMedicion
{
    /**
     * La cifra que Statera calcula sola.
     *
     * **Propone y sella**: no se vuelve a consultar al mirarla. Una serie que se
     * recalcula reescribiría marzo en octubre, que es el fallo que ya está
     * documentado para el `.docx`, para las salvaguardas de una valoración y para
     * la checklist de una auditoría cerrada.
     */
    public function calculada(Indicador $indicador, CarbonInterface $inicio, CarbonInterface $fin, ?User $autor = null): Medicion
    {
        $calculo = $indicador->calculo;

        if ($calculo === null) {
            throw new IndicadorNoCalculable($indicador);
        }

        $medida = $calculo->medir($indicador->marco_id);

        return $this->sellar($indicador, $inicio, $fin, [
            'valor' => $medida->valor,
            'numerador' => $medida->numerador,
            'denominador' => $medida->denominador,
            'origen' => OrigenMedicion::Calculado,
            'medida_en' => Carbon::now(),
        ], $autor);
    }

    /**
     * La cifra que escribe una persona.
     *
     * Existe aunque el panel ya calcule seis de las cifras del § 4.14, y no es
     * una concesión: «porcentaje de personal formado» no sale de esta base de
     * datos mientras el § 4.8 no exista, y varias de las que pide el informe INES
     * tampoco. Un módulo de métricas que sólo admitiera lo que ya sabe contar
     * dejaría fuera justo lo que cuesta medir.
     *
     * @param  array{valor: float, numerador?: ?int, denominador?: ?int, medida_en?: ?CarbonInterface, nota?: ?string}  $datos
     */
    public function manual(Indicador $indicador, CarbonInterface $inicio, CarbonInterface $fin, array $datos, ?User $autor = null): Medicion
    {
        return $this->sellar($indicador, $inicio, $fin, [
            'valor' => $datos['valor'],
            'numerador' => $datos['numerador'] ?? null,
            'denominador' => $datos['denominador'] ?? null,
            'origen' => OrigenMedicion::Manual,
            'medida_en' => $datos['medida_en'] ?? Carbon::now(),
            'nota' => $datos['nota'] ?? null,
        ], $autor);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function sellar(Indicador $indicador, CarbonInterface $inicio, CarbonInterface $fin, array $atributos, ?User $autor): Medicion
    {
        $existente = $indicador->mediciones()
            ->whereDate('periodo_inicio', $inicio)
            ->first();

        $atributos['periodo_fin'] = $fin;
        $atributos['registrada_por_id'] = $autor?->id;

        if ($existente instanceof Medicion) {
            // El objetivo NO se vuelve a tomar: se corrige la cifra, no el listón
            // contra el que se juzgó aquel periodo.
            $existente->fill($atributos)->save();

            return $existente->refresh();
        }

        $atributos['periodo_inicio'] = $inicio;
        $atributos['objetivo'] = $indicador->objetivo;

        /** @var Medicion $medicion */
        $medicion = $indicador->mediciones()->create($atributos);

        return $medicion->refresh();
    }
}
