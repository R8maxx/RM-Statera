<?php

declare(strict_types=1);

use App\Domain\Obligacion\Cadencia;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| La cadencia
|--------------------------------------------------------------------------
|
| Un entero de meses y no un enum, que es lo que ya hacen los documentos y la
| metodología de riesgos. Aquí se comprueban las dos cosas que el entero tiene
| que dar: el nombre con el que se dice en el sector y la aritmética de fechas.
|
*/

it('nombra las cadencias que tienen nombre y dice el número para el resto', function (int $meses, string $etiqueta): void {
    expect((new Cadencia($meses))->etiqueta())->toBe($etiqueta);
})->with([
    [1, 'Mensual'],
    [3, 'Trimestral'],
    [6, 'Semestral'],
    [12, 'Anual'],
    // La que ningún enum de periodicidad del producto sabía decir, y con la que
    // se renueva la conformidad del ENS.
    [24, 'Bienal'],
    [36, 'Trienal'],
    [18, 'Cada 18 meses'],
]);

/**
 * `addMonthsNoOverflow` y no aritmética a mano: sumar un mes al 31 de enero no
 * da el 31 de febrero, y ése es justo el fallo que se ve en marzo.
 */
it('suma meses sin desbordar al mes siguiente', function (string $desde, int $meses, string $esperada): void {
    expect((new Cadencia($meses))->despuesDe(Carbon::parse($desde))->toDateString())->toBe($esperada);
})->with([
    ['2026-01-31', 1, '2026-02-28'],
    ['2028-01-31', 1, '2028-02-29'],
    ['2026-01-31', 12, '2027-01-31'],
    ['2028-02-29', 12, '2029-02-28'],
    ['2026-03-14', 24, '2028-03-14'],
    ['2026-12-31', 1, '2027-01-31'],
]);
