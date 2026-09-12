<?php

declare(strict_types=1);

use App\Domain\Documento\Cuerpo\CuerpoDeFabrica;
use App\Domain\Documento\Cuerpo\EsquemaCuerpo;
use App\Domain\Documento\Cuerpo\Nodo;
use App\Domain\Documento\Cuerpo\RenderizadorCuerpo;
use App\Domain\Documento\Enums\TipoDocumento;

/**
 * El cuerpo del documento lo escribe una persona y acaba dentro de un PDF que se
 * entrega a un auditor. `RenderizadorCuerpo` es lo único que hay entre las dos
 * cosas, y su regla es la omisión: lo que no está declarado no se pinta.
 *
 * Estos tests fijan esa regla desde fuera. Ninguno comprueba que algo «se
 * sanea»; todos comprueban que algo **no sale**, que es una afirmación mucho más
 * fuerte y la única que se sostiene sola cuando mañana entre un nodo nuevo.
 */
function pintar(array $nodos): string
{
    return (new RenderizadorCuerpo)->aHtml(Nodo::de('doc', [], $nodos));
}

it('no pinta un nodo que no existe en el esquema', function (array $nodo): void {
    expect(pintar([$nodo]))->toBe('');
})->with([
    'un tipo inventado' => [['type' => 'iframe', 'content' => [['type' => 'text', 'text' => 'hola']]]],
    'un tipo vacío' => [['type' => '']],
    'sin tipo' => [['content' => [['type' => 'text', 'text' => 'hola']]]],
    'un tipo que no es cadena' => [['type' => 42]],
    // No hay nodo de imagen a propósito: una remota tumbaría la generación
    // entera —`failOnResourceLoadingFailed()`— y una incrustada hincharía la
    // instantánea sin límite.
    'una imagen' => [['type' => 'image', 'attrs' => ['src' => 'https://example.test/x.png']]],
    'un bloque de HTML crudo' => [['type' => 'html', 'attrs' => ['html' => '<script>1</script>']]],
]);

it('escapa el marcado que venga dentro de un texto', function (): void {
    $html = pintar([Nodo::parrafo('<script>alert(1)</script> & "comillas"')]);

    expect($html)->toBe('<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;comillas&quot;</p>')
        ->and($html)->not->toContain('<script>');
});

it('deja el texto de un enlace peligroso pero le quita la navegación', function (string $href): void {
    $html = pintar([Nodo::de('paragraph', [], [Nodo::enlace('Pulse aquí', $href)])]);

    // Lo que se quita es el enlace, no lo que la frase dice: borrar el texto
    // cambiaría el documento sin que nadie se entere.
    expect($html)->toBe('<p>Pulse aquí</p>')
        ->and($html)->not->toContain('href');
})->with([
    'javascript' => ['javascript:alert(1)'],
    'javascript en mayúsculas' => ['JavaScript:alert(1)'],
    'data' => ['data:text/html;base64,PHNjcmlwdD4='],
    'file' => ['file:///etc/passwd'],
    // Gotenberg descarga lo que se le ponga por delante: un enlace a la red
    // interna dentro de un documento es un SSRF con membrete.
    'gopher' => ['gopher://10.0.0.1:11211/'],
]);

it('pinta los enlaces que sí puede', function (string $href): void {
    expect(pintar([Nodo::de('paragraph', [], [Nodo::enlace('Statera', $href)])]))
        ->toContain('href="'.$href.'"');
})->with([
    'https' => ['https://statera.test/politica'],
    'http' => ['http://statera.test/politica'],
    'correo' => ['mailto:seguridad@statera.test'],
]);

it('no deja colar una clase que no esté enumerada', function (): void {
    $html = pintar([
        Nodo::de('paragraph', ['clase' => 'portada__filete'], [Nodo::texto('a')]),
        Nodo::de('table', ['clase' => 'tabla--inventada'], [
            Nodo::fila([Nodo::celdaTexto('b', 'clase-libre')]),
        ]),
        Nodo::de('badge', ['tono' => 'rojo'], [Nodo::texto('c')]),
    ]);

    expect($html)->not->toContain('portada__filete')
        ->and($html)->not->toContain('tabla--inventada')
        ->and($html)->not->toContain('clase-libre')
        ->and($html)->not->toContain('badge--rojo')
        // Un tono desconocido cae al neutro en vez de quedarse sin badge: la
        // celda sigue diciendo lo que decía.
        ->and($html)->toContain('badge badge--neutro');
});

it('no deja escribir estilo en línea libre', function (): void {
    $html = pintar([
        Nodo::de('paragraph', ['estilo' => 'position: fixed; top: 0'], [Nodo::texto('a')]),
        Nodo::de('table', [], [
            Nodo::fila([Nodo::cabeceraCelda('b', 'expression(alert(1))')]),
        ]),
    ]);

    expect($html)->not->toContain('position')
        ->and($html)->not->toContain('expression')
        ->and($html)->not->toContain('style=');
});

it('admite los cuatro estilos enumerados y los escribe enteros', function (): void {
    $html = pintar([Nodo::de('paragraph', ['estilo' => 'formula'], [Nodo::texto('C·I·T·A·D')])]);

    expect($html)->toBe('<p style="'.e(EsquemaCuerpo::ESTILOS['formula']).'">C·I·T·A·D</p>');
});

it('sólo admite anchos de columna con forma de medida', function (string $ancho, bool $valido): void {
    $html = pintar([Nodo::de('table', [], [Nodo::fila([Nodo::cabeceraCelda('Control', $ancho)])])]);

    expect(str_contains($html, 'width'))->toBe($valido);
})->with([
    ['0.62in', true],
    ['10pt', true],
    ['25%', true],
    ['auto', false],
    ['1in; position: fixed', false],
    ['999999in', false],
]);

it('descarta las marcas que no conoce y conserva las que sí', function (): void {
    $nodo = [
        'type' => 'text',
        'text' => 'texto',
        'marks' => [['type' => 'bold'], ['type' => 'subrayado'], ['type' => 'cifra']],
    ];

    expect(pintar([Nodo::de('paragraph', [], [$nodo])]))
        ->toBe('<p><span class="cifra"><strong>texto</strong></span></p>');
});

it('no pinta el SVG que le metan por el cuerpo', function (): void {
    // La gráfica guarda el reparto, no el dibujo: el SVG lo hace `GraficaSvg` al
    // renderizar y por eso el marcado vectorial nunca pasa por aquí.
    $html = pintar([
        Nodo::de('grafica', ['segmentos' => [
            ['clave' => 'implantado', 'etiqueta' => 'Implantados', 'valor' => 3],
            ['clave' => 'inventado', 'etiqueta' => 'Colado', 'valor' => 9],
        ]]),
    ]);

    expect($html)->toContain('var(--estado-implantado)')
        ->and($html)->not->toContain('estado-inventado');
});

it('no pinta una gráfica sin datos, ni su caja vacía', function (): void {
    expect(pintar([Nodo::de('grafica', ['segmentos' => []])]))->toBe('')
        ->and(pintar([Nodo::de('grafica', ['segmentos' => 'no es una lista'])]))->toBe('');
});

it('pinta el esqueleto de fábrica sin perder ninguna negrita', function (TipoDocumento $tipo): void {
    $html = (new RenderizadorCuerpo)->aHtml(CuerpoDeFabrica::para($tipo));

    expect($html)->toContain('<section class="portada">')
        ->and($html)->toContain('<div class="portada__filete"></div>')
        ->and($html)->toContain('<h2>Introducción</h2>')
        ->and($html)->toContain('<strong>')
        ->and($html)->not->toContain('<script');
})->with(TipoDocumento::cases());
