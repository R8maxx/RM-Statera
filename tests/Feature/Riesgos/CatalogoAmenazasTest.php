<?php

declare(strict_types=1);

use App\Domain\Catalogo\Excepciones\CatalogoInvalido;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Riesgo\Enums\GrupoAmenaza;
use App\Domain\Riesgo\Models\Amenaza;
use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| El catálogo de amenazas
|--------------------------------------------------------------------------
|
| Prioridad 4 de la cobertura: el importador, incluida la idempotencia y el diff.
|
| Lo que más importa aquí es lo que NO hace: no borrar. Una amenaza que desaparece
| de una revisión de MAGERIT puede tener riesgos colgando, y borrarla dejaría un
| análisis entero sin poder explicar de qué se estaba protegiendo.
|
*/

function escribirCatalogoAmenazas(array $documento): string
{
    $ruta = sys_get_temp_dir().'/amenazas-'.uniqid().'.yaml';
    file_put_contents($ruta, Yaml::dump($documento, 6));

    return $ruta;
}

/** @param list<array<string, mixed>> $amenazas */
function ficheroDeAmenazas(array $amenazas): string
{
    return escribirCatalogoAmenazas(['fuente' => 'Prueba', 'revisado' => false, 'amenazas' => $amenazas]);
}

function amenazaYaml(string $codigo, string $nombre = 'Una amenaza', array $extra = []): array
{
    return [
        'codigo' => $codigo,
        'grupo' => GrupoAmenaza::ErroresNoIntencionados->value,
        'nombre' => $nombre,
        'dimensiones' => ['D'],
        'tipos_activo' => ['hardware'],
        ...$extra,
    ];
}

it('carga el catálogo de MAGERIT que vive en el repositorio', function (): void {
    $importador = app(ImportadorCatalogo::class);

    $resultado = $importador->importar(base_path('catalogo/magerit-amenazas.yaml'));

    expect($resultado->tipo)->toBe('amenazas')
        ->and($resultado->nuevos)->not->toBeEmpty();

    // Los cuatro grupos del Libro II, enteros.
    foreach (GrupoAmenaza::cases() as $grupo) {
        expect(Amenaza::query()->where('grupo', $grupo->value)->exists())->toBeTrue(
            "El catálogo no trae ninguna amenaza del grupo `{$grupo->value}`.",
        );
    }

    // Códigos reconocibles por quien conozca MAGERIT.
    expect(Amenaza::query()->where('codigo', 'E.1')->exists())->toBeTrue()
        ->and(Amenaza::query()->where('codigo', 'A.25')->exists())->toBeTrue();
});

it('importar dos veces el mismo fichero no produce cambios', function (): void {
    $importador = app(ImportadorCatalogo::class);
    $fichero = base_path('catalogo/magerit-amenazas.yaml');

    $primera = $importador->importar($fichero);
    $segunda = $importador->importar($fichero);

    expect($primera->hayCambios())->toBeTrue()
        ->and($segunda->hayCambios())->toBeFalse()
        ->and($segunda->nuevos)->toBeEmpty()
        ->and($segunda->modificados)->toBeEmpty()
        ->and($segunda->sinCambios)->toBe(count($primera->nuevos));
});

it('reordenar las dimensiones en el YAML no cuenta como modificación', function (): void {
    // Sin normalizar la lista antes de la huella, mover `[C, I]` a `[I, C]` saldría
    // en el diff como un cambio del catálogo, que no lo es.
    $importador = app(ImportadorCatalogo::class);

    $importador->importar(ficheroDeAmenazas([
        amenazaYaml('E.1', 'Errores de los usuarios', ['dimensiones' => ['C', 'I', 'D']]),
    ]));

    $segunda = $importador->importar(ficheroDeAmenazas([
        amenazaYaml('E.1', 'Errores de los usuarios', ['dimensiones' => ['D', 'C', 'I']]),
    ]));

    expect($segunda->modificados)->toBeEmpty()
        ->and($segunda->sinCambios)->toBe(1);
});

it('detecta lo nuevo y lo modificado, y dice qué cambió', function (): void {
    $importador = app(ImportadorCatalogo::class);

    $importador->importar(ficheroDeAmenazas([amenazaYaml('E.1', 'Errores de los usuarios')]));

    $segunda = $importador->importar(ficheroDeAmenazas([
        amenazaYaml('E.1', 'Errores de las personas usuarias'),
        amenazaYaml('E.2', 'Errores del administrador'),
    ]));

    expect($segunda->nuevos)->toBe(['E.2'])
        ->and($segunda->modificados)->toHaveCount(1)
        ->and($segunda->modificados[0]['codigo'])->toBe('E.1')
        ->and($segunda->modificados[0]['cambios'])->toContain('nombre');
});

it('una amenaza que desaparece se marca, no se borra', function (): void {
    $importador = app(ImportadorCatalogo::class);

    $importador->importar(ficheroDeAmenazas([
        amenazaYaml('E.1'),
        amenazaYaml('E.2'),
    ]));

    $segunda = $importador->importar(ficheroDeAmenazas([amenazaYaml('E.1')]));

    expect($segunda->retirados)->toBe(['E.2']);

    $retirada = Amenaza::query()->where('codigo', 'E.2')->first();

    // Sigue ahí: puede haber riesgos colgando de ella.
    expect($retirada)->not->toBeNull()
        ->and($retirada->vigente)->toBeFalse()
        ->and($retirada->retirado_en)->not->toBeNull()
        ->and(Amenaza::query()->vigentes()->pluck('codigo')->all())->toBe(['E.1']);
});

it('una amenaza que vuelve se reactiva', function (): void {
    $importador = app(ImportadorCatalogo::class);

    $importador->importar(ficheroDeAmenazas([amenazaYaml('E.1'), amenazaYaml('E.2')]));
    $importador->importar(ficheroDeAmenazas([amenazaYaml('E.1')]));
    $tercera = $importador->importar(ficheroDeAmenazas([amenazaYaml('E.1'), amenazaYaml('E.2')]));

    expect($tercera->reactivados)->toBe(['E.2'])
        ->and(Amenaza::query()->where('codigo', 'E.2')->first()->vigente)->toBeTrue();
});

it('la simulación no escribe nada', function (): void {
    $importador = app(ImportadorCatalogo::class);

    $resultado = $importador->importar(ficheroDeAmenazas([amenazaYaml('E.1')]), simulacion: true);

    expect($resultado->nuevos)->toBe(['E.1'])
        ->and($resultado->simulacion)->toBeTrue()
        ->and(Amenaza::query()->count())->toBe(0);
});

/**
 * El que justifica haber cambiado el ternario por un `match`: antes, una raíz mal
 * escrita caía en `importarMarco()` y reventaba con «marco: el campo codigo es
 * obligatorio», que manda a buscar al sitio equivocado.
 */
it('un fichero con la clave raíz mal escrita falla diciendo la causa', function (): void {
    $fichero = escribirCatalogoAmenazas(['amenazax' => [amenazaYaml('E.1')]]);

    expect(fn () => app(ImportadorCatalogo::class)->importar($fichero))
        ->toThrow(function (CatalogoInvalido $e): void {
            expect(implode(' ', $e->errores))
                ->toContain('marco')
                ->toContain('mapeos')
                ->toContain('amenazas');
        });
});

it('rechaza un grupo, una dimensión o un tipo de activo que no existen', function (array $roto, string $esperado): void {
    $fichero = ficheroDeAmenazas([amenazaYaml('E.1', 'Una amenaza', $roto)]);

    expect(fn () => app(ImportadorCatalogo::class)->importar($fichero))
        ->toThrow(CatalogoInvalido::class);

    expect(Amenaza::query()->count())->toBe(0, "Se escribió pese a [{$esperado}].");
})->with([
    'grupo inventado' => [['grupo' => 'meteoritos'], 'grupo'],
    'dimensión que no es del ENS' => [['dimensiones' => ['Z']], 'dimension'],
    'tipo de activo que no es de MAGERIT' => [['tipos_activo' => ['nubes']], 'tipo'],
]);

it('rechaza dos amenazas con el mismo código', function (): void {
    $fichero = ficheroDeAmenazas([amenazaYaml('E.1', 'Una'), amenazaYaml('E.1', 'Otra')]);

    expect(fn () => app(ImportadorCatalogo::class)->importar($fichero))
        ->toThrow(CatalogoInvalido::class, 'duplicado');
});

it('el orden de importación pone las amenazas antes que los marcos y los mapeos', function (): void {
    // No es alfabético aunque lo parezca con los nombres de hoy: las amenazas no
    // referencian nada y los mapeos necesitan los dos extremos.
    $orden = array_map(
        static fn (string $ruta): string => basename($ruta),
        app(ImportadorCatalogo::class)->ficherosDe(base_path('catalogo')),
    );

    $amenazas = array_search('magerit-amenazas.yaml', $orden, true);
    $mapeos = array_search('mapeos-iso-ens.yaml', $orden, true);

    expect($amenazas)->toBe(0)
        ->and($mapeos)->toBe(count($orden) - 1);
});

/**
 * El catálogo es global (invariante 2): sin `organizacion_id`, sin RLS y visible
 * desde cualquier tenant. Es lo contrario de lo que se comprueba para los riesgos.
 */
it('las amenazas se ven desde cualquier organización', function (): void {
    app(ImportadorCatalogo::class)->importar(ficheroDeAmenazas([amenazaYaml('E.1')]));

    $una = Organizacion::factory()->create();
    $otra = Organizacion::factory()->create();

    comoOrganizacion($una);
    expect(Amenaza::query()->count())->toBe(1);

    comoOrganizacion($otra);
    expect(Amenaza::query()->count())->toBe(1);

    // Y también sin contexto: no hay política que deniegue por defecto.
    sinOrganizacion();
    expect(Amenaza::query()->count())->toBe(1);
});
