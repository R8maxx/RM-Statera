<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Riesgo\Enums\NivelRiesgo;
use App\Domain\Riesgo\Excepciones\EscalaInvalida;

/**
 * Cómo se convierte un par (probabilidad, impacto) en un riesgo y en un nivel.
 *
 * Función pura y sin estado, como `MotorCategorizacion`: entra una metodología y
 * dos números, sale un número y un escalón. Nada de consultas, nada de modelos.
 * Eso es lo que hace que la matriz completa se pueda probar sin base de datos.
 *
 * **Las bandas salen de los umbrales, no de quintiles.** Es la decisión que
 * impide que el nivel y el umbral se contradigan: `MuyAlto` es estar en o por
 * encima del umbral crítico y `Alto` es estar por encima del de aceptación, así
 * que `porEncimaDelUmbral()` y `NivelRiesgo::sobreUmbral()` dicen siempre lo
 * mismo. Con bandas por quintiles, un riesgo podía salir «muy alto» —badge rojo—
 * estando dentro del apetito que la organización declaró, y entonces la tabla y
 * el indicador del panel discreparían sobre la misma fila.
 *
 * La zona aceptable —de 1 hasta el umbral de aceptación sin llegar a él— se
 * reparte en tres tramos contiguos lo más iguales posible. Con un umbral de
 * aceptación muy bajo alguno de esos tramos se queda vacío, y es correcto: la
 * organización ha decidido que casi nada le resulta aceptable, y la herramienta
 * no tiene por qué inventarle grados que no ha pedido.
 */
final class CalculoRiesgo
{
    /**
     * El riesgo: probabilidad por impacto.
     *
     * Los dos valores se comprueban contra sus escalas. Un impacto de 7 en una
     * escala de 5 no es un riesgo grande, es un dato corrupto, y multiplicarlo
     * como si nada lo colaría en el histórico con apariencia de cifra buena.
     *
     * @throws EscalaInvalida
     */
    public function producto(Metodologia $metodologia, int $probabilidad, int $impacto): int
    {
        if (! $metodologia->probabilidad->admite($probabilidad)) {
            throw new EscalaInvalida(sprintf(
                'La probabilidad vale %d y la escala va de 1 a %d.',
                $probabilidad,
                $metodologia->probabilidad->maximo(),
            ));
        }

        if (! $metodologia->impacto->admite($impacto)) {
            throw new EscalaInvalida(sprintf(
                'El impacto vale %d y la escala va de 1 a %d.',
                $impacto,
                $metodologia->impacto->maximo(),
            ));
        }

        return $probabilidad * $impacto;
    }

    /** En qué escalón cae un riesgo ya calculado. */
    public function nivel(int $riesgo, Metodologia $metodologia): NivelRiesgo
    {
        if ($riesgo >= $metodologia->umbralCritico) {
            return NivelRiesgo::MuyAlto;
        }

        if ($riesgo >= $metodologia->umbralAceptacion) {
            return NivelRiesgo::Alto;
        }

        [$hastaMuyBajo, $hastaBajo] = $this->cortesDeLaZonaAceptable($metodologia);

        if ($riesgo <= $hastaMuyBajo) {
            return NivelRiesgo::MuyBajo;
        }

        if ($riesgo <= $hastaBajo) {
            return NivelRiesgo::Bajo;
        }

        return NivelRiesgo::Medio;
    }

    /**
     * Si hay que tratarlo. Es `nivel() >= Alto`, y lo es por construcción.
     */
    public function porEncimaDelUmbral(int $riesgo, Metodologia $metodologia): bool
    {
        return $riesgo >= $metodologia->umbralAceptacion;
    }

    public function esCritico(int $riesgo, Metodologia $metodologia): bool
    {
        return $riesgo >= $metodologia->umbralCritico;
    }

    /**
     * De qué a qué va cada escalón, para poder enseñarlo.
     *
     * La leyenda de la matriz y la ayuda de la ficha salen de aquí en vez de
     * repetir la regla en el cliente: con la condición escrita dos veces, el día
     * que cambie una la leyenda dirá una cosa y el badge otra. Un escalón vacío
     * —posible con umbrales muy bajos— no sale en el mapa.
     *
     * **El tono y el icono viajan con la banda**, no los deduce el cliente. Es la
     * misma regla que con los estados: el dominio dice de qué color va cada cosa,
     * porque el mismo tono significa cosas distintas según el módulo.
     *
     * @return array<string, array{desde: int, hasta: int, etiqueta: string, tono: string, icono: string}>
     */
    public function bandas(Metodologia $metodologia): array
    {
        [$hastaMuyBajo, $hastaBajo] = $this->cortesDeLaZonaAceptable($metodologia);

        $limites = [
            NivelRiesgo::MuyBajo->value => [1, $hastaMuyBajo],
            NivelRiesgo::Bajo->value => [$hastaMuyBajo + 1, $hastaBajo],
            NivelRiesgo::Medio->value => [$hastaBajo + 1, $metodologia->umbralAceptacion - 1],
            NivelRiesgo::Alto->value => [$metodologia->umbralAceptacion, $metodologia->umbralCritico - 1],
            NivelRiesgo::MuyAlto->value => [$metodologia->umbralCritico, $metodologia->riesgoMaximo()],
        ];

        $tramos = [];

        foreach (NivelRiesgo::cases() as $nivel) {
            [$desde, $hasta] = $limites[$nivel->value];

            if ($desde > $hasta) {
                continue;
            }

            $tramos[$nivel->value] = [
                'desde' => $desde,
                'hasta' => $hasta,
                'etiqueta' => $nivel->etiqueta(),
                'tono' => $nivel->tono(),
                'icono' => $nivel->icono(),
            ];
        }

        return $tramos;
    }

    /**
     * Los dos cortes que parten la zona aceptable en tres.
     *
     * @return array{int, int}
     */
    private function cortesDeLaZonaAceptable(Metodologia $metodologia): array
    {
        $ancho = $metodologia->umbralAceptacion - 1;

        $hastaMuyBajo = (int) ceil($ancho / 3);
        $hastaBajo = $hastaMuyBajo + (int) ceil(($ancho - $hastaMuyBajo) / 2);

        return [$hastaMuyBajo, $hastaBajo];
    }
}
