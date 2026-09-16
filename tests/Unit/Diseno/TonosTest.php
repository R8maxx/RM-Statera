<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Los tonos del dominio
|--------------------------------------------------------------------------
|
| `lib/tonos.ts` es el único mapa de tono → clases, y su función `tono()` acaba
| en `?? neutro`: **un tono que el servidor emita y que no esté en el mapa no
| falla, sale gris**. Es exactamente el fallo que `IconoTipo` tenía con los
| iconos y que `IconosTest` convirtió en rojo; en tonos no lo cubría nada.
|
| Duele más que el de los iconos, además, porque un badge sin icono se nota y un
| badge gris parece una decisión: «no iniciado» y «gris porque falta el tono» se
| pintan igual.
|
| Los enums no se enumeran a mano: se descubren con `enumsDelDominioCon('tono')`,
| así que el enum que traiga el módulo siguiente entra solo.
|
*/

/**
 * Los nombres de tono que `lib/tonos.ts` sabe resolver.
 *
 * Se leen del fichero y no de una copia, por lo mismo que en `IconosTest`: una
 * copia se desincroniza justo del fichero que importa.
 *
 * @return list<string>
 */
function tonosDelCliente(): array
{
    $fuente = (string) file_get_contents(base_path('resources/js/lib/tonos.ts'));

    // Las cinco tablas de tonos, más los alias, que también son nombres válidos.
    preg_match_all(
        '/const (?:estados|ordinales|prioridades|procedencia|tipos|propios|alias): Record<string, (?:Tono|string)> = \{(.*?)\n\};/s',
        $fuente,
        $bloques,
    );

    expect($bloques[1])->not->toBeEmpty('No se encuentran los mapas de tono en lib/tonos.ts.');

    $nombres = [];

    foreach ($bloques[1] as $bloque) {
        /*
         * Una clave por línea, al primer nivel de indentación: `nombre: {`, o
         * `nombre: 'otro',` en el caso de los alias. Van entrecomilladas cuando
         * llevan guion o dos puntos —`'prioridad-alta'`, `'tipo:datos'`—, así
         * que las comillas son opcionales y el nombre admite los dos.
         */
        preg_match_all("/^\s{4}'?([a-z0-9_:-]+)'?:/m", $bloque, $claves);
        $nombres = [...$nombres, ...$claves[1]];
    }

    return array_values(array_unique($nombres));
}

it('cada caso de cada enum declara su tono', function (string $enum): void {
    foreach ($enum::cases() as $caso) {
        expect($caso->tono())->not->toBe(
            '',
            "El caso `{$caso->value}` de {$enum} no declara tono.",
        );
    }
})->with(fn () => enumsDelDominioCon('tono'));

/**
 * El que de verdad importa. Un tono que no está en el mapa se pinta gris y
 * nadie se entera, así que aquí falla.
 */
it('todos los tonos del servidor existen en el mapa del cliente', function (): void {
    $delCliente = tonosDelCliente();

    foreach (enumsDelDominioCon('tono') as $enum) {
        foreach ($enum::cases() as $caso) {
            expect(in_array($caso->tono(), $delCliente, true))->toBeTrue(sprintf(
                'El tono `%s` de %s::%s no está en lib/tonos.ts, así que ese badge se pintaría '
                .'en gris neutro y nadie se enteraría.',
                $caso->tono(),
                class_basename($enum),
                $caso->name,
            ));
        }
    }
});
