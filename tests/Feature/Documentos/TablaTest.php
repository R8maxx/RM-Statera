<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Sistema\Models\Sistema;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La tabla de documentos
|--------------------------------------------------------------------------
|
| Lo que aquí se prueba y no en los otros recursos: que las cifras de versión
| lleguen por SUBCONSULTA y no por join. Con un join, un documento con cuatro
| entregas saldría cuatro veces y la paginación contaría mal — el mismo problema
| que llevó a `Filtro::porRelacion()` en activos.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create(['codigo' => 'SIS-01']);
});

/** @return array<string, mixed> */
function filasDeDocumentos(array $parametros = []): array
{
    $respuesta = test()->actingAs(test()->usuario)->get('/documentos?'.http_build_query($parametros));

    return $respuesta->assertOk()->viewData('page')['props'];
}

it('no duplica un documento por tener varias versiones emitidas', function (): void {
    $documento = Documento::factory()->soa()->paraSistema($this->sistema->id)->create(['codigo' => 'SOA-01']);

    foreach ([1, 2, 3] as $numero) {
        DocumentoVersion::factory()->delDocumento($documento->id)->emitida($numero)->create();
    }

    $props = filasDeDocumentos();

    expect($props['filas'])->toHaveCount(1)
        ->and($props['meta']->total)->toBe(1);

    // Y enseña la última, no una cualquiera.
    expect($props['filas'][0]['version']->etiqueta)->toBe('v3');
});

it('distingue no haber entregado nunca de tener un borrador a medias', function (): void {
    $sinNada = Documento::factory()->soa()->paraSistema($this->sistema->id)->create(['codigo' => 'SOA-01']);
    $conBorrador = Documento::factory()->soa()->paraSistema($this->sistema->id)->create(['codigo' => 'SOA-02']);

    DocumentoVersion::factory()->delDocumento($conBorrador->id)->generada()->create();

    $filas = collect(filasDeDocumentos()['filas'])->keyBy('codigo');

    expect($filas['SOA-01']['version']->etiqueta)->toBe('Sin emitir')
        ->and($filas['SOA-01']['generacion']->etiqueta)->toBe('Al día');

    expect($filas['SOA-02']['version']->etiqueta)->toBe('Sin emitir')
        ->and($filas['SOA-02']['generacion']->etiqueta)->toBe('Borrador listo');

    expect($sinNada->codigo)->toBe('SOA-01');
});

it('ordena por la última emisión, que es una columna calculada', function (): void {
    $viejo = Documento::factory()->soa()->paraSistema($this->sistema->id)->create(['codigo' => 'SOA-VIEJO']);
    $nuevo = Documento::factory()->soa()->paraSistema($this->sistema->id)->create(['codigo' => 'SOA-NUEVO']);

    DocumentoVersion::factory()->delDocumento($viejo->id)->emitida()->create([
        'emitida_en' => now()->subYear(),
    ]);
    DocumentoVersion::factory()->delDocumento($nuevo->id)->emitida()->create([
        'emitida_en' => now(),
    ]);

    $props = filasDeDocumentos(['sort' => '-ultima_emision']);

    expect(array_column($props['filas'], 'codigo'))->toBe(['SOA-NUEVO', 'SOA-VIEJO']);
    // Lo que vuelve en `meta` es el orden que se aplicó de verdad, no el pedido.
    expect($props['meta']->orden)->toBe('-ultima_emision');
});

it('filtra por tipo y devuelve en meta el filtro aplicado', function (): void {
    Documento::factory()->soa()->paraSistema($this->sistema->id)->create(['codigo' => 'SOA-01']);

    $ens = Sistema::factory()->de($this->organizacion)
        ->conMarco(Marco::factory()->create(['codigo' => 'ENS-SINTETICO']))
        ->create(['codigo' => 'SIS-ENS']);
    Documento::factory()->dda()->paraSistema($ens->id)->create(['codigo' => 'DDA-01']);

    $props = filasDeDocumentos(['filter' => ['tipo' => 'dda_ens']]);

    expect(array_column($props['filas'], 'codigo'))->toBe(['DDA-01']);
    expect($props['meta']->filtros)->toHaveKey('tipo');
});

it('ignora un filtro que no existe en vez de romper la petición', function (): void {
    Documento::factory()->soa()->paraSistema($this->sistema->id)->create(['codigo' => 'SOA-01']);

    // Una URL guardada no puede convertirse en un error porque alguien renombre
    // un filtro.
    $props = filasDeDocumentos(['filter' => ['inventado' => 'lo que sea']]);

    expect($props['filas'])->toHaveCount(1);
    expect($props['meta']->filtros)->not->toHaveKey('inventado');
});

it('la ficha llega con el borrador y el historial en props separados', function (): void {
    $documento = Documento::factory()->soa()->paraSistema($this->sistema->id)->create(['codigo' => 'SOA-01']);
    DocumentoVersion::factory()->delDocumento($documento->id)->emitida()->create();
    DocumentoVersion::factory()->delDocumento($documento->id)->generada()->create();

    $this->actingAs($this->usuario)->get("/documentos/{$documento->id}")
        ->assertInertia(fn (AssertableInertia $p) => $p
            ->has('documento')
            // Van sueltos porque son los que recarga el poll: una recarga
            // parcial pide claves de primer nivel.
            ->has('versionEnCurso')
            ->has('versiones', 1)
        );
});
