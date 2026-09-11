<?php

declare(strict_types=1);

use App\Domain\Documento\Narrativa\MarkdownDocumento;

/**
 * El renderizador del texto que escribe la organización.
 *
 * Es la única superficie de inyección del módulo: detrás hay un PDF que alguien
 * firma y le entrega a un auditor.
 */
beforeEach(function (): void {
    $this->md = new MarkdownDocumento;
});

it('pinta el subconjunto que se admite', function (): void {
    expect($this->md->aHtml('Texto con **negrita** y *cursiva*.'))
        ->toBe('<p>Texto con <strong>negrita</strong> y <em>cursiva</em>.</p>');

    expect($this->md->aHtml("- uno\n- dos"))
        ->toContain('<ul>')->toContain('<li>uno</li>');

    expect($this->md->aHtml('[enlace](https://ejemplo.es)'))
        ->toContain('<a href="https://ejemplo.es">enlace</a>');
});

it('no deja que el usuario produzca un h1 ni se salte un nivel', function (string $entrada, string $etiqueta): void {
    // El `<h2>` de cada sección lo pone la plantilla; lo del usuario va debajo.
    // Es lo que mantiene la jerarquía que exige PDF/UA.
    expect($this->md->aHtml($entrada))->toStartWith("<{$etiqueta}>");
})->with([
    ['# Uno', 'h3'],
    ['## Dos', 'h3'],
    ['### Tres', 'h4'],
    ['#### Cuatro', 'h4'],
    ['###### Seis', 'h4'],
]);

it('en el editor NO desplaza, o cada guardado bajaría un nivel más', function (): void {
    expect($this->md->aHtmlDeEditor('## Dos'))->toBe('<h2>Dos</h2>');
    expect($this->md->aHtmlDeEditor('### Tres'))->toBe('<h3>Tres</h3>');
});

it('quita las imágenes, que tumbarían la generación entera', function (): void {
    /*
     * `failOnResourceLoadingFailed()` está encendido y la allow-list de
     * Gotenberg es `^(file:///tmp/|data:).*`: un `![](https://…)` escrito por
     * cualquiera haría fallar el PDF con un error que apunta a Gotenberg.
     */
    $html = $this->md->aHtml('![una foto](https://ejemplo.es/x.png)');

    expect($html)->not->toContain('<img')
        // El texto alternativo se conserva: quien lo escribió quería decir algo.
        ->and($html)->toContain('una foto');
});

it('no deja pasar HTML ni enlaces peligrosos', function (string $entrada, string $prohibido): void {
    expect($this->md->aHtml($entrada))->not->toContain($prohibido);
})->with([
    ['<script>alert(1)</script>', '<script'],
    ['<img src=x onerror=alert(1)>', 'onerror'],
    ['<b>hola</b>', '<b>'],
    ['[x](javascript:alert(1))', 'javascript:'],
    ['[x](data:text/html;base64,PHNjcmlwdD4=)', 'data:text/html'],
]);

it('aguanta una lista absurdamente anidada sin reventar', function (): void {
    $entrada = '';

    for ($i = 0; $i < 50; $i++) {
        $entrada .= str_repeat('  ', $i)."- nivel {$i}\n";
    }

    expect($this->md->aHtml($entrada))->toContain('nivel 0');
});

it('normaliza lo que llega pegado desde un procesador de textos', function (): void {
    // Los finales de línea de Windows los mete cualquiera que pegue desde Word,
    // y CommonMark los trata distinto.
    expect($this->md->normalizar("Uno\r\n\r\nDos"))->toBe("Uno\n\nDos");
    expect($this->md->normalizar("Uno\n\n\n\n\nDos"))->toBe("Uno\n\nDos");
    expect($this->md->normalizar("  Hola  \n"))->toBe('Hola');
    expect($this->md->normalizar(null))->toBe('');
});

it('no pinta nada cuando no hay nada', function (): void {
    expect($this->md->aHtml(''))->toBe('');
    expect($this->md->aHtml("   \n  "))->toBe('');
});
