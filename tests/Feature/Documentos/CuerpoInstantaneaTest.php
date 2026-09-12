<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Cuerpo\GuardarCuerpo;
use App\Domain\Documento\Cuerpo\Nodo;
use App\Domain\Documento\EmitirVersion;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoCuerpo;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * Lo que se entregó se queda como se entregó, aunque el documento siga vivo.
 *
 * Con el cuerpo editable esto deja de ser una obviedad: el documento es ahora
 * una fila que alguien puede reescribir entera cualquier martes. Si la versión
 * emitida se leyera de esa fila, la SoA que se le enseñó al auditor en marzo
 * diría en octubre lo que diga el editor hoy —con la huella de marzo impresa
 * dentro—. De ahí que el cuerpo se congele en la instantánea.
 */
beforeEach(function (): void {
    Storage::fake('documentos');
    app()->instance(ClienteGotenberg::class, new GotenbergFalso);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();

    $this->generar = fn (): DocumentoVersion => tap(
        app(GenerarDocumento::class)->encolar($this->documento),
        fn (DocumentoVersion $v) => app(GenerarDocumento::class)->ejecutar($v),
    )->fresh();
});

it('congela el cuerpo entero en la instantánea de la versión', function (): void {
    $version = ($this->generar)();

    $cuerpo = $version->instantanea['cuerpo'] ?? null;

    expect($cuerpo)->toBeArray()
        ->and($cuerpo['type'] ?? null)->toBe('doc')
        // Y lo que ya congelaba sigue estando: esto se añade, no sustituye.
        ->and($version->instantanea)->toHaveKeys(['titulo', 'portada', 'resumen', 'filas']);
});

it('una versión emitida no cambia aunque se reescriba el documento entero', function (): void {
    $emitida = app(EmitirVersion::class)(($this->generar)(), 'Entrega a auditoría.');

    $antes = $emitida->instantanea['cuerpo'];

    $fila = DocumentoCuerpo::query()->where('documento_id', $this->documento->id)->firstOrFail();

    app(GuardarCuerpo::class)($fila, Nodo::de('doc', [], [
        Nodo::de('portada', [], [Nodo::de('marcaPortada', [], [Nodo::texto('Otra cosa')])]),
        Nodo::de('seccion', [], [Nodo::parrafo('El documento ahora dice esto.')]),
    ]));

    expect($emitida->fresh()?->instantanea['cuerpo'])->toBe($antes)
        ->and((string) json_encode($antes))->not->toContain('El documento ahora dice esto.');
});

it('el trigger rechaza tocar la fila de una versión emitida', function (): void {
    $emitida = app(EmitirVersion::class)(($this->generar)(), 'Entrega a auditoría.');

    // No es que el código sea educado: lo impide PostgreSQL.
    expect(fn () => $emitida->update([
        'instantanea' => [...$emitida->instantanea, 'cuerpo' => ['type' => 'doc']],
    ]))->toThrow(QueryException::class);
});

it('el borrador siguiente sí recoge lo que se editó', function (): void {
    app(EmitirVersion::class)(($this->generar)(), 'Primera entrega.');

    $fila = DocumentoCuerpo::query()->where('documento_id', $this->documento->id)->firstOrFail();

    app(GuardarCuerpo::class)($fila, Nodo::de('doc', [], [
        Nodo::de('portada', [], [
            Nodo::de('marcaPortada', [], [Nodo::texto('Statera')]),
            Nodo::hueco('portada_ficha'),
            Nodo::hueco('portada_pie'),
        ]),
        Nodo::de('seccion', [], [
            Nodo::encabezado(2, 'Introducción'),
            Nodo::parrafo('Redactado a mano para la segunda entrega.'),
        ]),
        Nodo::de('seccion', [], [
            Nodo::encabezado(2, 'Limitaciones de esta declaración'),
            Nodo::hueco('limitaciones_sistema'),
        ]),
    ]));

    $segunda = ($this->generar)();

    expect((string) json_encode($segunda->instantanea['cuerpo']))
        ->toContain('Redactado a mano para la segunda entrega.');
});
