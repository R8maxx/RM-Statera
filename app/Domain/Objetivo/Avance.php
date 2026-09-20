<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Metrica\Enums\CumplimientoIndicador;
use App\Domain\Metrica\Models\Indicador;

/**
 * Cómo va un objetivo, leído de sus indicadores. **Se deriva, no se guarda.**
 *
 * Es la misma decisión que tomó el § 4.14 con el cumplimiento de un indicador:
 * guardarlo sería el mismo dato en dos sitios que pueden desincronizarse, y aquí
 * la desincronización sería peor —la cifra del objetivo diría «en objetivo» con
 * su indicador en rojo al lado—.
 *
 * **Y no es el estado del objetivo.** El estado lo declara una persona: la
 * dirección decide al cierre si el objetivo se alcanzó, igual que el propietario
 * de un riesgo declara el residual. Esto es lo que las cifras dicen mientras
 * tanto, y se enseña **al lado** del estado sin sobrescribirlo nunca. El
 * precedente exacto es `ValoracionEfectiva` frente a la valoración propia de un
 * activo, y el motivo es el mismo: la 6.2 no publica ninguna función de
 * indicadores a veredicto, y cualquiera que inventáramos sería una opinión de la
 * herramienta disfrazada de cálculo.
 *
 * Lo que sí hace, como `Riesgo::residualSinRespaldo()` y
 * `Activo::esperaBorradoSeguro()`, es **poner delante la contradicción**: un
 * objetivo que alguien dio por alcanzado con dos de sus tres indicadores fuera de
 * objetivo se ve de un vistazo, y la herramienta no toca el dato.
 */
final readonly class Avance
{
    public function __construct(
        public int $enObjetivo,
        public int $medidos,
        public int $total,
    ) {}

    /**
     * @param  iterable<int, Indicador>  $indicadores
     */
    public static function de(iterable $indicadores): self
    {
        $enObjetivo = 0;
        $medidos = 0;
        $total = 0;

        foreach ($indicadores as $indicador) {
            $total++;

            $cumplimiento = $indicador->cumplimiento();

            /*
             * «Sin objetivo» y «sin medir» **no cuentan como medidos**, por el
             * argumento de `EstadoControl::PorConfirmar`: la ausencia de dato es
             * una pregunta abierta y no un incumplimiento. Colapsarlas dejaría un
             * objetivo recién creado figurando como fallado.
             */
            if ($cumplimiento === CumplimientoIndicador::SinMedir || $cumplimiento === CumplimientoIndicador::SinObjetivo) {
                continue;
            }

            $medidos++;

            if ($cumplimiento === CumplimientoIndicador::EnObjetivo) {
                $enObjetivo++;
            }
        }

        return new self($enObjetivo, $medidos, $total);
    }

    /**
     * La frase, **siempre con su denominador**.
     *
     * «2» no dice nada y «2 de 3» sí, que es el tercer principio del producto. Y
     * los dos casos vacíos se nombran aparte porque no son lo mismo: sin ningún
     * indicador vinculado, la 6.2 e) está sin hacer; con indicadores y sin
     * medición, lo que falta es la 9.1.
     */
    public function etiqueta(): string
    {
        if ($this->total === 0) {
            return 'Sin indicador';
        }

        if ($this->medidos === 0) {
            return 'Sin medir';
        }

        return sprintf('%d de %d', $this->enObjetivo, $this->medidos);
    }

    /**
     * **Ninguno gasta rojo**, como ninguno de los cuatro veredictos del § 4.14.
     *
     * Quedarse corto respecto a una cifra que la organización se puso es la
     * distancia que queda, y un indicador que castiga por apuntar lo que falta
     * enseña a no apuntarlo. Lo que va en rojo en este módulo es el plazo.
     */
    public function tono(): string
    {
        if ($this->total === 0 || $this->medidos === 0) {
            return 'no_iniciado';
        }

        return $this->enObjetivo === $this->medidos ? 'implantado' : 'en_progreso';
    }

    /** Si hay indicadores medidos y alguno no llega a su objetivo. */
    public function seQuedaCorto(): bool
    {
        return $this->medidos > 0 && $this->enObjetivo < $this->medidos;
    }
}
