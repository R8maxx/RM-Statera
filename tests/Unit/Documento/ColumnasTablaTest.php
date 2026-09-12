<?php

declare(strict_types=1);

use App\Domain\Documento\Cuerpo\ColumnasTabla;
use App\Domain\Documento\Cuerpo\EsquemaCuerpo;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Render\GeometriaPagina;

/**
 * La tabla larga tiene que caber en la hoja.
 *
 * `documento.css` pone `table-layout: fixed` en la tabla larga —es lo que hace
 * que las diez u once columnas respeten su anchura en vez de repartirse a ojo—,
 * y con esa disposición **las anchuras declaradas ganan al `width: 100%`**. Si
 * suman más que la caja de texto, la tabla sale más ancha que ella: la última
 * columna se imprime dentro del margen derecho y se corta en el borde del papel.
 *
 * Y no avisa de nada. En el PDF no hay error, ni barra de desplazamiento, ni
 * hueco evidente: sólo una columna más estrecha de lo que debería con un texto
 * que acaba antes de tiempo. Sumaban 11,32 y 11,24 pulgadas contra 10,28
 * disponibles, y lo destapó el editor al componer sobre la misma hoja.
 *
 * La cuenta sale de `GeometriaPagina` y no de un número escrito aquí: el día que
 * un tipo de documento se imprima en otro tamaño, este test se entera solo.
 */
function anchoUtilDeLaHoja(): float
{
    return (float) GeometriaPagina::ANCHO - 2 * (float) GeometriaPagina::MARGEN_LATERAL;
}

it('declara anchuras que caben en la caja de texto', function (TipoDocumento $tipo): void {
    $suma = array_sum(array_map(
        static fn (array $columna): float => (float) rtrim($columna['ancho'], 'in'),
        ColumnasTabla::para($tipo),
    ));

    expect($suma)->toBeLessThanOrEqual(anchoUtilDeLaHoja());
})->with(TipoDocumento::cases());

/**
 * Y que no se quede corta: una tabla que ocupe el setenta por ciento de la
 * página desperdicia el apaisado, que es justo el motivo de imprimir apaisado.
 */
it('aprovecha la hoja, sin dejar media página en blanco', function (TipoDocumento $tipo): void {
    $suma = array_sum(array_map(
        static fn (array $columna): float => (float) rtrim($columna['ancho'], 'in'),
        ColumnasTabla::para($tipo),
    ));

    expect($suma)->toBeGreaterThan(anchoUtilDeLaHoja() * 0.9);
})->with(TipoDocumento::cases());

/** Y que cada una siga siendo una anchura que el esquema admite. */
it('declara anchuras que el esquema del cuerpo admite', function (TipoDocumento $tipo): void {
    foreach (ColumnasTabla::para($tipo) as $columna) {
        expect(EsquemaCuerpo::anchoValido($columna['ancho']))->toBeTrue($columna['ancho']);
    }
})->with(TipoDocumento::cases());
