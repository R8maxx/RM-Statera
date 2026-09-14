<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Riesgo\Excepciones\EscalaInvalida;

/**
 * Una de las dos escalas de la metodología: probabilidad o impacto.
 *
 * Es una lista de escalones **contigua y empezando en 1**, y eso lo comprueba el
 * value object y no el `FormRequest`: la regla vale igual para un seeder, para un
 * importador y para la metodología de fábrica, y `RegistrarDependencia` ya sentó
 * el precedente de que una regla del dominio se comprueba en el dominio.
 *
 * Por qué la contigüidad no es puntillismo: la matriz de riesgo pinta una celda
 * por par de escalones, y una escala con un hueco —1, 2, 4— deja una fila vacía
 * en mitad de la cuadrícula sin que nadie entienda por qué. Y el producto de dos
 * escalas con huecos ya no recorre un rango continuo, así que las bandas de
 * `CalculoRiesgo` se vuelven irregulares de una forma que no se ve leyendo el
 * código.
 *
 * Inmutable, como `ValoracionDimensiones`: una escala se congela en cada
 * valoración (`riesgo_valoraciones.escala`), y algo que se congela no puede
 * mutarse en sitio.
 */
final readonly class EscalaRiesgo
{
    /** @param list<NivelEscala> $niveles */
    private function __construct(public array $niveles) {}

    /**
     * @param  list<array{valor?: mixed, etiqueta?: mixed, descripcion?: mixed}>  $crudo
     *
     * @throws EscalaInvalida
     */
    public static function desdeArray(array $crudo, string $nombre = 'escala'): self
    {
        if (count($crudo) < 2) {
            throw new EscalaInvalida(sprintf(
                'La %s tiene %d escalón(es). Una escala de menos de dos no gradúa nada.',
                $nombre,
                count($crudo),
            ));
        }

        $niveles = [];
        $esperado = 1;

        foreach ($crudo as $posicion => $escalon) {
            $valor = $escalon['valor'] ?? null;
            $etiqueta = trim((string) ($escalon['etiqueta'] ?? ''));

            if (! is_int($valor)) {
                throw new EscalaInvalida(sprintf(
                    'El escalón %d de la %s no lleva un valor entero.',
                    $posicion + 1,
                    $nombre,
                ));
            }

            if ($etiqueta === '') {
                throw new EscalaInvalida(sprintf(
                    'El escalón %d de la %s no lleva etiqueta: en la tabla saldría un número suelto.',
                    $valor,
                    $nombre,
                ));
            }

            if ($valor !== $esperado) {
                throw new EscalaInvalida($esperado === 1
                    ? sprintf(
                        'La %s empieza en %d. Los escalones empiezan en 1, porque es lo que multiplica '
                        .'en el cálculo del riesgo y un cero anularía la fila entera.',
                        $nombre,
                        $valor,
                    )
                    : sprintf(
                        'La %s salta de %d a %d. Los escalones van seguidos, porque la matriz pinta una '
                        .'celda por escalón y un hueco deja una fila sin explicación.',
                        $nombre,
                        $esperado - 1,
                        $valor,
                    ));
            }

            $descripcion = $escalon['descripcion'] ?? null;

            $niveles[] = new NivelEscala(
                valor: $valor,
                etiqueta: $etiqueta,
                descripcion: is_string($descripcion) && trim($descripcion) !== '' ? trim($descripcion) : null,
            );

            $esperado++;
        }

        return new self($niveles);
    }

    public function maximo(): int
    {
        return count($this->niveles);
    }

    public function admite(int $valor): bool
    {
        return $valor >= 1 && $valor <= $this->maximo();
    }

    /** @throws EscalaInvalida */
    public function nivelDe(int $valor): NivelEscala
    {
        if (! $this->admite($valor)) {
            throw new EscalaInvalida(sprintf(
                'El valor %d no está en una escala de 1 a %d.',
                $valor,
                $this->maximo(),
            ));
        }

        return $this->niveles[$valor - 1];
    }

    public function equivale(self $otra): bool
    {
        return $this->aArray() == $otra->aArray();
    }

    /**
     * Lo que se congela en `riesgo_valoraciones.escala`.
     *
     * @return list<array{valor: int, etiqueta: string, descripcion: string|null}>
     */
    public function aArray(): array
    {
        return array_map(static fn (NivelEscala $nivel): array => $nivel->aArray(), $this->niveles);
    }
}
