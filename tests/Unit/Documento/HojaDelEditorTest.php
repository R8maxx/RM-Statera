<?php

declare(strict_types=1);

/**
 * La hoja del documento se acota, no se copia.
 *
 * `resources/js/lib/hojaDocumento.ts` coge `resources/documentos/documento.css`
 * tal cual y le hace **cuatro** cambios para poder meterla dentro de un
 * `@scope`: quita el `@page`, quita el `@media screen`, y renombra `:root` y
 * `body` a `:scope`.
 *
 * Esas cuatro sustituciones son búsquedas de texto sobre un fichero que nadie
 * obliga a tener esta forma. El día que alguien reorganice la hoja —parta el
 * `:root` en dos, envuelva el cuerpo en otra media query— las sustituciones
 * dejarían de casar y **el editor se despintaría sin ningún error**: sin
 * tipografía, sin tamaños y con los colores de la aplicación por debajo. Nadie
 * lo vería hasta abrir el editor, y quien lo viera pensaría que se rompió otra
 * cosa.
 *
 * Así que lo que se fija aquí no es el estilo: es la forma de la que depende la
 * traducción. Si este test se pone rojo, hay que mirar `hojaDocumento.ts` antes
 * de tocar nada.
 */
function hojaDelDocumento(): string
{
    return (string) file_get_contents(resource_path('documentos/documento.css'));
}

it('tiene un solo bloque «:root», que es donde viven las variables', function (): void {
    expect(preg_match_all('/^:root\s*\{/m', hojaDelDocumento()))->toBe(1);
});

it('tiene un solo «@page», que se quita porque anidado no vale', function (): void {
    expect(preg_match_all('/^@page\s*\{/m', hojaDelDocumento()))->toBe(1);
});

it('tiene un solo «@media screen», que es el del volcado --html', function (): void {
    expect(preg_match_all('/^@media screen\s*\{/m', hojaDelDocumento()))->toBe(1);
});

/**
 * Dos: el de dentro del `@media screen` —que se va con él— y el de verdad, que
 * es el que lleva la familia, el cuerpo de 8,5 pt y el color del texto. Ése es
 * el que pasa a `:scope`, y sin él el documento saldría con la tipografía de la
 * interfaz.
 */
it('tiene dos bloques «body», y el de fuera es el que se renombra', function (): void {
    expect(preg_match_all('/^\s*body\s*\{/m', hojaDelDocumento()))->toBe(2);
});

/**
 * Las fuentes no viajan en la hoja: `AssetsDocumento` antepone los `@font-face`
 * con los `.woff2` en `data:` y define las dos variables. En el navegador no hay
 * nada de eso, así que `hojaDocumento.ts` las apunta a las familias que ya sirve
 * `app.css`. Si la hoja dejara de usarlas, ese complemento sobraría.
 */
it('deja la familia en variables, no escrita', function (): void {
    expect(hojaDelDocumento())
        ->toContain('var(--fuente-documento)')
        ->toContain('var(--fuente-cifra)');
});

/**
 * La raíz del ámbito es la clase que `EditorCuerpo` le pone a ProseMirror. Si
 * una de las dos cambia sin la otra, el `@scope` no casa con nada y vuelve a
 * pasar lo mismo: hoja puesta, documento sin pintar.
 */
it('acota la hoja a la misma clase que usa el editor', function (): void {
    $hoja = (string) file_get_contents(resource_path('js/lib/hojaDocumento.ts'));
    $editor = (string) file_get_contents(resource_path('js/components/documento/EditorCuerpo.vue'));

    expect($hoja)->toContain("export const RAIZ = 'documento-editor';");
    expect($editor)->toContain('${RAIZ} focus:outline-none');
});
