<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * El aviso de «el borrador es anterior a lo que has escrito».
 *
 * Sin él, alguien edita el documento, se descarga el borrador que ya había, no
 * ve su texto y da el módulo por roto. Es un aviso pequeño que evita una
 * conclusión grande.
 *
 * Y estuvo apagado sin que nadie lo notara: miraba `documento_secciones`, la
 * tabla de los once huecos narrativos, y desde que el documento entero es
 * editable **ahí no escribe nadie**. La comparación seguía ejecutándose, seguía
 * devolviendo `false` y el aviso no volvía a salir nunca. Por eso este test
 * existe: no basta con que la condición esté escrita, tiene que mirar la tabla
 * en la que se escribe.
 */
beforeEach(function (): void {
    Storage::fake('documentos');
    app()->instance(ClienteGotenberg::class, new GotenbergFalso);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();
    $this->usuario = usuarioCon();

    /* La cola va en `sync`: esto genera de verdad y deja el PDF en el disco falso. */
    app(GenerarDocumento::class)->encolar($this->documento, $this->usuario);

    $this->aviso = fn (): bool => $this->actingAs($this->usuario)
        ->get("/documentos/{$this->documento->id}")
        ->viewData('page')['props']['cuerpoMasNuevoQueElBorrador'];
});

it('no avisa mientras el borrador sea el del documento que hay', function (): void {
    expect(($this->aviso)())->toBeFalse();
});

/*
 * Se viaja un minuto porque la comparación es entre marcas de tiempo y las dos
 * caerían en el mismo segundo: el test pasaría o fallaría según lo rápida que
 * vaya la máquina, que es la peor clase de test.
 */
it('avisa en cuanto se edita el documento después de generar', function (): void {
    /* El cuerpo se estrena al abrir el editor, y guardar lo toca. */
    $this->actingAs($this->usuario)->get("/documentos/{$this->documento->id}/cuerpo")->assertOk();

    $this->travel(1)->minutes();
    $this->documento->cuerpo()->firstOrFail()->touch();

    expect(($this->aviso)())->toBeTrue();
});

it('y deja de avisar al regenerar', function (): void {
    $this->actingAs($this->usuario)->get("/documentos/{$this->documento->id}/cuerpo")->assertOk();

    $this->travel(1)->minutes();
    $this->documento->cuerpo()->firstOrFail()->touch();

    expect(($this->aviso)())->toBeTrue();

    $this->travel(1)->minutes();
    $this->actingAs($this->usuario)
        ->post("/documentos/{$this->documento->id}/generar")
        ->assertRedirect();

    expect(($this->aviso)())->toBeFalse();
});

it('no avisa si todavía no hay ningún borrador en disco', function (): void {
    $otro = Documento::factory()->soa()->paraSistema($this->documento->sistema_id)->create(['codigo' => 'SOA-SIN']);

    expect(
        $this->actingAs($this->usuario)
            ->get("/documentos/{$otro->id}")
            ->viewData('page')['props']['cuerpoMasNuevoQueElBorrador']
    )->toBeFalse();
});
