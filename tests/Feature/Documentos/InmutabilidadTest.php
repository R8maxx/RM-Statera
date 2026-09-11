<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\EmitirVersion;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\Excepciones\VersionNoEmisible;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * Una versión emitida no se modifica, y no porque el código sea educado.
 *
 * La regla que sostiene el módulo entero —el PDF entregado se almacena, no se
 * regenera— no puede depender de que nadie escriba un `update()` distraído
 * dentro de seis meses. Lo garantiza un trigger de PostgreSQL, y esto lo fija.
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

it('rechaza en la base cualquier cambio sobre una versión emitida', function (): void {
    $version = app(EmitirVersion::class)(($this->generar)(), 'Entrega a auditoría.');

    expect($version->numero)->toBe(1);

    DocumentoVersion::query()->whereKey($version->id)->update(['motivo' => 'Otro motivo']);
})->throws(QueryException::class, 'Una version emitida no se modifica');

it('deja regenerar el borrador cuantas veces haga falta', function (): void {
    $primera = ($this->generar)();
    $rutaPrimera = $primera->ruta;

    $segunda = ($this->generar)();

    // Misma fila —hay un solo borrador por documento—, fichero nuevo: regenerar
    // escribe una clave nueva en vez de sobreescribir la anterior.
    expect($segunda->id)->toBe($primera->id)
        ->and($segunda->ruta)->not->toBe($rutaPrimera)
        ->and($segunda->estado_generacion)->toBe(EstadoGeneracion::Generada);
});

it('numera las entregas de forma correlativa', function (): void {
    $emitir = app(EmitirVersion::class);

    expect($emitir(($this->generar)())->numero)->toBe(1);
    expect($emitir(($this->generar)())->numero)->toBe(2);
    expect($emitir(($this->generar)())->numero)->toBe(3);

    expect($this->documento->siguienteNumero())->toBe(4);
});

it('mueve el PDF emitido al prefijo que en producción lleva Object Lock', function (): void {
    $version = app(EmitirVersion::class)(($this->generar)());

    expect($version->ruta)->toContain('/emitidas/v1/')
        ->and($version->ruta)->not->toContain('/borradores/');

    Storage::disk('documentos')->assertExists($version->ruta);
});

it('conserva la huella al emitir: el PDF es byte a byte el mismo', function (): void {
    $borrador = ($this->generar)();
    $huella = $borrador->hash_sha256;

    $emitida = app(EmitirVersion::class)($borrador);

    expect($emitida->hash_sha256)->toBe($huella);
    expect(hash('sha256', Storage::disk('documentos')->get($emitida->ruta)))->toBe($huella);
});

it('no emite un borrador que todavía no tiene PDF', function (): void {
    $version = app(GenerarDocumento::class)->encolar($this->documento);

    app(EmitirVersion::class)($version);
})->throws(VersionNoEmisible::class, 'todavía no tiene PDF');

it('no vuelve a emitir lo ya emitido', function (): void {
    $version = app(EmitirVersion::class)(($this->generar)());

    app(EmitirVersion::class)($version);
})->throws(VersionNoEmisible::class, 'ya está emitida');

it('deja hueco para un borrador nuevo después de cada entrega', function (): void {
    app(EmitirVersion::class)(($this->generar)());

    // El índice único parcial admite un solo borrador vivo: si la emisión no
    // hubiera liberado el hueco, esto reventaría.
    $nuevo = app(GenerarDocumento::class)->encolar($this->documento);

    expect($nuevo->esBorrador())->toBeTrue();
    expect($this->documento->versiones()->whereNull('numero')->count())->toBe(1);
});
