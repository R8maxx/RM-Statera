<?php

declare(strict_types=1);

use App\Domain\Riesgo\EscalaRiesgo;
use App\Domain\Riesgo\Excepciones\EscalaInvalida;

/*
|--------------------------------------------------------------------------
| Las escalas de la metodología
|--------------------------------------------------------------------------
|
| Una escala mal formada no revienta al guardarla: revienta después, al pintar la
| matriz o al repartir las bandas, y a tres capas de distancia de la causa. Por
| eso la comprueba el value object —donde pasan también el seeder, la fábrica y
| cualquier importador futuro— y no el FormRequest.
|
*/

/** @param list<array{valor: int, etiqueta: string}> $escalones */
function escala(array $escalones): EscalaRiesgo
{
    return EscalaRiesgo::desdeArray($escalones);
}

/** @return list<array{valor: int, etiqueta: string}> */
function escalones(int ...$valores): array
{
    return array_map(
        static fn (int $valor): array => ['valor' => $valor, 'etiqueta' => "Escalón {$valor}"],
        array_values($valores),
    );
}

it('acepta una escala contigua que empieza en 1', function (): void {
    $escala = escala(escalones(1, 2, 3, 4, 5));

    expect($escala->maximo())->toBe(5)
        ->and($escala->nivelDe(3)->etiqueta)->toBe('Escalón 3')
        ->and($escala->admite(5))->toBeTrue()
        ->and($escala->admite(6))->toBeFalse()
        ->and($escala->admite(0))->toBeFalse();
});

it('rechaza una escala con un hueco', function (): void {
    // La matriz pinta una celda por escalón: el hueco deja una fila sin explicar.
    expect(fn () => escala(escalones(1, 2, 4)))
        ->toThrow(EscalaInvalida::class, 'salta de 2 a 4');
});

it('rechaza una escala desordenada', function (): void {
    expect(fn () => escala(escalones(1, 3, 2)))->toThrow(EscalaInvalida::class);
});

it('rechaza una escala que no empieza en 1', function (): void {
    expect(fn () => escala(escalones(0, 1, 2)))->toThrow(EscalaInvalida::class);
    expect(fn () => escala(escalones(2, 3, 4)))->toThrow(EscalaInvalida::class);
});

it('rechaza una escala vacía o de un solo escalón', function (array $escalones): void {
    // Una escala de un escalón no gradúa: todos los riesgos saldrían iguales.
    expect(fn () => escala($escalones))->toThrow(EscalaInvalida::class, 'no gradúa nada');
})->with([
    'vacía' => [[]],
    'de un escalón' => [[['valor' => 1, 'etiqueta' => 'Único']]],
]);

it('rechaza un escalón sin etiqueta', function (): void {
    // Sin etiqueta, en la tabla sale un número suelto que cada uno interpreta.
    expect(fn () => EscalaRiesgo::desdeArray([
        ['valor' => 1, 'etiqueta' => 'Baja'],
        ['valor' => 2, 'etiqueta' => '   '],
    ]))->toThrow(EscalaInvalida::class, 'no lleva etiqueta');
});

it('rechaza un escalón cuyo valor no es entero', function (): void {
    expect(fn () => EscalaRiesgo::desdeArray([
        ['valor' => 1, 'etiqueta' => 'Baja'],
        ['valor' => '2', 'etiqueta' => 'Media'],
    ]))->toThrow(EscalaInvalida::class, 'no lleva un valor entero');
});

it('no deja leer un valor fuera de la escala', function (): void {
    expect(fn () => escala(escalones(1, 2, 3))->nivelDe(4))
        ->toThrow(EscalaInvalida::class, 'no está en una escala de 1 a 3');
});

it('sobrevive a la ida y vuelta que congela cada valoración', function (): void {
    $original = EscalaRiesgo::desdeArray([
        ['valor' => 1, 'etiqueta' => 'Muy baja', 'descripcion' => 'Casi nunca.'],
        ['valor' => 2, 'etiqueta' => 'Alta', 'descripcion' => null],
    ]);

    $recuperada = EscalaRiesgo::desdeArray($original->aArray());

    expect($recuperada->equivale($original))->toBeTrue()
        ->and($recuperada->nivelDe(1)->descripcion)->toBe('Casi nunca.')
        ->and($recuperada->nivelDe(2)->descripcion)->toBeNull();
});

it('no distingue dos escalas iguales', function (): void {
    expect(escala(escalones(1, 2, 3))->equivale(escala(escalones(1, 2, 3))))->toBeTrue()
        ->and(escala(escalones(1, 2, 3))->equivale(escala(escalones(1, 2))))->toBeFalse();
});
