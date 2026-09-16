<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\AprobarVersion;
use App\Domain\Documento\EmitirVersion;
use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\EnviarARevision;
use App\Domain\Documento\Excepciones\VersionNoEmisible;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * Una versión emitida no se modifica, y no porque el código sea educado.
 *
 * La regla que sostiene el módulo entero —el PDF entregado se almacena, no se
 * regenera— no puede depender de que nadie escriba un `update()` distraído
 * dentro de seis meses. Lo garantiza un trigger de PostgreSQL, y esto lo fija.
 *
 * Con el flujo de aprobación el trigger tiene **una sola puerta**: jubilar la
 * versión aprobada al firmarse la siguiente. Sin ella, un documento aprobado no
 * podría revisarse nunca. Todo lo demás sigue cerrado.
 */
beforeEach(function (): void {
    Storage::fake('documentos');
    app()->instance(ClienteGotenberg::class, new GotenbergFalso);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();
    $this->direccion = User::factory()->create(['organizacion_id' => $this->organizacion->id]);

    $this->generar = fn (): DocumentoVersion => tap(
        app(GenerarDocumento::class)->encolar($this->documento),
        fn (DocumentoVersion $v) => app(GenerarDocumento::class)->ejecutar($v),
    )->fresh();

    /*
     * El camino completo hasta la entrega: generar, mandar a revisión y firmar.
     *
     * Firmar encola la regeneración —el PDF tiene que salir con la firma en
     * portada— y en los tests la cola es `sync`, así que al volver de aquí la
     * versión ya está numerada y congelada.
     */
    $this->entregar = function (?string $motivo = null): DocumentoVersion {
        $version = ($this->generar)();

        app(EnviarARevision::class)($version, $motivo ?? 'Entrega a auditoría.');

        return app(AprobarVersion::class)($version->fresh(), $this->direccion);
    };
});

it('rechaza en la base cualquier cambio sobre una versión emitida', function (): void {
    $version = ($this->entregar)();

    expect($version->numero)->toBe(1);

    DocumentoVersion::query()->whereKey($version->id)->update(['motivo' => 'Otro motivo']);
})->throws(QueryException::class, 'Una version emitida no se modifica');

/**
 * El `updated_at` solo tampoco pasa.
 *
 * Es el caso que se cuela si el trigger enumera columnas en vez de comparar el
 * registro entero: Eloquent toca la marca de tiempo en cada `save()`, y una fila
 * que se puede «tocar» es una fila que alguien acabará tocando.
 */
it('rechaza incluso un toque que sólo cambia la marca de tiempo', function (): void {
    $version = ($this->entregar)();

    DocumentoVersion::query()->whereKey($version->id)->update(['updated_at' => now()->addDay()]);
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
    expect(($this->entregar)()->numero)->toBe(1);
    expect(($this->entregar)()->numero)->toBe(2);
    expect(($this->entregar)()->numero)->toBe(3);

    expect($this->documento->siguienteNumero())->toBe(4);
});

/**
 * La única puerta del trigger, y la que hace falta sí o sí: al firmarse la v2,
 * la v1 pasa a obsoleta. Es el mismo agujero que se le talló a
 * `riesgo_valoraciones` para poder jubilar la valoración anterior.
 */
it('jubila la versión anterior al aprobarse la siguiente', function (): void {
    $primera = ($this->entregar)();
    $segunda = ($this->entregar)();

    expect($primera->fresh()->estado)->toBe(EstadoDocumental::Obsoleto)
        ->and($primera->fresh()->obsoleta_en)->not->toBeNull()
        ->and($segunda->estado)->toBe(EstadoDocumental::Aprobado);

    // Y sólo una viva: lo impone el índice único parcial.
    expect($this->documento->versiones()->where('estado', EstadoDocumental::Aprobado->value)->count())->toBe(1);
});

it('mueve el PDF emitido al prefijo que en producción lleva Object Lock', function (): void {
    $version = ($this->entregar)();

    expect($version->ruta)->toContain('/emitidas/v1/')
        ->and($version->ruta)->not->toContain('/borradores/');

    Storage::disk('documentos')->assertExists($version->ruta);
});

it('conserva la huella al emitir: el PDF es byte a byte el mismo', function (): void {
    $emitida = ($this->entregar)();

    expect(hash('sha256', Storage::disk('documentos')->get($emitida->ruta)))->toBe($emitida->hash_sha256);
});

it('no emite un borrador que todavía no tiene PDF', function (): void {
    $version = app(GenerarDocumento::class)->encolar($this->documento);

    app(EmitirVersion::class)($version);
})->throws(VersionNoEmisible::class, 'todavía no tiene PDF');

/**
 * Y sobre todo: no se entrega lo que nadie ha firmado. Emitir dejó de ser un
 * acto propio cuando aprobar pasó a ser lo que emite.
 */
it('no entrega un borrador que la dirección no ha aprobado', function (): void {
    app(EmitirVersion::class)(($this->generar)());
})->throws(VersionNoEmisible::class, 'sólo se entrega lo que la dirección ha aprobado');

it('no vuelve a emitir lo ya emitido', function (): void {
    $version = ($this->entregar)();

    app(EmitirVersion::class)($version);
})->throws(VersionNoEmisible::class, 'ya está emitida');

it('deja hueco para un borrador nuevo después de cada entrega', function (): void {
    ($this->entregar)();

    // El índice único parcial admite un solo borrador vivo: si la emisión no
    // hubiera liberado el hueco, esto reventaría.
    $nuevo = app(GenerarDocumento::class)->encolar($this->documento);

    expect($nuevo->esBorrador())->toBeTrue();
    expect($this->documento->versiones()->whereNull('numero')->count())->toBe(1);
});
