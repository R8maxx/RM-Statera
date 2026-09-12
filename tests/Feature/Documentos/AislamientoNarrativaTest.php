<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoSeccion;
use App\Domain\Documento\Models\PlantillaSeccion;
use App\Domain\Documento\Narrativa\GuardarNarrativa;
use App\Domain\Documento\Narrativa\GuardarPlantilla;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;

/*
|--------------------------------------------------------------------------
| Aislamiento de los textos narrativos
|--------------------------------------------------------------------------
|
| Estas dos tablas guardan prosa que el cliente ha escrito sobre su propio SGSI:
| cómo determina lo que le aplica, qué limitaciones reconoce y quién aprueba sus
| documentos. Filtrarla entre organizaciones es tan grave como filtrar la tabla
| de controles, y bastante más embarazoso.
|
*/

function narrativaDeDosOrganizaciones(): array
{
    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);

    $propia = Organizacion::factory()->create(['nombre' => 'Propia']);
    $ajena = Organizacion::factory()->create(['nombre' => 'Ajena']);

    comoOrganizacion($propia);
    $sistemaPropio = Sistema::factory()->de($propia)->conMarco($marco)->create();
    $documentoPropio = Documento::factory()->soa()->paraSistema($sistemaPropio->id)->create(['codigo' => 'SOA-PRO']);
    app(MaterializarSecciones::class)($documentoPropio);
    $usuario = usuarioCon(organizacion: $propia);

    comoOrganizacion($ajena);
    $sistemaAjeno = Sistema::factory()->de($ajena)->conMarco($marco)->create();
    $documentoAjeno = Documento::factory()->soa()->paraSistema($sistemaAjeno->id)->create(['codigo' => 'SOA-AJE']);
    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['introduccion' => 'Secreto de la ajena.']);
    app(MaterializarSecciones::class)($documentoAjeno);
    app(GuardarNarrativa::class)($documentoAjeno, ['conclusiones' => 'Conclusión confidencial.']);

    sinOrganizacion();

    return [
        'usuario' => $usuario,
        'propia' => $propia,
        'propio' => $documentoPropio,
        'ajeno' => $documentoAjeno,
    ];
}

it('no se ven las secciones de otra organización', function (): void {
    ['propia' => $propia] = narrativaDeDosOrganizaciones();

    comoOrganizacion($propia);

    expect(DocumentoSeccion::query()->where('contenido_md', 'Conclusión confidencial.')->exists())
        ->toBeFalse();

    expect(PlantillaSeccion::query()->where('contenido_md', 'Secreto de la ajena.')->exists())
        ->toBeFalse();
});

it('el resolutor no cruza la frontera', function (): void {
    ['propia' => $propia, 'propio' => $propio] = narrativaDeDosOrganizaciones();

    comoOrganizacion($propia);

    // La plantilla de la otra organización no debe llegar ni por el eslabón
    // intermedio de la cadena.
    expect(app(ResolverNarrativa::class)->paraDocumento($propio)['introduccion'])
        ->not->toBe('Secreto de la ajena.');
});

it('la pantalla de textos de un documento ajeno responde 404, no 403', function (): void {
    ['usuario' => $usuario, 'ajeno' => $ajeno] = narrativaDeDosOrganizaciones();

    // Decir «existe pero no es tuyo» ya sería filtrar.
    $this->actingAs($usuario)->get("/documentos/{$ajeno->id}/cuerpo")->assertNotFound();
    $this->actingAs($usuario)->put("/documentos/{$ajeno->id}/cuerpo", [])->assertNotFound();
});

it('sin contexto no se ve ninguna fila: RLS deniega por defecto', function (): void {
    narrativaDeDosOrganizaciones();

    sinOrganizacion();

    expect(DocumentoSeccion::query()->count())->toBe(0);
    expect(PlantillaSeccion::query()->count())->toBe(0);
});

it('cada organización edita su propia plantilla sin pisar la otra', function (): void {
    ['propia' => $propia] = narrativaDeDosOrganizaciones();

    comoOrganizacion($propia);
    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['introduccion' => 'La nuestra.']);

    expect(app(ResolverNarrativa::class)->paraPlantilla(TipoDocumento::SoaIso)['introduccion'])
        ->toBe('La nuestra.');

    expect(PlantillaSeccion::query()->count())->toBe(1);
});
