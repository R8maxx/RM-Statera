<?php

declare(strict_types=1);

use App\Domain\Aviso\Fuente;

/*
|--------------------------------------------------------------------------
| Dos fuentes no comparten icono
|--------------------------------------------------------------------------
|
| `IconosTest` comprueba que todo icono del servidor exista en el mapa del
| cliente, y que dos estados **del mismo tono** dentro de **un mismo enum** no lo
| compartan. Ninguna de las dos cosas cubre esto.
|
| Y aquí importa más que en cualquier otro sitio: en la rejilla del calendario el
| icono es **el único canal que identifica la fuente** —el color dice cómo va, no
| qué es—, y la fila de chips que hace de clave sólo existe en `/calendario`. En
| el popover de un día, en la agenda de móvil y en el correo el icono va solo.
|
| Nació de encontrar tres fuentes con la misma palomita en la misma posición
| —`ClipboardCheck`, `FileCheck` y `CalendarCheck`— sobre tres cuadriláteros de
| 18×18. A los 14 px a los que `IconoTipo` los pinta, eran el mismo icono.
|
*/

it('dos fuentes no comparten icono', function (): void {
    $iconos = array_map(static fn (Fuente $fuente): string => $fuente->icono(), Fuente::cases());

    $repetidos = array_keys(array_filter(array_count_values($iconos), static fn (int $veces): bool => $veces > 1));

    expect($repetidos)->toBeEmpty(sprintf(
        'Las fuentes comparten icono: %s. En el calendario el icono es el único canal que las '
        .'separa, así que dos iguales son dos chips indistinguibles.',
        implode(', ', $repetidos),
    ));
});

/**
 * El mismo criterio, un paso más allá: **ninguna fuente puede usar el icono de
 * respaldo de un tono**.
 *
 * En el chip del calendario los dos se pintan a la vez —el icono de la fuente
 * delante, el tono del estado detrás— y `lib/tonos.ts` declara un icono por tono
 * para cuando el dominio no manda uno. Si coincidieran, el chip llevaría dos veces
 * el mismo símbolo diciendo dos cosas distintas.
 *
 * Fue un riesgo real: `Obligacion` llevaba `CalendarCheck` y el tono `planificado`
 * usa `CalendarClock`.
 *
 * Se lee del fichero y no de una copia, igual que `IconosTest` con el mapa de
 * `IconoTipo` y `PermisosDeLaCuentaTest` con `navegacion.ts`: una copia se
 * desincroniza justo del fichero que importa.
 */
it('ninguna fuente usa el icono de respaldo de un tono', function (): void {
    $fuente = (string) file_get_contents(base_path('resources/js/lib/tonos.ts'));

    preg_match_all("/icono:\s*'([A-Za-z0-9]+)'/", $fuente, $encontrados);

    $deTonos = array_unique($encontrados[1]);

    expect($deTonos)->not->toBeEmpty('No se encuentra ningún icono en lib/tonos.ts.');

    foreach (Fuente::cases() as $caso) {
        expect(in_array($caso->icono(), $deTonos, true))->toBeFalse(sprintf(
            'La fuente `%s` usa `%s`, que `lib/tonos.ts` ya gasta como icono de respaldo de un '
            .'tono. Los dos se pintan en el mismo chip del calendario.',
            $caso->value,
            $caso->icono(),
        ));
    }
});
