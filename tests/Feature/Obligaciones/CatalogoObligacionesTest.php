<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Excepciones\CatalogoInvalido;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\Obligacion;
use App\Domain\Obligacion\ObligacionesAplicables;
use App\Domain\Sistema\AplicarValoracion;
use App\Domain\Sistema\Models\Sistema;
use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| El catálogo de obligaciones y lo que se le propone a cada organización
|--------------------------------------------------------------------------
|
| Mismo contrato que requisitos y amenazas —idempotente, empareja por código, no
| borra— y una pieza propia: lo que se propone se **filtra**, y filtrar de menos
| es exigirle a alguien algo que no le toca.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

function importarObligaciones(array $obligaciones): void
{
    $fichero = tempnam(sys_get_temp_dir(), 'obl').'.yaml';
    file_put_contents($fichero, Yaml::dump(['obligaciones' => $obligaciones], 6));

    app(ImportadorCatalogo::class)->importar($fichero);

    unlink($fichero);
}

it('importar dos veces el mismo fichero no produce cambios', function (): void {
    $obligaciones = [[
        'codigo' => 'test.anual',
        'nombre' => 'Algo que toca cada año',
        'periodicidad_meses' => 12,
    ]];

    importarObligaciones($obligaciones);

    $fichero = tempnam(sys_get_temp_dir(), 'obl').'.yaml';
    file_put_contents($fichero, Yaml::dump(['obligaciones' => $obligaciones], 6));

    $resultado = app(ImportadorCatalogo::class)->importar($fichero, simulacion: true);

    unlink($fichero);

    expect($resultado->hayCambios())->toBeFalse()
        ->and($resultado->sinCambios)->toBe(1);
});

/**
 * No se borra: se marca. Puede haber compromisos colgando, y con ellos el
 * histórico de haberlos cumplido — que es justo lo que el auditor pide del
 * periodo anterior.
 */
it('una obligación que desaparece se marca y se informa de los compromisos afectados', function (): void {
    importarObligaciones([
        ['codigo' => 'test.uno', 'nombre' => 'Uno', 'periodicidad_meses' => 12],
        ['codigo' => 'test.dos', 'nombre' => 'Dos', 'periodicidad_meses' => 12],
    ]);

    $desaparecida = Obligacion::query()->where('codigo', 'test.dos')->firstOrFail();
    Compromiso::factory()->deObligacion($desaparecida->id)->create();

    $fichero = tempnam(sys_get_temp_dir(), 'obl').'.yaml';
    file_put_contents($fichero, Yaml::dump([
        'obligaciones' => [['codigo' => 'test.uno', 'nombre' => 'Uno', 'periodicidad_meses' => 12]],
    ], 6));

    $resultado = app(ImportadorCatalogo::class)->importar($fichero);

    unlink($fichero);

    expect($resultado->retirados)->toBe(['test.dos'])
        ->and($resultado->compromisosAfectados)->toBe(1)
        ->and($desaparecida->fresh()?->vigente)->toBeFalse()
        ->and($desaparecida->fresh()?->retirado_en)->not->toBeNull();
});

it('un marco que no existe es un error y no un aviso', function (): void {
    expect(fn () => importarObligaciones([[
        'codigo' => 'test.marco',
        'nombre' => 'De un marco inventado',
        'marco' => 'NIS2-INEXISTENTE',
        'periodicidad_meses' => 12,
    ]]))->toThrow(CatalogoInvalido::class);
});

it('una obligación del ENS no se propone sin un sistema de ese marco', function (): void {
    $marco = Marco::factory()->create(['codigo' => 'ENS-RD311-2022']);
    Obligacion::factory()->deMarco($marco->id)->create();

    $this->organizacion->update(['sujeto_obligado_ens' => true]);

    expect(app(ObligacionesAplicables::class)->para($this->organizacion))->toHaveCount(0);
});

it('tampoco se propone a quien no está sujeto al ENS', function (): void {
    $marco = Marco::factory()->create(['codigo' => 'ENS-RD311-2022']);
    Obligacion::factory()->deMarco($marco->id)->create();

    Sistema::factory()->create(['marco_id' => $marco->id]);

    $this->organizacion->update(['sujeto_obligado_ens' => false, 'proveedor_sector_publico' => false]);

    expect(app(ObligacionesAplicables::class)->para($this->organizacion->fresh()))->toHaveCount(0);
});

/**
 * Las pruebas de continuidad no muerden en básica: no se proponen hoy y entran
 * solas el día que un sistema alcance media, sin migración y sin tocar código.
 */
it('una obligación de categoría media no se propone a un sistema básico', function (): void {
    $marco = Marco::factory()->create(['codigo' => 'ENS-RD311-2022']);
    Obligacion::factory()->deMarco($marco->id)->desdeCategoria(CategoriaEns::Media)->create();

    $sistema = Sistema::factory()->create(['marco_id' => $marco->id]);
    $this->organizacion->update(['sujeto_obligado_ens' => true]);

    app(AplicarValoracion::class)->aplicar($sistema, uniforme(NivelDimension::Bajo), []);

    expect(app(ObligacionesAplicables::class)->para($this->organizacion->fresh()))->toHaveCount(0);

    app(AplicarValoracion::class)->aplicar($sistema->fresh(), uniforme(NivelDimension::Medio), []);

    expect(app(ObligacionesAplicables::class)->para($this->organizacion->fresh()))->toHaveCount(1);
});

it('lo ya asumido deja de proponerse', function (): void {
    $obligacion = Obligacion::factory()->create();

    expect(app(ObligacionesAplicables::class)->para($this->organizacion))->toHaveCount(1);

    Compromiso::factory()->deObligacion($obligacion->id)->create();

    expect(app(ObligacionesAplicables::class)->para($this->organizacion))->toHaveCount(0);
});
