<?php

declare(strict_types=1);

use App\Domain\Documento\Render\LectorPaginas;

/**
 * La cuenta de páginas de la pasada de medida, sin `pdftotext`.
 *
 * `pdftotext` termina cada página con un salto de página; lo que se prueba aquí
 * es lo que se hace con ese texto, que es donde un error de uno desplaza todos
 * los números del índice sin que nada falle.
 */
it('da a cada sección la página donde aparece su marcador, contando desde uno', function (): void {
    $texto = "Índice\f@@s-1@@ Introducción\fsigue\f@@s-2@@ Resumen\f@@s-3@@ Tabla\fsigue\fsigue\f";

    expect(LectorPaginas::desdeTexto($texto))->toBe([
        'paginas' => ['s-1' => 2, 's-2' => 4, 's-3' => 5],
        'total' => 7,
    ]);
});

it('cuenta la última página aunque no acabe en salto de página', function (): void {
    expect(LectorPaginas::desdeTexto("uno\f@@s-1@@ dos")['total'])->toBe(2);
});

it('se queda con la primera aparición de un marcador', function (): void {
    expect(LectorPaginas::desdeTexto("@@s-1@@\f@@s-1@@\f")['paginas'])->toBe(['s-1' => 1]);
});

it('no inventa la página de un marcador que no aparece', function (): void {
    expect(LectorPaginas::desdeTexto("Índice\ftexto sin marcas\f")['paginas'])->toBe([]);
});
