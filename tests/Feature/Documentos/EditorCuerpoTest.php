<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoCuerpo;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;

/**
 * El editor del cuerpo, por la puerta por la que entra de verdad.
 *
 * Lo que se prueba aquí no se ve llamando al dominio: son las cosas que le pasan
 * al documento **por el camino**, entre el editor y la fila.
 *
 * Se puede redactar antes de generar nada.
 *
 * Una versión nace en `GenerarDocumento::encolar()`, así que un documento recién
 * creado no tiene ninguna. El botón «Editar documento» de la ficha no espera a
 * que la haya —ni debe: redactar es lo primero que se hace— y hasta aquí eso era
 * un 404 en el camino más corto que existe entre crear un documento y escribir
 * en él.
 *
 * La fila de `documento_versiones` NO se crea al abrir el editor: esa tabla es el
 * registro de lo que se ha entregado.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();
});

it('abre el editor de un documento que todavía no tiene ninguna versión', function (): void {
    expect($this->documento->versiones()->count())->toBe(0);

    $this->actingAs(usuarioCon())
        ->get("/documentos/{$this->documento->id}/cuerpo")
        ->assertOk();
});

it('no inventa una entrega por el hecho de abrir el editor', function (): void {
    $this->actingAs(usuarioCon())->get("/documentos/{$this->documento->id}/cuerpo")->assertOk();

    expect($this->documento->versiones()->count())->toBe(0);
});

it('guarda lo redactado antes de que exista ninguna versión', function (): void {
    $usuario = usuarioCon();

    $this->actingAs($usuario)->get("/documentos/{$this->documento->id}/cuerpo")->assertOk();

    $fila = DocumentoCuerpo::query()->where('documento_id', $this->documento->id)->firstOrFail();

    $this->actingAs($usuario)
        ->putJson("/documentos/{$this->documento->id}/cuerpo", ['cuerpo' => $fila->cuerpo])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->documento->versiones()->count())->toBe(0);
});

it('en cuanto hay un borrador, es el borrador el que manda', function (): void {
    $borrador = DocumentoVersion::factory()->delDocumento($this->documento->id)->create();

    $this->actingAs(usuarioCon())
        ->get("/documentos/{$this->documento->id}/cuerpo")
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('borrador.id', $borrador->id));
});

/**
 * Abrir el editor no es editar.
 *
 * `editado_en` es lo que hace que la portada diga que el documento se mantiene a
 * mano y que las limitaciones lo declaren, y lo sella **guardar**. Mirar no
 * guarda: el editor emite el árbol ya normalizado por Tiptap al cargar, y
 * tomarlo por una edición hacía que «Ver el PDF» guardara solo y el documento
 * acabara declarando algo que no había pasado.
 */
it('abrir el editor no sella editado_en', function (): void {
    $this->actingAs(usuarioCon())->get("/documentos/{$this->documento->id}/cuerpo")->assertOk();

    $fila = DocumentoCuerpo::query()->where('documento_id', $this->documento->id)->firstOrFail();

    expect($fila->editado_en)->toBeNull();
});

/**
 * El texto del documento llega tal cual se escribió.
 *
 * El cuerpo viaja como un árbol donde cada trozo de texto es una cadena suelta, y
 * `TrimStrings` —global, en toda petición— recortaba cada una. Un espacio al
 * principio de un nodo no es relleno: es lo que separa ese trozo del anterior,
 * casi siempre del que va en negrita. Recortarlo imprimía «no es una
 * entrega.Este PDF» en el documento que se le entrega al auditor, y de paso
 * convertía cualquier guardado en un cambio.
 */
it('no se come los espacios que separan un trozo de texto del anterior', function (): void {
    $usuario = usuarioCon();

    $this->actingAs($usuario)->get("/documentos/{$this->documento->id}/cuerpo")->assertOk();

    $fila = DocumentoCuerpo::query()->where('documento_id', $this->documento->id)->firstOrFail();

    $cuerpo = $fila->cuerpo;
    $cuerpo['content'][] = [
        'type' => 'paragraph',
        'content' => [
            ['type' => 'text', 'text' => 'Aviso.', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' Lo que viene detrás.'],
        ],
    ];

    $this->actingAs($usuario)
        ->putJson("/documentos/{$this->documento->id}/cuerpo", ['cuerpo' => $cuerpo])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $guardado = (string) json_encode($fila->fresh()->cuerpo);

    expect($guardado)->toContain(' Lo que viene detr');
});
