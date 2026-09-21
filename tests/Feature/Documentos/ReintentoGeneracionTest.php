<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * La barrera contra la doble generación no puede tragarse los reintentos.
 *
 * `ejecutar()` toma la fila con un `UPDATE` condicional para que dos
 * trabajadores no generen el mismo borrador a la vez. Sin distinguir el
 * reintento, esa barrera convertía el segundo intento en un no-op: la fila ya
 * estaba en `generando`, el `UPDATE` afectaba a cero filas, el job terminaba
 * «con éxito», `failed()` no corría nunca y la versión se quedaba en «generando»
 * para siempre. La ficha sondeaba dos minutos y se rendía sin poder decir qué
 * había pasado.
 *
 * Mordió de verdad: dos versiones llevaban cinco días colgadas con el fallo real
 * —no se podía escribir en MinIO— sin una sola fila en `failed_jobs`.
 */
beforeEach(function (): void {
    Storage::fake('documentos');

    // `phpunit.xml` fija `QUEUE_CONNECTION=sync`, así que `encolar()` generaría
    // en el acto y aquí hace falta mirar el estado intermedio. Mismo cuidado que
    // en `GeneracionTest`.
    Queue::fake();

    app()->instance(ClienteGotenberg::class, new GotenbergFalso);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create([
        'codigo' => 'SOA-SGSI-01',
    ]);
});

it('un reintento retoma la fila que dejó a medias el intento anterior', function (): void {
    $version = app(GenerarDocumento::class)->encolar($this->documento);

    // Lo que deja un intento que reventó a mitad: tomada y sin fichero.
    $version->update(['estado_generacion' => EstadoGeneracion::Generando->value]);

    app(GenerarDocumento::class)->ejecutar($version->fresh(), reintento: true);

    expect($version->fresh()->estado_generacion)->toBe(EstadoGeneracion::Generada)
        ->and($version->fresh()->ruta)->not->toBeNull();
});

it('sin reintento, una fila que ya se llevó otro trabajador no se toca', function (): void {
    $version = app(GenerarDocumento::class)->encolar($this->documento);

    $version->update(['estado_generacion' => EstadoGeneracion::Generando->value]);

    app(GenerarDocumento::class)->ejecutar($version->fresh());

    // Ni la genera ni la estropea: la deja exactamente como estaba.
    expect($version->fresh()->estado_generacion)->toBe(EstadoGeneracion::Generando)
        ->and($version->fresh()->ruta)->toBeNull();
});

it('el primer intento sigue exigiendo que la fila esté encolada', function (): void {
    $version = app(GenerarDocumento::class)->encolar($this->documento);

    $version->update(['estado_generacion' => EstadoGeneracion::Fallida->value, 'error' => 'lo que sea']);

    app(GenerarDocumento::class)->ejecutar($version->fresh(), reintento: true);

    // `generando` es lo único que un reintento puede retomar además de
    // `encolada`: una fila ya marcada como fallida no se resucita sola.
    expect($version->fresh()->estado_generacion)->toBe(EstadoGeneracion::Fallida);
});
