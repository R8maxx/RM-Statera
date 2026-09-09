<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Enums;

use InvalidArgumentException;
use Stringable;

/**
 * Qué se exige de una medida en una categoría dada.
 *
 * No es un enum a propósito: además de `no_aplica` y `aplica`, el ENS admite
 * refuerzos `R1`, `R2`, `R3`… cuyo número no está acotado por el marco y cambia
 * entre revisiones. Cerrarlo en un enum obligaría a tocar código cada vez que
 * el catálogo incorpora un refuerzo nuevo, que es justo lo que prohíbe el
 * invariante 3: el catálogo va como datos, no como código.
 */
final readonly class Exigencia implements Stringable
{
    public const NO_APLICA = 'no_aplica';

    public const APLICA = 'aplica';

    private function __construct(public string $valor) {}

    public static function desde(string $valor): self
    {
        $valor = trim($valor);

        if ($valor !== self::NO_APLICA && $valor !== self::APLICA && preg_match('/^R[0-9]+$/', $valor) !== 1) {
            throw new InvalidArgumentException("Exigencia no reconocida: [{$valor}]. Se esperaba no_aplica, aplica o R<n>.");
        }

        return new self($valor);
    }

    public static function noAplica(): self
    {
        return new self(self::NO_APLICA);
    }

    public static function aplica(): self
    {
        return new self(self::APLICA);
    }

    public static function refuerzo(int $nivel): self
    {
        if ($nivel < 1) {
            throw new InvalidArgumentException("Nivel de refuerzo no válido: [{$nivel}].");
        }

        return new self('R'.$nivel);
    }

    /**
     * Un refuerzo implica que la medida aplica: R2 es "aplica, y además esto".
     */
    public function esAplicable(): bool
    {
        return $this->valor !== self::NO_APLICA;
    }

    public function nivelRefuerzo(): ?int
    {
        if (preg_match('/^R([0-9]+)$/', $this->valor, $coincidencias) === 1) {
            return (int) $coincidencias[1];
        }

        return null;
    }

    public function equivale(self $otra): bool
    {
        return $this->valor === $otra->valor;
    }

    /**
     * Cómo se lee esta exigencia en la interfaz y en los documentos.
     *
     * `R2` no significa nada fuera del Anexo II, y la tabla la lee gente que no
     * se lo sabe de memoria.
     */
    public function etiqueta(): string
    {
        $refuerzo = $this->nivelRefuerzo();

        return match (true) {
            $refuerzo !== null => "Refuerzo {$refuerzo}",
            $this->valor === self::APLICA => 'Aplica',
            default => 'No aplica',
        };
    }

    /**
     * El nombre de estado con el que se pinta, no un color: los colores viven
     * en `resources/css/app.css`.
     *
     * Los refuerzos se colapsan en un solo tono a propósito. Su número no está
     * acotado por el marco —ese es el motivo de que esto no sea un enum—, así
     * que un tono por nivel obligaría a tocar la interfaz cada vez que el
     * catálogo incorpora un `R4`.
     */
    public function tono(): string
    {
        return match (true) {
            $this->nivelRefuerzo() !== null => 'reforzado',
            $this->valor === self::APLICA => 'exigible',
            default => 'no_aplica',
        };
    }

    /**
     * Orden de exigencia, para quedarse con la mayor cuando concurren varias
     * fuentes (categoría del sistema, modulación por dimensión y perfil).
     */
    public function peso(): int
    {
        return match (true) {
            $this->valor === self::NO_APLICA => 0,
            $this->valor === self::APLICA => 1,
            default => 1 + (int) $this->nivelRefuerzo(),
        };
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
