<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\ValoracionEfectiva;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Riesgo\Models\Amenaza;
use App\Domain\Riesgo\Models\Riesgo;

/**
 * Cuánto daño haría un riesgo, deducido de lo que valen los activos sobre los que
 * pesa.
 *
 * **Lee la valoración EFECTIVA, no la propia, y ahí es donde este módulo cobra el
 * grafo de dependencias del inventario.** Un riesgo sobre una base de datos que
 * alguien valoró «bajo» porque «total, es una base de datos» se puntúa contra
 * «alto» si esa base sostiene un servicio esencial, porque `ValoracionEfectiva` ya
 * sabe que vale «alto». Una hoja de cálculo no hace eso: valora el activo donde
 * está escrito y deja sin proteger justo lo que hay debajo de lo importante.
 *
 * Y se filtra por las dimensiones de la amenaza cuando las hay: un corte de luz
 * no afecta a la confidencialidad de nada, así que puntuarlo contra el valor en
 * confidencialidad del activo lo inflaría sin motivo. Cuando la amenaza es libre
 * —texto que escribió alguien, sin catálogo detrás— se cuentan las cinco, porque
 * no hay de dónde saber cuáles aplican y exigir de más nunca deja a nadie
 * desprotegido.
 *
 * **Es una propuesta, no una imposición.** Lo que se guarda en la valoración es
 * lo que el analista confirme: el impacto lo decide una persona, y esto le ahorra
 * el trabajo de mirar activo por activo.
 */
final class ImpactoIntrinseco
{
    public function __construct(private readonly ValoracionEfectiva $efectiva) {}

    /**
     * La valoración plegada de todos los activos del riesgo: el máximo dimensión
     * a dimensión.
     *
     * Se pliega con `elevadaCon()`, que es la misma operación con la que una
     * valoración sube por el grafo de activos. El máximo y no la media: el riesgo
     * sobre un conjunto vale lo que vale el más expuesto de sus miembros, y
     * promediar dejaría el activo crítico escondido detrás de veinte triviales.
     */
    public function valoracionDe(Riesgo $riesgo): ValoracionDimensiones
    {
        $plegada = new ValoracionDimensiones;

        foreach ($riesgo->activos as $activo) {
            $plegada = $plegada->elevadaCon($this->efectiva->de($activo));
        }

        return $plegada;
    }

    /**
     * El escalón de impacto que sugiere ese conjunto de activos, en la escala de
     * la metodología.
     *
     * Devuelve `null` cuando el riesgo no tiene activos: no es un impacto de cero,
     * es que no hay nada de lo que deducirlo, y devolver el mínimo haría pasar por
     * cálculo lo que es una ausencia de datos. Mismo criterio que `na` frente a
     * «bajo» en el Anexo I.
     */
    public function sugerido(Riesgo $riesgo, Metodologia $metodologia): ?int
    {
        if ($riesgo->activos->isEmpty()) {
            return null;
        }

        $nivel = $this->nivelRelevante($riesgo);

        return $this->escalar($nivel, $metodologia);
    }

    /**
     * El desglose por dimensión, que es lo que permite decir POR QUÉ vale lo que
     * vale: «5 en disponibilidad, 2 en confidencialidad».
     *
     * Es lo que se congela en `riesgo_valoraciones.impacto_por_dimension`.
     *
     * @return array<string, int>
     */
    public function porDimension(Riesgo $riesgo, Metodologia $metodologia): array
    {
        $valoracion = $this->valoracionDe($riesgo);
        $desglose = [];

        foreach ($this->dimensionesDe($riesgo->amenaza) as $dimension) {
            $desglose[$dimension->value] = $this->escalar($valoracion->nivelDe($dimension), $metodologia);
        }

        return $desglose;
    }

    /**
     * Qué activo pone el techo, y en qué dimensión.
     *
     * Hermano de `ValoracionEfectiva::motivos()`, y por el mismo motivo: sin esto,
     * un impacto sugerido de 5 sobre un conjunto de treinta activos parece un
     * error de la herramienta.
     *
     * @return list<array{activo: Activo, dimensiones: list<Dimension>}>
     */
    public function motivos(Riesgo $riesgo): array
    {
        $techo = $this->valoracionDe($riesgo);
        $relevantes = $this->dimensionesDe($riesgo->amenaza);
        $motivos = [];

        foreach ($riesgo->activos as $activo) {
            $suya = $this->efectiva->de($activo);

            $dimensiones = array_values(array_filter(
                $relevantes,
                static fn (Dimension $dimension): bool => $suya->nivelDe($dimension)->peso() === $techo->nivelDe($dimension)->peso()
                    && $suya->nivelDe($dimension)->peso() > 0,
            ));

            if ($dimensiones !== []) {
                $motivos[] = ['activo' => $activo, 'dimensiones' => $dimensiones];
            }
        }

        return $motivos;
    }

    /** El más alto de los niveles que la amenaza puede tocar. */
    private function nivelRelevante(Riesgo $riesgo): NivelDimension
    {
        $valoracion = $this->valoracionDe($riesgo);
        $maximo = NivelDimension::Na;

        foreach ($this->dimensionesDe($riesgo->amenaza) as $dimension) {
            $nivel = $valoracion->nivelDe($dimension);

            if ($nivel->peso() > $maximo->peso()) {
                $maximo = $nivel;
            }
        }

        return $maximo;
    }

    /**
     * Sobre qué dimensiones actúa la amenaza. Las cinco cuando no se sabe.
     *
     * @return list<Dimension>
     */
    private function dimensionesDe(?Amenaza $amenaza): array
    {
        $declaradas = $amenaza?->dimensionesAfectadas() ?? [];

        return $declaradas === [] ? Dimension::cases() : $declaradas;
    }

    /**
     * Traduce un nivel del Anexo I a un escalón de la escala de impacto.
     *
     * Proporcional al tamaño de la escala, para que funcione igual con una de
     * cinco escalones que con una de diez: `alto` siempre es el techo y `na`
     * siempre es el suelo. Y el suelo es 1 y no 0, porque la escala empieza en 1
     * — un impacto de cero anularía el producto y dejaría el riesgo en nada.
     */
    private function escalar(NivelDimension $nivel, Metodologia $metodologia): int
    {
        $maximo = $metodologia->impacto->maximo();
        $techo = NivelDimension::Alto->peso();

        return max(1, (int) ceil($nivel->peso() / $techo * $maximo));
    }
}
