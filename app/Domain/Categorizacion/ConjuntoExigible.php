<?php

declare(strict_types=1);

namespace App\Domain\Categorizacion;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * El conjunto de medidas exigibles a un sistema: la salida del motor.
 *
 * Sólo contiene medidas que aplican. Una celda `no_aplica` de la matriz no entra
 * aquí: lo que no se exige no genera implantación.
 *
 * @implements IteratorAggregate<string, MedidaExigible>
 */
final readonly class ConjuntoExigible implements Countable, IteratorAggregate
{
    /** @var array<string, MedidaExigible> Indexado por código de requisito. */
    public array $medidas;

    /**
     * @param  iterable<MedidaExigible>  $medidas
     */
    public function __construct(iterable $medidas = [])
    {
        $indexadas = [];

        foreach ($medidas as $medida) {
            $indexadas[$medida->codigo] = $medida;
        }

        ksort($indexadas, SORT_NATURAL);

        $this->medidas = $indexadas;
    }

    public function paraCodigo(string $codigo): ?MedidaExigible
    {
        return $this->medidas[$codigo] ?? null;
    }

    public function exige(string $codigo): bool
    {
        return isset($this->medidas[$codigo]);
    }

    /** @return list<string> */
    public function codigos(): array
    {
        return array_keys($this->medidas);
    }

    /**
     * Agrupado por familia del Anexo II: `org` marco organizativo, `op` marco
     * operacional, `mp` medidas de protección.
     *
     * @return array<string, list<MedidaExigible>>
     */
    public function agrupadoPorFamilia(): array
    {
        $familias = [];

        foreach ($this->medidas as $medida) {
            $familias[$medida->familia()][] = $medida;
        }

        return $familias;
    }

    public function count(): int
    {
        return count($this->medidas);
    }

    public function estaVacio(): bool
    {
        return $this->medidas === [];
    }

    /** @return Traversable<string, MedidaExigible> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->medidas);
    }
}
