<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\IniciarDeclaracion;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La conformidad por la interfaz
|--------------------------------------------------------------------------
|
| Lo que se fija aquí es lo que no se ve leyendo el dominio: que un bloqueo
| vuelve como mensaje y no como 500, que el Auditor lee y no escribe, y que el
| sistema o la versión de otra organización no existen.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->sistema = sistemaEns($this->organizacion);
});

it('lista los sistemas bajo el ENS con su estado', function (): void {
    autoevaluacion($this->sistema);
    app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    $this->actingAs($this->usuario)
        ->get('/conformidad')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('conformidad/Index')
            ->has('sistemas', 1)
            ->where('sistemas.0.id', $this->sistema->id)
            ->where('sistemas.0.categoria', 'Básica')
            ->where('sistemas.0.enPreparacion.estado', EstadoConformidad::EnPreparacion->value)
            ->where('sistemas.0.vigente', null));
});

it('la ficha enseña por qué todavía no se puede declarar', function (): void {
    $this->actingAs($this->usuario)
        ->get("/conformidad/sistemas/{$this->sistema->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('conformidad/Ficha')
            ->where('comprobacion.sePuedeIniciar', false)
            ->where('comprobacion.bloqueos', fn ($bloqueos): bool => str_contains((string) collect($bloqueos)->first(), 'autoevaluación cerrada')));
});

it('iniciar bloqueado vuelve con el motivo al campo y no con un 500', function (): void {
    $this->actingAs($this->usuario)
        ->from("/conformidad/sistemas/{$this->sistema->id}")
        ->post("/conformidad/sistemas/{$this->sistema->id}")
        ->assertRedirect("/conformidad/sistemas/{$this->sistema->id}")
        ->assertSessionHasErrors('conformidad');

    expect(Conformidad::query()->count())->toBe(0);
});

it('inicia la declaración desde la ficha', function (): void {
    autoevaluacion($this->sistema);

    $this->actingAs($this->usuario)
        ->post("/conformidad/sistemas/{$this->sistema->id}")
        ->assertRedirect("/conformidad/sistemas/{$this->sistema->id}")
        ->assertSessionHasNoErrors();

    expect(Conformidad::query()->sole()->estado)->toBe(EstadoConformidad::EnPreparacion);
});

it('prepara la serie de la Declaración una sola vez, pública y del sistema', function (): void {
    autoevaluacion($this->sistema);
    app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    $this->actingAs($this->usuario)->post("/conformidad/sistemas/{$this->sistema->id}/documento")->assertRedirect();
    $this->actingAs($this->usuario)->post("/conformidad/sistemas/{$this->sistema->id}/documento")->assertRedirect();

    $documento = Documento::query()->sole();

    expect($documento->tipo)->toBe(TipoDocumento::DeclaracionConformidadEns)
        ->and($documento->sistema_id)->toBe($this->sistema->id)
        ->and($documento->clasificacion)->toBe(ClasificacionDocumental::Publico);
});

it('el Auditor ve la conformidad y no puede tocarla', function (): void {
    autoevaluacion($this->sistema);
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)->get('/conformidad')->assertOk();
    $this->actingAs($auditor)->get("/conformidad/sistemas/{$this->sistema->id}")->assertOk();
    $this->actingAs($auditor)->post("/conformidad/sistemas/{$this->sistema->id}")->assertForbidden();

    expect(Conformidad::query()->count())->toBe(0);
});

it('el sistema y la versión de otra organización no existen', function (): void {
    autoevaluacion($this->sistema);
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    $otra = comoOrganizacion();
    $ajeno = sistemaEns($otra);
    $documentoAjeno = Documento::factory()->declaracionConformidad()->paraSistema($ajeno->id)->create();
    $versionAjena = DocumentoVersion::factory()->delDocumento($documentoAjeno->id)->emitida()->create();

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)->get("/conformidad/sistemas/{$ajeno->id}")->assertNotFound();
    $this->actingAs($this->usuario)->post("/conformidad/sistemas/{$ajeno->id}")->assertNotFound();

    $this->actingAs($this->usuario)
        ->post("/conformidad/{$conformidad->id}/declarar", ['documento_version_id' => $versionAjena->id])
        ->assertNotFound();

    expect($conformidad->fresh()->estado)->toBe(EstadoConformidad::EnPreparacion);
});

it('retirar sin motivo vuelve al campo', function (): void {
    autoevaluacion($this->sistema);
    $conformidad = app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    $this->actingAs($this->usuario)
        ->post("/conformidad/{$conformidad->id}/retirar", ['motivo' => ''])
        ->assertSessionHasErrors('motivo');

    expect($conformidad->fresh()->estado)->toBe(EstadoConformidad::EnPreparacion);
});
