<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Cuerpo\CuerpoDeFabrica;
use App\Domain\Documento\Cuerpo\Nodo;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoCuerpo;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;

/*
|--------------------------------------------------------------------------
| Aislamiento del cuerpo de los documentos
|--------------------------------------------------------------------------
|
| `documento_cuerpos` contiene el documento entero: los controles excluidos con
| sus motivos, las evidencias, las limitaciones que la organización reconoce por
| escrito y lo que su responsable haya redactado. De todas las tablas del
| producto es de las que peor sienta filtrar, así que se comprueban las tres
| capas: la columna, el scope de Eloquent y RLS.
|
*/

function cuerposDeDosOrganizaciones(): array
{
    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);

    $propia = Organizacion::factory()->create(['nombre' => 'Propia']);
    $ajena = Organizacion::factory()->create(['nombre' => 'Ajena']);

    comoOrganizacion($propia);
    $sistemaPropio = Sistema::factory()->de($propia)->conMarco($marco)->create();
    $documentoPropio = Documento::factory()->soa()->paraSistema($sistemaPropio->id)->create(['codigo' => 'SOA-PRO']);
    DocumentoCuerpo::factory()->delDocumento($documentoPropio->id)->create();
    $usuario = usuarioCon(organizacion: $propia);

    comoOrganizacion($ajena);
    $sistemaAjeno = Sistema::factory()->de($ajena)->conMarco($marco)->create();
    $documentoAjeno = Documento::factory()->soa()->paraSistema($sistemaAjeno->id)->create(['codigo' => 'SOA-AJE']);

    $secreto = CuerpoDeFabrica::para(TipoDocumento::SoaIso, [
        'conclusiones' => [Nodo::parrafo('El proveedor de copias incumple su ANS desde marzo.')],
    ]);

    DocumentoCuerpo::factory()->delDocumento($documentoAjeno->id)->create([
        'cuerpo' => $secreto,
        'generado' => $secreto,
    ]);

    sinOrganizacion();

    return [
        'usuario' => $usuario,
        'propia' => $propia,
        'propio' => $documentoPropio,
        'ajeno' => $documentoAjeno,
    ];
}

it('no se ve el cuerpo de otra organización', function (): void {
    ['propia' => $propia, 'ajeno' => $ajeno] = cuerposDeDosOrganizaciones();

    comoOrganizacion($propia);

    expect(DocumentoCuerpo::query()->where('documento_id', $ajeno->id)->exists())->toBeFalse()
        ->and(DocumentoCuerpo::query()->count())->toBe(1)
        // Y tampoco por dentro del jsonb, que es donde vive lo que se ha escrito.
        ->and(DocumentoCuerpo::query()->whereRaw("cuerpo::text like '%incumple su ANS%'")->exists())
        ->toBeFalse();
});

it('el editor de un documento ajeno responde 404, no 403', function (): void {
    ['usuario' => $usuario, 'ajeno' => $ajeno] = cuerposDeDosOrganizaciones();

    // Decir «existe pero no es tuyo» ya sería filtrar.
    $this->actingAs($usuario)->get("/documentos/{$ajeno->id}/cuerpo")->assertNotFound();
    $this->actingAs($usuario)->put("/documentos/{$ajeno->id}/cuerpo", [
        'cuerpo' => ['type' => 'doc', 'content' => [['type' => 'seccion']]],
    ])->assertNotFound();
});

it('guardar en un documento ajeno no toca su cuerpo', function (): void {
    ['usuario' => $usuario, 'ajeno' => $ajeno] = cuerposDeDosOrganizaciones();

    $this->actingAs($usuario)->put("/documentos/{$ajeno->id}/cuerpo", [
        'cuerpo' => ['type' => 'doc', 'content' => [['type' => 'seccion', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Pisado.']]],
        ]]]],
    ])->assertNotFound();

    sinOrganizacion();
    comoOrganizacion($ajeno->organizacion_id);

    expect(DocumentoCuerpo::query()->whereRaw("cuerpo::text like '%Pisado.%'")->exists())->toBeFalse();
});

it('sin contexto no se ve ninguna fila: RLS deniega por defecto', function (): void {
    cuerposDeDosOrganizaciones();

    sinOrganizacion();

    expect(DocumentoCuerpo::query()->count())->toBe(0);
});
