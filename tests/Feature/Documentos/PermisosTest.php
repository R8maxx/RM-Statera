<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Dobles\GotenbergFalso;

/**
 * Quién puede leer los documentos y quién puede entregarlos.
 *
 * La frontera importante no es leer/escribir: es **emitir**. Emitir una versión
 * es entregarla, y quien la firma es el responsable de seguridad. Un auditor,
 * en cambio, tiene que poder leerlas todas: si no pudiera abrir la Declaración
 * de Aplicabilidad no podría auditar nada.
 */
beforeEach(function (): void {
    // La cola va en `sync`, así que generar desde una ruta genera de verdad.
    Storage::fake('documentos');
    app()->instance(ClienteGotenberg::class, new GotenbergFalso);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();
});

it('el auditor y el técnico ven el índice y la ficha', function (Rol $rol): void {
    $usuario = usuarioCon($rol);

    $this->actingAs($usuario)->get('/documentos')->assertOk();
    $this->actingAs($usuario)->get("/documentos/{$this->documento->id}")->assertOk();
})->with([Rol::Auditor, Rol::Tecnico]);

it('ni el auditor ni el técnico generan o emiten', function (Rol $rol): void {
    $usuario = usuarioCon($rol);

    $this->actingAs($usuario)->post("/documentos/{$this->documento->id}/generar")->assertForbidden();
    $this->actingAs($usuario)->post("/documentos/{$this->documento->id}/emitir")->assertForbidden();
    $this->actingAs($usuario)->get('/documentos/crear')->assertForbidden();
})->with([Rol::Auditor, Rol::Tecnico]);

it('el auditor puede descargar lo entregado, que es de lo que va auditar', function (): void {
    DocumentoVersion::factory()->delDocumento($this->documento->id)->emitida()->create();

    // Llega a la acción —no hay 403—; el 404 es porque el fichero no existe en
    // el disco falso de este test, que aquí da igual.
    $this->actingAs(usuarioCon(Rol::Auditor))
        ->get("/documentos/{$this->documento->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->has('versiones', 1));
});

it('el responsable de seguridad sí genera y emite', function (): void {
    $usuario = usuarioCon(Rol::ResponsableSeguridad);

    $this->actingAs($usuario)->get('/documentos/crear')->assertOk();

    // Generar vuelve a donde se pidió: la ficha si se pidió desde la ficha, y el
    // editor si se pidió con «Ver el PDF».
    $this->actingAs($usuario)
        ->from("/documentos/{$this->documento->id}")
        ->post("/documentos/{$this->documento->id}/generar")
        ->assertRedirect("/documentos/{$this->documento->id}");

    $this->actingAs($usuario)
        ->from("/documentos/{$this->documento->id}/cuerpo")
        ->post("/documentos/{$this->documento->id}/generar")
        ->assertRedirect("/documentos/{$this->documento->id}/cuerpo");
});

it('el técnico redacta el documento pero no toca la plantilla', function (): void {
    $usuario = usuarioCon(Rol::Tecnico);

    // El editor del cuerpo necesita una versión de la que construir el
    // contenido: sin ella no hay de dónde sacar las cifras del documento.
    DocumentoVersion::factory()->delDocumento($this->documento->id)->create();

    // Redactar es trabajo de quien prepara el documento; la plantilla decide
    // cómo empiezan TODOS los futuros, y eso es otra decisión.
    $this->actingAs($usuario)->get("/documentos/{$this->documento->id}/cuerpo")->assertOk();
    $this->actingAs($usuario)->get('/plantillas-documento')->assertForbidden();
    $this->actingAs($usuario)->get('/plantillas-documento/soa_iso')->assertForbidden();
});

it('el auditor no redacta nada', function (): void {
    $usuario = usuarioCon(Rol::Auditor);

    $this->actingAs($usuario)->get("/documentos/{$this->documento->id}/cuerpo")->assertForbidden();
    $this->actingAs($usuario)->get('/plantillas-documento')->assertForbidden();
});

it('el responsable de seguridad sí toca la plantilla', function (): void {
    $usuario = usuarioCon(Rol::ResponsableSeguridad);

    $this->actingAs($usuario)->get('/plantillas-documento')->assertOk();
    $this->actingAs($usuario)->get('/plantillas-documento/soa_iso')->assertOk();
});

it('un tipo de documento inventado en la plantilla responde 404', function (): void {
    $this->actingAs(usuarioCon(Rol::ResponsableSeguridad))
        ->get('/plantillas-documento/no_existe')
        ->assertNotFound();
});

it('no ofrece «Nuevo documento» a quien no puede crearlo', function (): void {
    $this->actingAs(usuarioCon(Rol::Auditor))->get('/documentos')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $acciones = $pagina->toArray()['props']['recurso']['accionesGenerales'];

            expect($acciones)->toBe([]);
        });
});
