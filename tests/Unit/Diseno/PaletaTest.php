<?php

declare(strict_types=1);

use Tests\Diseno\Color;
use Tests\Diseno\Paleta;

/*
|--------------------------------------------------------------------------
| El validador de paletas
|--------------------------------------------------------------------------
|
| DESIGN.md §3 dice: «las cifras salen de ejecutar el validador de paletas sobre
| los hex de esta tabla, no de estimarlas. Si se retoca un `--estado-*`, se
| vuelve a medir». Ese validador no existía: las cifras estaban escritas en el
| documento y no había forma de comprobarlas.
|
| Esto es el validador. Lee los tokens del CSS que de verdad pinta —no una copia—
| y falla si alguien retoca un color y se lleva por delante el contraste.
|
| `en_revision` no entra: está declarado como `var(--violeta-600)` y no como un
| `oklch` propio, así que lo mide la tabla del violeta.
|
*/

it('cada estado se lee sobre su propio fondo suave', function (string $tema): void {
    foreach (Paleta::paresDeEstado($tema) as $nombre => $par) {
        $texto = Color::aLineal(...$par['texto']);
        $fondo = Color::aLineal(...$par['fondo']);
        $contraste = Color::contraste($texto, $fondo);

        expect($contraste)->toBeGreaterThanOrEqual(
            4.5,
            sprintf(
                'El tono `%s` en tema %s da %.2f:1 sobre su fondo suave (%s sobre %s). DESIGN.md §11 '
                .'pide 4.5:1 para texto normal, y un badge de estado es texto normal.',
                $nombre,
                $tema,
                $contraste,
                Color::aHex($texto),
                Color::aHex($fondo),
            ),
        );
    }
})->with(['claro', 'oscuro']);

/**
 * El par que DESIGN.md tenía anotado como deuda: `implantado` y `en_progreso` no
 * se distinguían con protanopía —ΔE 5.7, por debajo del suelo de 6—.
 *
 * Dos tonos que no llegan al suelo pueden convivir, pero **sólo si algo que no
 * es el color los separa**: en este producto, el icono del estado. Lo que este
 * test impide es que alguien acerque dos tonos creyendo que no pasa nada.
 */
it('los estados con color se distinguen entre sí, o dependen del icono', function (): void {
    $pares = Paleta::paresDeEstado('claro');

    // Los dos grises están juntos a propósito: «no iniciado» y «no aplica» no
    // son grados de lo mismo y no compiten por un hueco de color. Los separan el
    // icono y la palabra, que es justo lo que DESIGN.md manda cuando el color no
    // llega.
    $separadosPorElIcono = [['estado-no-iniciado', 'estado-no-aplica']];

    $nombres = array_keys($pares);

    foreach ($nombres as $i => $uno) {
        foreach (array_slice($nombres, $i + 1) as $otro) {
            if (in_array([$uno, $otro], $separadosPorElIcono, true)) {
                continue;
            }

            $distancia = Color::distanciaConProtanopia(
                Color::aLineal(...$pares[$uno]['texto']),
                Color::aLineal(...$pares[$otro]['texto']),
            );

            expect($distancia)->toBeGreaterThanOrEqual(
                6.0,
                sprintf(
                    '`%s` y `%s` quedan a ΔE %.1f con protanopía, por debajo del suelo de 6. O se '
                    .'separan los tonos, o se declaran como separados por el icono.',
                    $uno,
                    $otro,
                    $distancia,
                ),
            );
        }
    }
});

/*
|--------------------------------------------------------------------------
| Los cuatro cuadrantes del DAFO (§ 4.1)
|--------------------------------------------------------------------------
|
| Tercera familia semántica, y la única que se mira **de cuatro en cuatro**: la
| matriz enseña los cuatro cuadrantes a la vez, uno al lado del otro. Por eso
| aquí el suelo de ΔE 6 se exige entero y sin excepciones declaradas, a
| diferencia de `--estado-*` —que tiene la pareja de grises— y de `--tipo-*`,
| donde la peor pareja se queda en 5.2.
|
*/

it('cada cuadrante del DAFO se lee sobre su propio fondo suave', function (string $tema): void {
    $pares = Paleta::paresDeDafo($tema);

    expect($pares)->toHaveCount(4, "No se encuentran los cuatro `--dafo-*` en el tema {$tema}.");

    foreach ($pares as $nombre => $par) {
        $contraste = Color::contraste(
            Color::aLineal(...$par['texto']),
            Color::aLineal(...$par['fondo']),
        );

        expect($contraste)->toBeGreaterThanOrEqual(4.5, sprintf(
            'El tono `%s` en tema %s da %.2f:1 sobre su fondo suave. Un badge de cuadrante es texto normal.',
            $nombre,
            $tema,
            $contraste,
        ));
    }
})->with(['claro', 'oscuro']);

it('los cuatro cuadrantes se distinguen entre sí', function (): void {
    $pares = Paleta::paresDeDafo('claro');
    $nombres = array_keys($pares);

    foreach ($nombres as $i => $uno) {
        foreach (array_slice($nombres, $i + 1) as $otro) {
            $distancia = Color::distanciaConProtanopia(
                Color::aLineal(...$pares[$uno]['texto']),
                Color::aLineal(...$pares[$otro]['texto']),
            );

            expect($distancia)->toBeGreaterThanOrEqual(6.0, sprintf(
                '`%s` y `%s` quedan a ΔE %.1f con protanopía. Los cuatro cuadrantes se miran juntos '
                .'en la matriz, así que aquí el suelo de 6 no admite excepciones.',
                $uno,
                $otro,
                $distancia,
            ));
        }
    }
});

/**
 * La familia es profunda a propósito: es lo único que la separa de las otras dos,
 * porque los nueve `--tipo-*` ocupan ya la rueda de hue entera.
 *
 * Sin esta comprobación, alguien que aclarara los `--dafo-*` para «que se vean
 * mejor» los acercaría a los estados sin que nada avisara, y entonces una
 * fortaleza empezaría a leerse como un «implantado».
 */
it('el DAFO es más profundo que los estados y que los tipos', function (): void {
    foreach (Paleta::paresDeDafo('claro') as $nombre => $par) {
        expect($par['texto'][0])->toBeLessThan(0.45, sprintf(
            '`%s` tiene L %.2f. Los estados están en 0.52 y los tipos en 0.47: lo que hace que esta '
            .'familia se lea como propia es ser más oscura, no el hue.',
            $nombre,
            $par['texto'][0],
        ));
    }
});

/**
 * Las conversiones se comprueban contra cifras que ya estaban escritas en
 * DESIGN.md antes de existir este código: si el conversor se rompiera, los
 * dos tests de arriba dejarían de medir lo que dicen medir.
 */
it('reproduce los hex que DESIGN.md tenía medidos', function (): void {
    expect(Color::aHex(Color::aLineal(0.52, 0.13, 155)))->toBe('#007E46')
        ->and(Color::aHex(Color::aLineal(0.52, 0.13, 245)))->toBe('#036EAE')
        ->and(Color::aHex(Color::aLineal(0.52, 0.13, 196)))->toBe('#007E81');
});

it('reproduce la deuda de protanopía que DESIGN.md tenía anotada', function (): void {
    // Los valores de antes del arreglo: el documento decía ΔE 5.7.
    $distancia = Color::distanciaConProtanopia(
        Color::aLineal(0.52, 0.13, 155),
        Color::aLineal(0.62, 0.14, 70),
    );

    expect($distancia)->toBeGreaterThan(5.0)->toBeLessThan(6.5);
});
