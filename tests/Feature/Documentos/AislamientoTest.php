<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\DocumentoRecurso;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Aislamiento de los documentos
|--------------------------------------------------------------------------
|
| Prioridad 2 de la cobertura. Aquí pesa más que en otros módulos: una versión
| contiene la Declaración de Aplicabilidad ENTERA de una organización —su
| alcance, sus exclusiones y sus huecos—, así que filtrarla es peor que filtrar
| la fila que la nombra.
|
| Y responde 404, no 403: decir «existe pero no es tuyo» ya sería filtrar.
|
*/

/**
 * @return array{usuario: User, propio: Documento, ajeno: Documento, versionAjena: DocumentoVersion}
 */
function dosOrganizacionesConDocumentos(): array
{
    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);

    $propia = Organizacion::factory()->create(['nombre' => 'Propia']);
    $ajena = Organizacion::factory()->create(['nombre' => 'Ajena']);

    comoOrganizacion($propia);
    $sistemaPropio = Sistema::factory()->de($propia)->conMarco($marco)->create();
    $propio = Documento::factory()->soa()->paraSistema($sistemaPropio->id)->create(['codigo' => 'SOA-PRO']);
    $usuario = usuarioCon(organizacion: $propia);

    comoOrganizacion($ajena);
    $sistemaAjeno = Sistema::factory()->de($ajena)->conMarco($marco)->create();
    $ajeno = Documento::factory()->soa()->paraSistema($sistemaAjeno->id)->create(['codigo' => 'SOA-AJE']);
    $versionAjena = DocumentoVersion::factory()->delDocumento($ajeno->id)->emitida()->create();

    sinOrganizacion();

    return ['usuario' => $usuario, 'propio' => $propio, 'ajeno' => $ajeno, 'versionAjena' => $versionAjena];
}

it('el índice sólo lista los documentos de la organización del usuario', function (): void {
    ['usuario' => $usuario] = dosOrganizacionesConDocumentos();

    $this->actingAs($usuario)->get('/documentos')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $codigos = array_column($pagina->toArray()['props']['filas'], 'codigo');

            expect($codigos)->toContain('SOA-PRO')->not->toContain('SOA-AJE');
        });
});

it('la ficha de un documento ajeno responde 404, no 403', function (): void {
    ['usuario' => $usuario, 'ajeno' => $ajeno] = dosOrganizacionesConDocumentos();

    $this->actingAs($usuario)->get("/documentos/{$ajeno->id}")->assertNotFound();
});

it('no se descarga la versión de un documento ajeno', function (): void {
    ['usuario' => $usuario, 'ajeno' => $ajeno, 'versionAjena' => $version] = dosOrganizacionesConDocumentos();

    $this->actingAs($usuario)
        ->get("/documentos/{$ajeno->id}/versiones/{$version->id}/descargar")
        ->assertNotFound();
});

it('no se descarga una versión que pertenece a OTRO documento de la misma organización', function (): void {
    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $organizacion = comoOrganizacion();
    $sistema = Sistema::factory()->de($organizacion)->conMarco($marco)->create();

    $uno = Documento::factory()->soa()->paraSistema($sistema->id)->create(['codigo' => 'SOA-01']);
    $otro = Documento::factory()->soa()->paraSistema($sistema->id)->create(['codigo' => 'SOA-02']);
    $version = DocumentoVersion::factory()->delDocumento($otro->id)->emitida()->create();

    // Es lo que evita `scopeBindings()`: sin él, la versión se resolvería
    // globalmente y se descargaría desde una URL que no le corresponde.
    $this->actingAs(usuarioCon())
        ->get("/documentos/{$uno->id}/versiones/{$version->id}/descargar")
        ->assertNotFound();
});

it('ni el generar ni el emitir alcanzan a un documento ajeno', function (): void {
    ['usuario' => $usuario, 'ajeno' => $ajeno] = dosOrganizacionesConDocumentos();

    $this->actingAs($usuario)->post("/documentos/{$ajeno->id}/generar")->assertNotFound();
    $this->actingAs($usuario)->post("/documentos/{$ajeno->id}/emitir")->assertNotFound();
});

it('la consulta del recurso no ve versiones de otra organización', function (): void {
    ['propio' => $propio] = dosOrganizacionesConDocumentos();

    comoOrganizacion($propio->organizacion_id);

    // Las subconsultas del recurso van en SQL crudo y no pasan por el scope de
    // Eloquent: aquí lo que filtra es RLS, la tercera capa.
    $fila = (new DocumentoRecurso)->consulta()->firstOrFail();

    expect($fila->codigo)->toBe('SOA-PRO')
        ->and($fila->getAttribute('ultima_version'))->toBeNull();

    expect(DocumentoVersion::query()->count())->toBe(0);
});
