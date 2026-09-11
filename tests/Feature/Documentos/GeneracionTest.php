<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Jobs\GenerarDocumentoJob;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Render\AssetsDocumento;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * La tubería: encolar, generar, almacenar y registrar.
 *
 * Aquí no se prueba qué dice el documento —de eso van `ContenidoSoaTest` y
 * `ContenidoDdaTest`— sino que lo que se le manda a Gotenberg es lo que debe y
 * que lo que vuelve se guarda de forma que se pueda demostrar.
 *
 * OJO con `encolar()` en esta suite: `phpunit.xml` fija `QUEUE_CONNECTION=sync`,
 * así que despachar el job lo EJECUTA en el acto. Es cómodo para la mayoría de
 * los tests y una trampa para los que quieren mirar el estado intermedio: ésos
 * llevan `Queue::fake()`.
 */
beforeEach(function (): void {
    Storage::fake('documentos');

    $this->gotenberg = new GotenbergFalso;
    app()->instance(ClienteGotenberg::class, $this->gotenberg);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($this->sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($this->sistema->id)->create([
        'codigo' => 'SOA-SGSI-01',
        'titulo' => 'Declaración de Aplicabilidad',
    ]);
});

it('encola el trabajo en la cola de documentos, con escalares y no con modelos', function (): void {
    Queue::fake();

    $version = app(GenerarDocumento::class)->encolar($this->documento, usuarioCon());

    Queue::assertPushedOn('documentos', GenerarDocumentoJob::class,
        function (GenerarDocumentoJob $job) use ($version): bool {
            return $job->versionId === $version->id
                && $job->organizacionId === $this->organizacion->id;
        });

    expect($version->estado_generacion)->toBe(EstadoGeneracion::Encolada);
});

it('nunca genera dentro del ciclo de petición', function (): void {
    Queue::fake();

    app(GenerarDocumento::class)->encolar($this->documento);

    // Encolar deja la fila puesta y no llama a Gotenberg: una SoA de noventa y
    // tres controles con evidencias tarda, y bloquear la petición con eso es
    // cómo se consigue que alguien recargue y lance tres generaciones.
    expect($this->gotenberg->solicitudes)->toBeEmpty();
});

it('guarda el PDF con su huella y su tamaño', function (): void {
    $version = generada($this->documento);

    expect($version->estado_generacion)->toBe(EstadoGeneracion::Generada)
        ->and($version->disco)->toBe('documentos')
        ->and($version->hash_sha256)->toHaveLength(64)
        ->and($version->tamano)->toBeGreaterThan(0)
        ->and($version->mime)->toBe('application/pdf');

    Storage::disk('documentos')->assertExists($version->ruta);

    // La huella es del fichero que está en el disco, no de otra cosa.
    expect(hash('sha256', Storage::disk('documentos')->get($version->ruta)))
        ->toBe($version->hash_sha256);
});

it('congela en la instantánea lo mismo que se pintó', function (): void {
    $version = generada($this->documento);

    $instantanea = $version->instantanea;

    expect($instantanea)->toHaveKeys(['titulo', 'portada', 'resumen', 'filas', 'limitaciones']);
    // Un PDF no se puede consultar: sin esto no hay forma de contestar «¿qué
    // cambió entre la v3 y la v4?».
    expect($instantanea['filas'])->toHaveCount($version->total_requisitos);
});

it('denormaliza los tres recuentos para que la tabla no abra el jsonb', function (): void {
    $version = generada($this->documento);

    expect($version->total_requisitos)->toBe(count($version->instantanea['filas']))
        ->and($version->total_excluidos)->toBe(0)
        ->and($version->total_implantados)->toBe(0);
});

it('manda el HTML y los assets en el multipart, nunca una URL', function (): void {
    generada($this->documento);

    $solicitud = $this->gotenberg->ultima();

    expect($solicitud->html)->toStartWith('<!DOCTYPE html>');
    expect($solicitud->nombresDeAssets())->toContain(AssetsDocumento::HOJA);

    // La hoja se referencia por su nombre a secas: Gotenberg deja el multipart
    // plano y la allow-list del contenedor sólo admite `file:///tmp/...`.
    expect($solicitud->html)->toContain('href="'.AssetsDocumento::HOJA.'"');
    expect($solicitud->html)->not->toContain('http://');
    expect($solicitud->html)->not->toContain('https://');
});

it('manda cabecera y pie autocontenidos, porque Chromium no les hereda el CSS', function (): void {
    generada($this->documento);

    $solicitud = $this->gotenberg->ultima();

    foreach ([$solicitud->cabecera, $solicitud->pie] as $parte) {
        // Su propio `<style>`, su propio `font-size`, y ni rastro de la hoja
        // común: fuera de aquí, nada de eso llega.
        expect($parte)->toContain('<style>')
            ->and($parte)->toContain('font-size')
            ->and($parte)->not->toContain(AssetsDocumento::HOJA);
    }

    // Las clases mágicas de Chromium sólo funcionan dentro del pie.
    expect($solicitud->pie)->toContain('class="pageNumber"')->toContain('class="totalPages"');
});

it('estampa la clasificación y la versión en el pie de cada página', function (): void {
    generada($this->documento);

    expect($this->gotenberg->ultima()->pie)
        // `mp.info.2`: la clasificación va impresa en el documento.
        ->toContain('USO INTERNO')
        ->toContain('Borrador');
});

it('pide PDF/A con los metadatos del documento', function (): void {
    generada($this->documento);

    expect($this->gotenberg->ultima()->metadatos)
        ->toHaveKey('Creator', 'Statera')
        ->toHaveKey('Title', 'Declaración de Aplicabilidad — SOA-SGSI-01');
});

it('deja constancia de quién pidió el documento, porque la cola no tiene usuario', function (): void {
    $usuario = usuarioCon();

    $version = app(GenerarDocumento::class)->encolar($this->documento, $usuario);

    // `RegistroAuditoria` lee el usuario de la sesión y en cola no hay ninguna:
    // esta columna es la única constancia.
    expect($version->generada_por_id)->toBe($usuario->id);
});

it('no genera dos veces si otro trabajador ya se llevó el borrador', function (): void {
    Queue::fake();

    $version = app(GenerarDocumento::class)->encolar($this->documento);

    // Simula al otro trabajador: ya lo puso en «generando».
    DocumentoVersion::query()->whereKey($version->id)
        ->update(['estado_generacion' => EstadoGeneracion::Generando->value]);

    app(GenerarDocumento::class)->ejecutar($version);

    expect($this->gotenberg->solicitudes)->toBeEmpty();
});

it('suelta el fichero anterior al volver a encolar, para no dejar una fila incoherente', function (): void {
    $version = generada($this->documento);

    expect($version->ruta)->not->toBeNull();

    Queue::fake();
    $reencolada = app(GenerarDocumento::class)->encolar($this->documento);

    // El CHECK de la tabla exige ruta y huella exactamente cuando el estado es
    // «generada»: dejar las de la generación anterior lo rompería.
    expect($reencolada->estado_generacion)->toBe(EstadoGeneracion::Encolada)
        ->and($reencolada->ruta)->toBeNull()
        ->and($reencolada->hash_sha256)->toBeNull();
});

it('registra el fallo con su mensaje cuando Gotenberg no responde', function (): void {
    app()->instance(ClienteGotenberg::class, new GotenbergFalso('el contenedor no está levantado'));

    // Sin esto la cola síncrona ejecutaría el job dentro de `encolar()` y la
    // excepción saldría antes de que se pueda mirar el estado intermedio.
    Queue::fake();

    $version = app(GenerarDocumento::class)->encolar($this->documento);

    $job = new GenerarDocumentoJob($version->id, $this->organizacion->id);

    try {
        $job->handle(app(GenerarDocumento::class));
    } catch (Throwable $e) {
        // El `catch` del servicio no marca «fallida»: eso lo hace `failed()`,
        // después del último intento, para no decir que falló algo que Horizon
        // todavía va a reintentar.
        expect($version->fresh()->estado_generacion)->toBe(EstadoGeneracion::Generando);

        $job->failed($e);
    }

    $version->refresh();

    expect($version->estado_generacion)->toBe(EstadoGeneracion::Fallida)
        ->and($version->error)->toContain('no está levantado');
});

/**
 * Un documento ya generado.
 *
 * Con la cola en `sync`, encolar ejecuta el job en el acto: es el camino
 * completo —encolar, job, middleware de contexto, servicio, Gotenberg, disco—
 * y no un atajo.
 */
function generada(Documento $documento): DocumentoVersion
{
    return app(GenerarDocumento::class)->encolar($documento)->fresh();
}
