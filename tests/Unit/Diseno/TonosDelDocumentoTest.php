<?php

declare(strict_types=1);

use App\Domain\Documento\Cuerpo\EsquemaCuerpo;
use App\Domain\Documento\Cuerpo\Nodo;

/*
|--------------------------------------------------------------------------
| Los tonos que el documento sabe pintar
|--------------------------------------------------------------------------
|
| Tercera vez que el producto cierra el mismo fallo silencioso. `IconoTipo` no
| pintaba nada si el nombre no estaba en su mapa y lo cerró `IconosTest`;
| `tonos.ts` devolvía gris y lo cerró `TonosTest`; y el documento hace lo mismo:
| un tono que no esté en `EsquemaCuerpo::TONOS_BADGE` sale como **chip neutro**
| —gris y sin punto—, que es el tono de «procedencia, no estado».
|
| Duele más que los dos anteriores porque el destino es un PDF que se le entrega
| a un auditor: un badge gris donde tenía que ir un estado no se corrige
| recargando la página.
|
| Lo destapó el acta de la revisión por la dirección (§ 4.15), que es el primer
| documento que imprime badges de objetivos de seguridad: `en_revision` no estaba
| en el mapa y «Propuesto» salía como chip neutro.
|
*/

/** Los tonos que declara el esquema del cuerpo. */
function tonosDelDocumento(): array
{
    return array_keys(EsquemaCuerpo::TONOS_BADGE);
}

/** Las clases `badge--*` que la hoja del documento sabe pintar. */
function clasesDeBadgeDelCss(): array
{
    $fuente = (string) file_get_contents(base_path('resources/documentos/documento.css'));

    // Se leen del fichero y no de una copia, por lo mismo que en `IconosTest`:
    // una copia se desincroniza justo del fichero que importa.
    preg_match_all('/^\.badge--([a-z_]+)\s*\{/m', $fuente, $nombres);

    return array_values(array_unique($nombres[1]));
}

it('todo tono del esquema tiene su clase en la hoja del documento', function (): void {
    $delCss = clasesDeBadgeDelCss();

    foreach (tonosDelDocumento() as $tono) {
        expect(in_array($tono, $delCss, true))->toBeTrue(sprintf(
            'El tono `%s` está en EsquemaCuerpo::TONOS_BADGE y no tiene `.badge--%s` en documento.css, '
            .'así que ese badge saldría sin color en el PDF.',
            $tono,
            $tono,
        ));
    }
});

it('la hoja del documento no arrastra clases que ya no usa nadie', function (): void {
    $delEsquema = tonosDelDocumento();

    foreach (clasesDeBadgeDelCss() as $clase) {
        expect(in_array($clase, $delEsquema, true))->toBeTrue(
            "La clase `.badge--{$clase}` está en documento.css y no la emite ningún tono del esquema.",
        );
    }
});

/**
 * El que de verdad importa: que el fallo haya dejado de ser silencioso.
 *
 * `RenderizadorCuerpo` cae a `neutro` cuando no reconoce el tono, y eso es lo
 * correcto para un cuerpo **editado** —`SanearCuerpo` anula el tono que no
 * reconoce y el badge tiene que pintarse igual—. Lo que no puede pasar es que un
 * **generador** emita un tono desconocido y el PDF salga con el badge apagado sin
 * que nadie se entere, así que `Nodo::badge()` lo rechaza en el sitio por donde
 * sólo pasa código.
 *
 * **No se recorren los enums del dominio a propósito.** El vocabulario tiene
 * familias que el documento no imprime nunca —los nueve `--tipo-*`, los cuatro
 * `--dafo-*`, las cuatro prioridades y los tres ordinales— y exigirle al papel
 * que las conozca sería pedirle que supiera pintar badges que ningún generador
 * le pasa. Lo que se fija es la puerta, no el inventario.
 */
it('un generador no puede emitir un tono que el documento no sepa pintar', function (): void {
    expect(fn () => Nodo::badge('tipo:datos', 'Datos'))
        ->toThrow(LogicException::class, 'no está en EsquemaCuerpo::TONOS_BADGE');
});

it('y acepta todos los que sí están en el mapa', function (): void {
    foreach (tonosDelDocumento() as $tono) {
        $badge = Nodo::badge($tono, 'Prueba');

        expect($badge['attrs']['tono'])->toBe($tono);
    }
});
