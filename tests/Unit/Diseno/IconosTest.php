<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Aviso\Fuente;
use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\Enums\OrigenTexto;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;

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

/** Los enums que declaran icono, que son los que se pintan como badge. */
function enumsConIcono(): array
{
    return [
        EstadoTarea::class,
        EstadoImplantacion::class,
        EstadoCicloVida::class,
        EstadoControl::class,
        Clasificacion::class,
        ClasificacionDocumental::class,
        EstadoGeneracion::class,
        OrigenTexto::class,
        PrioridadTarea::class,
        TipoActivo::class,
        Fuente::class,
    ];
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
})->with([EstadoTarea::class, EstadoImplantacion::class, EstadoCicloVida::class, EstadoControl::class, Clasificacion::class]);
