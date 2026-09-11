<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Jobs\GenerarDocumentoJob;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * El aislamiento multi-tenant dentro de la cola.
 *
 * Es el test que nadie escribe y el que más falta hace. En una cola no hay
 * petición HTTP, así que no corre `EstablecerContextoOrganizacion` y no hay
 * usuario del que deducir el tenant: sin contexto, el scope global no devuelve
 * ninguna fila y la política de RLS deniega por defecto. El job no rompería,
 * sencillamente no vería nada, que es la peor forma de fallar que existe.
 */
beforeEach(function (): void {
    Storage::fake('documentos');

    $this->gotenberg = new GotenbergFalso;
    app()->instance(ClienteGotenberg::class, $this->gotenberg);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();
    $this->version = DocumentoVersion::factory()->delDocumento($this->documento->id)->create();
});

it('sin contexto no ve la versión y no hace nada: el fallo es SILENCIOSO', function (): void {
    $versionId = $this->version->id;

    // La situación del worker: proceso limpio, sin petición y sin usuario.
    sinOrganizacion();

    // Sin excepción y sin ruido. Ni el scope global ni RLS devuelven la fila, y
    // el job se va como si el documento no existiera.
    (new GenerarDocumentoJob($versionId, $this->organizacion->id))
        ->handle(app(GenerarDocumento::class));

    expect($this->gotenberg->solicitudes)->toBeEmpty();

    comoOrganizacion($this->organizacion->id);

    // La versión sigue encolada para siempre: nadie se entera de nada. Éste es
    // exactamente el motivo de que exista `ConContextoDeOrganizacion`.
    expect(DocumentoVersion::query()->findOrFail($versionId)->estado_generacion)
        ->toBe(EstadoGeneracion::Encolada);
});

it('el middleware del job fija la organización y el trabajo sale adelante', function (): void {
    $versionId = $this->version->id;
    $organizacionId = $this->organizacion->id;

    sinOrganizacion();

    $job = new GenerarDocumentoJob($versionId, $organizacionId);

    // Lo que hace la cola: pasar el job por sus middlewares.
    foreach ($job->middleware() as $middleware) {
        $middleware->handle($job, fn () => $job->handle(app(GenerarDocumento::class)));
    }

    comoOrganizacion($organizacionId);

    expect(DocumentoVersion::query()->findOrFail($versionId)->estado_generacion)
        ->toBe(EstadoGeneracion::Generada);
});

it('devuelve el contexto a denegar por defecto al terminar', function (): void {
    $job = new GenerarDocumentoJob($this->version->id, $this->organizacion->id);

    sinOrganizacion();

    foreach ($job->middleware() as $middleware) {
        $middleware->handle($job, fn () => $job->handle(app(GenerarDocumento::class)));
    }

    // Un worker atiende jobs de organizaciones distintas: si el contexto
    // sobreviviera, el siguiente job escribiría en el tenant equivocado.
    expect(app(ContextoOrganizacion::class)->hayContexto())->toBeFalse();
});

it('no toca la versión de otra organización aunque el job diga que es suya', function (): void {
    $otra = Organizacion::factory()->create();

    $job = new GenerarDocumentoJob($this->version->id, $otra->id);

    sinOrganizacion();

    foreach ($job->middleware() as $middleware) {
        $middleware->handle($job, fn () => $job->handle(app(GenerarDocumento::class)));
    }

    comoOrganizacion($this->organizacion->id);

    // Con el contexto de la otra organización, RLS y el scope no ven la fila:
    // el job se va sin hacer nada en vez de escribir donde no debe.
    expect(DocumentoVersion::query()->findOrFail($this->version->id)->estado_generacion)
        ->toBe(EstadoGeneracion::Encolada);

    expect($this->gotenberg->solicitudes)->toBeEmpty();
});

it('marca el fallo aunque `failed()` no pase por el middleware', function (): void {
    $job = new GenerarDocumentoJob($this->version->id, $this->organizacion->id);

    sinOrganizacion();

    $job->failed(new RuntimeException('Gotenberg respondió 503.'));

    comoOrganizacion($this->organizacion->id);

    $version = DocumentoVersion::query()->findOrFail($this->version->id);

    expect($version->estado_generacion)->toBe(EstadoGeneracion::Fallida)
        ->and($version->error)->toContain('503');
});
