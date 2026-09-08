<?php

declare(strict_types=1);

namespace App\Domain\Categorizacion;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use InvalidArgumentException;

/**
 * La valoración de las cinco dimensiones de seguridad de un sistema.
 *
 * Es la ÚNICA entrada del motor de categorización: la aplicabilidad se deriva de
 * aquí y nunca se marca a mano (invariante 4 de la especificación). Value object
 * inmutable a propósito, para que una valoración pueda compararse con otra y el
 * recálculo tras un cambio sea un diff y no una mutación en sitio.
 */
final readonly class ValoracionDimensiones
{
    public function __construct(
        public NivelDimension $confidencialidad = NivelDimension::Na,
        public NivelDimension $integridad = NivelDimension::Na,
        public NivelDimension $disponibilidad = NivelDimension::Na,
        public NivelDimension $autenticidad = NivelDimension::Na,
        public NivelDimension $trazabilidad = NivelDimension::Na,
    ) {}

    /**
     * @param  array<string, NivelDimension|string>  $niveles  Indexado por el código de la dimensión (C, I, D, A, T).
     */
    public static function desdeArray(array $niveles): self
    {
        $resueltos = [];

        foreach ($niveles as $codigo => $nivel) {
            $dimension = Dimension::tryFrom((string) $codigo);

            if ($dimension === null) {
                throw new InvalidArgumentException("Dimensión no reconocida: [{$codigo}]. Se esperaba C, I, D, A o T.");
            }

            $resueltos[$dimension->value] = $nivel instanceof NivelDimension
                ? $nivel
                : (NivelDimension::tryFrom($nivel) ?? throw new InvalidArgumentException(
                    "Nivel no reconocido para la dimensión [{$codigo}]: [{$nivel}]. Se esperaba na, bajo, medio o alto."
                ));
        }

        return new self(
            confidencialidad: $resueltos[Dimension::Confidencialidad->value] ?? NivelDimension::Na,
            integridad: $resueltos[Dimension::Integridad->value] ?? NivelDimension::Na,
            disponibilidad: $resueltos[Dimension::Disponibilidad->value] ?? NivelDimension::Na,
            autenticidad: $resueltos[Dimension::Autenticidad->value] ?? NivelDimension::Na,
            trazabilidad: $resueltos[Dimension::Trazabilidad->value] ?? NivelDimension::Na,
        );
    }

    public function nivelDe(Dimension $dimension): NivelDimension
    {
        return match ($dimension) {
            Dimension::Confidencialidad => $this->confidencialidad,
            Dimension::Integridad => $this->integridad,
            Dimension::Disponibilidad => $this->disponibilidad,
            Dimension::Autenticidad => $this->autenticidad,
            Dimension::Trazabilidad => $this->trazabilidad,
        };
    }

    /**
     * Paso 1 del motor: la categoría del sistema es el máximo de las cinco.
     *
     * Devuelve `null` cuando las cinco son `na`, que significa sistema fuera del
     * ámbito del ENS. Tratar ese caso como categoría básica sería exigirle
     * medidas a un sistema que no las debe.
     */
    public function categoria(): ?CategoriaEns
    {
        return $this->nivelMaximo()->aCategoria();
    }

    public function nivelMaximo(): NivelDimension
    {
        $maximo = NivelDimension::Na;

        foreach (Dimension::cases() as $dimension) {
            $nivel = $this->nivelDe($dimension);

            if ($nivel->peso() > $maximo->peso()) {
                $maximo = $nivel;
            }
        }

        return $maximo;
    }

    public function enAmbitoEns(): bool
    {
        return $this->categoria() !== null;
    }

    /**
     * Copia con una dimensión cambiada. El value object es inmutable: el
     * recálculo tras una revaloración compara dos valoraciones, no muta una.
     */
    public function conNivel(Dimension $dimension, NivelDimension $nivel): self
    {
        $niveles = $this->aArray();
        $niveles[$dimension->value] = $nivel;

        return self::desdeArray($niveles);
    }

    /** @return array<string, NivelDimension> */
    public function aArray(): array
    {
        $niveles = [];

        foreach (Dimension::cases() as $dimension) {
            $niveles[$dimension->value] = $this->nivelDe($dimension);
        }

        return $niveles;
    }

    public function equivale(self $otra): bool
    {
        return $this->aArray() == $otra->aArray();
    }
}
