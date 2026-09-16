<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Los iconos del dominio
|--------------------------------------------------------------------------
|
| `IconoTipo.vue` resuelve por nombre y **no pinta nada si el nombre no está en
| su mapa**: no avisa, no rompe, simplemente falta el icono. Un fallo así se
| descubre mirando la pantalla, y sólo si te fijas.
|
| Este test lo convierte en algo que falla: todo nombre que el servidor pueda
| emitir tiene que existir en el mapa del cliente.
|
*/

/**
 * Los enums que declaran icono, que son los que se pintan como badge.
 *
 * **Se descubren, no se enumeran.** Era una lista literal, y una lista literal
 * convierte esto en un test del que hay que acordarse: el enum nuevo se escribe,
 * nadie toca la lista, y el test sigue verde sin comprobar nada de lo nuevo. Es
 * el mismo defecto que `Rol::permisos()` tiene declarado en CLAUDE.md.
 */
function enumsConIcono(): array
{
    return enumsDelDominioCon('icono');
}

/** Los nombres que el mapa de `IconoTipo.vue` sabe resolver. */
function iconosDelCliente(): array
{
    $fuente = (string) file_get_contents(base_path('resources/js/components/IconoTipo.vue'));

    // El mapa es `Nombre: NombreIcon,`; se lee del fichero y no de una copia,
    // porque una copia se desincroniza justo del fichero que importa.
    preg_match('/const iconos: Record<string, Component> = \{(.*?)\n\};/s', $fuente, $bloque);

    expect($bloque)->not->toBeEmpty('No se encuentra el mapa de iconos en IconoTipo.vue.');

    preg_match_all('/^\s{4}([A-Za-z0-9]+):\s*[A-Za-z0-9]+Icon,/m', $bloque[1], $nombres);

    return $nombres[1];
}

it('cada caso de cada enum declara su icono', function (string $enum): void {
    foreach ($enum::cases() as $caso) {
        expect($caso->icono())->not->toBe(
            '',
            "El caso `{$caso->value}` de {$enum} no declara icono.",
        );
    }
})->with(fn () => enumsConIcono());

/**
 * El que de verdad importa: un nombre mal escrito en PHP no rompe nada, sólo
 * deja el badge sin icono. Aquí sí rompe.
 */
it('todos los iconos del servidor existen en el mapa del cliente', function (): void {
    $delCliente = iconosDelCliente();

    foreach (enumsConIcono() as $enum) {
        foreach ($enum::cases() as $caso) {
            // `toContain` admite varios valores, no un mensaje: la comprobación
            // va fuera para que el mensaje sea el mensaje.
            expect(in_array($caso->icono(), $delCliente, true))->toBeTrue(sprintf(
                'El icono `%s` de %s::%s no está en el mapa de IconoTipo.vue, así que ese badge se '
                .'pintaría sin icono y nadie se enteraría.',
                $caso->icono(),
                class_basename($enum),
                $caso->name,
            ));
        }
    }
});

/**
 * Al revés: un icono en el mapa que ya no usa nadie es peso muerto en el bundle,
 * que es el motivo por el que el mapa es explícito y no un `import *`.
 */
it('el mapa del cliente no arrastra iconos que ya no usa nadie', function (): void {
    $delServidor = [];

    foreach (enumsConIcono() as $enum) {
        foreach ($enum::cases() as $caso) {
            $delServidor[] = $caso->icono();
        }
    }

    foreach (iconosDelCliente() as $nombre) {
        expect(in_array($nombre, $delServidor, true))->toBeTrue(
            "El icono `{$nombre}` está en IconoTipo.vue y no lo emite ningún enum.",
        );
    }
});

/**
 * Dos estados con el mismo tono tienen que llevar iconos distintos: si no, el
 * badge no los separa ni por color ni por símbolo, y sólo queda la palabra.
 */
it('dos estados del mismo tono no comparten icono', function (string $enum): void {
    $porTono = [];

    foreach ($enum::cases() as $caso) {
        $porTono[$caso->tono()][] = $caso;
    }

    foreach ($porTono as $tono => $casos) {
        $iconos = array_map(static fn ($caso): string => $caso->icono(), $casos);

        expect($iconos)->toBe(
            array_values(array_unique($iconos)),
            sprintf(
                'En %s, los casos con tono `%s` comparten icono: se pintarían idénticos.',
                class_basename($enum),
                $tono,
            ),
        );
    }
})
    /*
     * Todos los que declaran las dos cosas, descubiertos igual que arriba. La
     * lista literal era aquí además engañosa: parecía una selección con criterio
     * y era sólo los que había el día que se escribió. `EstadoDocumental` es el
     * caso que la justificaba —«Rechazado» y «Obsoleto» comparten el gris del
     * dominio, y el icono es lo único que separa una decisión de un archivo—.
     */
    ->with(fn () => array_values(array_intersect(
        enumsDelDominioCon('icono'),
        enumsDelDominioCon('tono'),
    )));
