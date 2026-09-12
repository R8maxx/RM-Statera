<?php

declare(strict_types=1);

use App\Domain\Documento\Cuerpo\EsquemaCuerpo;

/**
 * El esquema del cuerpo está escrito dos veces, y tiene que decir lo mismo.
 *
 * En PHP porque es la lista blanca del servidor —lo que no está, no se guarda y
 * no se pinta— y en TypeScript porque en ProseMirror **el esquema ES la lista
 * blanca**: lo que no está declarado no se puede crear, ni escribiendo, ni
 * pegando, ni arrastrando. Ninguna de las dos sobra: sin la del cliente habría
 * que sanear HTML pegado en el navegador, y sin la del servidor bastaría con
 * llamar a la ruta con `curl`.
 *
 * Lo que sí sobra es que diverjan, y divergen en silencio: un nodo que exista en
 * PHP y no en el editor **se borra al abrir el documento**, sin error y sin
 * aviso, y quien lo escribió sólo se entera abriendo el PDF.
 */
function declaradoEnElEditor(string $constante): array
{
    $fuente = (string) file_get_contents(resource_path('js/lib/cuerpoDocumento.ts'));

    $encontrado = preg_match('/export const '.$constante.' = \[(.*?)\] as const;/s', $fuente, $coincidencias);

    expect($encontrado)->toBe(1, "No se encontró «{$constante}» en cuerpoDocumento.ts");

    preg_match_all("/'([^']+)'/", $coincidencias[1], $valores);

    return $valores[1];
}

it('declara los mismos nodos en PHP y en el editor', function (): void {
    $php = array_keys(EsquemaCuerpo::NODOS);
    $editor = declaradoEnElEditor('NODOS');

    sort($php);
    sort($editor);

    expect($editor)->toBe($php);
});

it('declara las mismas marcas en PHP y en el editor', function (): void {
    $php = EsquemaCuerpo::MARCAS;
    $editor = declaradoEnElEditor('MARCAS');

    sort($php);
    sort($editor);

    expect($editor)->toBe($php);
});

/**
 * Un mapa `clave => clases` del editor, leído del fichero.
 *
 * @return array<string, string>
 */
function mapaDelEditor(string $constante): array
{
    $fuente = (string) file_get_contents(resource_path('js/lib/cuerpoDocumento.ts'));

    $encontrado = preg_match(
        '/export const '.$constante.': Record<string, string> = \{(.*?)\n\};/s',
        $fuente,
        $coincidencias,
    );

    expect($encontrado)->toBe(1, "No se encontró el mapa «{$constante}» en cuerpoDocumento.ts");

    preg_match_all("/^\s*([A-Za-z_]+): '([^']*)',/m", $coincidencias[1], $pares, PREG_SET_ORDER);

    $mapa = [];

    foreach ($pares as $par) {
        $mapa[$par[1]] = $par[2];
    }

    return $mapa;
}

/**
 * Y no basta con que coincidan las claves: tienen que coincidir **los valores**.
 *
 * Desde que el editor se pinta con `documento.css` tal cual, lo que traduce una
 * clave a sus clases ya no es una hoja de estilos propia del editor: es este
 * mapa, y tiene que decir exactamente lo mismo que el de PHP. Una clase mal
 * escrita aquí no da ningún error — sencillamente ese bloque sale sin estilo, y
 * sólo se nota comparando el editor con el PDF al lado.
 *
 * `tabla--fija` es el caso que más duele: sin ella se pierde el
 * `table-layout: fixed` y las diez columnas de la SoA se reparten a ojo.
 */
it('traduce las claves enumeradas a las mismas clases en PHP y en el editor', function (string $constante): void {
    $php = match ($constante) {
        'CLASES_PARRAFO' => EsquemaCuerpo::CLASES_PARRAFO,
        'CLASES_ENCABEZADO' => EsquemaCuerpo::CLASES_ENCABEZADO,
        'ESTILOS' => EsquemaCuerpo::ESTILOS,
        'TONOS_BADGE' => EsquemaCuerpo::TONOS_BADGE,
        'VARIANTES_CAJA' => EsquemaCuerpo::VARIANTES_CAJA,
        'CLASES_TABLA' => EsquemaCuerpo::CLASES_TABLA,
        'CLASES_FILA' => EsquemaCuerpo::CLASES_FILA,
        'CLASES_CELDA' => EsquemaCuerpo::CLASES_CELDA,
    };

    $editor = mapaDelEditor($constante);

    ksort($php);
    ksort($editor);

    expect($editor)->toBe($php);
})->with([
    'CLASES_PARRAFO',
    'CLASES_ENCABEZADO',
    'ESTILOS',
    'TONOS_BADGE',
    'VARIANTES_CAJA',
    'CLASES_TABLA',
    'CLASES_FILA',
    'CLASES_CELDA',
]);

/**
 * `trailingNode` de StarterKit añade un nodo vacío al final del documento para
 * que siempre se pueda escribir debajo del último bloque. Con un `doc`
 * corriente es un párrafo; aquí el `doc` es `portada seccion+` y lo único que
 * cabe al final es una SECCIÓN.
 *
 * Con él encendido, **abrir el editor y guardar añadía una sección vacía**, y
 * cada visita añadía otra: páginas en blanco en el PDF del auditor que nadie
 * había escrito. No hay forma de probarlo desde PHP, así que se fija que la
 * extensión siga apagada, que es lo que se olvida al tocar esta configuración.
 */
it('apaga el nodo final de StarterKit, que aquí sería una sección vacía', function (): void {
    $fuente = (string) file_get_contents(resource_path('js/lib/cuerpoDocumento.ts'));

    expect($fuente)->toContain('trailingNode: false');
});

/** Lo mismo con el subrayado: no está en `MARCAS`, así que el servidor lo poda. */
it('no ofrece marcas que el servidor va a podar', function (): void {
    $fuente = (string) file_get_contents(resource_path('js/lib/cuerpoDocumento.ts'));

    expect(EsquemaCuerpo::admiteMarca('underline'))->toBeFalse()
        ->and($fuente)->toContain('underline: false');
});
