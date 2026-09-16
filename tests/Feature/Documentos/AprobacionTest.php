<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\AprobarVersion;
use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\EnviarARevision;
use App\Domain\Documento\Excepciones\AprobacionNoPermitida;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\RechazarVersion;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/*
|--------------------------------------------------------------------------
| El flujo de aprobación (§ 4.5)
|--------------------------------------------------------------------------
|
| La regla que lo ordena todo: **aprobar es lo que emite**. No es una preferencia
| de flujo, es física del módulo — la portada se congela en `instantanea` al
| generar y el trigger vuelve la fila inmutable en cuanto tiene número, así que
| una firma posterior NO podría salir impresa en el documento que se entrega, que
| es justamente donde el auditor la busca.
|
| De ahí sale lo demás: firmar escribe la aprobación y manda regenerar; el número
| se asigna al terminar esa generación. En los tests la cola es `sync`, así que al
| volver de `AprobarVersion` la versión ya está entregada.
|
*/

beforeEach(function (): void {
    Storage::fake('documentos');
    $this->gotenberg = new GotenbergFalso;
    app()->instance(ClienteGotenberg::class, $this->gotenberg);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->conPeriodicidad(12)->create();
    $this->direccion = User::factory()->create(['organizacion_id' => $this->organizacion->id]);

    $this->borrador = function (): DocumentoVersion {
        $servicio = app(GenerarDocumento::class);
        $version = $servicio->encolar($this->documento);
        $servicio->ejecutar($version);

        return $version->fresh();
    };
});

it('un borrador recién generado está en borrador, no aprobado', function (): void {
    expect(($this->borrador)()->estado)->toBe(EstadoDocumental::Borrador);
});

it('mandar a revisión no congela nada: el borrador se sigue regenerando', function (): void {
    $version = ($this->borrador)();

    app(EnviarARevision::class)($version, 'Primera entrega.');

    expect($version->fresh()->estado)->toBe(EstadoDocumental::EnRevision);

    // Y se puede volver a generar sobre la misma fila: quien revisa pide cambios
    // y quien lo escribió los hace.
    $regenerado = ($this->borrador)();

    expect($regenerado->id)->toBe($version->id)
        ->and($regenerado->numero)->toBeNull();
});

it('no se manda a revisión lo que todavía no tiene PDF', function (): void {
    $version = app(GenerarDocumento::class)->encolar($this->documento);

    app(EnviarARevision::class)($version);
})->throws(AprobacionNoPermitida::class, 'todavía no tiene PDF');

it('no se aprueba un borrador que nadie ha mandado a revisión', function (): void {
    app(AprobarVersion::class)(($this->borrador)(), $this->direccion);
})->throws(AprobacionNoPermitida::class, 'no puede pasar a «Aprobado»');

it('aprobar numera, congela y deja la firma en la fila', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version, 'Primera entrega.');

    $aprobada = app(AprobarVersion::class)($version->fresh(), $this->direccion, 'Comité del 3 de marzo.');

    expect($aprobada->estado)->toBe(EstadoDocumental::Aprobado)
        ->and($aprobada->numero)->toBe(1)
        ->and($aprobada->aprobada_por_id)->toBe($this->direccion->id)
        ->and($aprobada->nota_aprobacion)->toBe('Comité del 3 de marzo.')
        ->and($aprobada->ruta)->toContain('/emitidas/v1/');
});

/**
 * Lo que justifica el orden entero del flujo: **la firma sale impresa**.
 *
 * Si se firmara después de emitir, el PDF entregado no podría llevarla, y el
 * único sitio donde el auditor la busca es la portada del documento.
 */
it('el PDF que se entrega lleva la firma en portada y no se marca como borrador', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version, 'Primera entrega.');

    $aprobada = app(AprobarVersion::class)($version->fresh(), $this->direccion, 'Comité del 3 de marzo.');

    $portada = $aprobada->instantanea['portada'];

    expect($portada['aprobadaPor'])->toBe($this->direccion->name)
        ->and($portada['aprobadaEn'])->toBe(Carbon::today()->format('d/m/Y'))
        ->and($portada['esBorrador'])->toBeFalse()
        // Y el pie de cada página lleva «v1», no «Borrador».
        ->and($portada['version'])->toBe('v1');

    $limitaciones = implode(' ', $aprobada->instantanea['limitaciones']);

    expect($limitaciones)->not->toContain('**Borrador.**');
});

/**
 * Y lo mismo sobre el HTML que se le manda a Gotenberg, que es lo que de verdad
 * se imprime.
 *
 * No basta con mirar la instantánea: la ficha de la portada es un bloque
 * **materializado**, y se materializaba una sola vez al crear el cuerpo. Con eso,
 * la instantánea traía la firma y el papel seguía diciendo «Borrador» con la
 * fecha del día en que alguien pulsó «Generar» por primera vez. Por eso
 * `portada_ficha` entró en `SIEMPRE_RECALCULADOS`.
 */
it('la firma llega al papel, y no sólo a la instantánea', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version, 'Primera entrega.');

    app(AprobarVersion::class)($version->fresh(), $this->direccion, 'Comité del 3 de marzo.');

    expect($this->gotenberg->html())
        ->toContain('Aprobada por')
        ->toContain($this->direccion->name)
        ->not->toContain('Borrador — no es una entrega');
});

it('la próxima revisión se calcula desde la firma y la periodicidad del documento', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version);

    $aprobada = app(AprobarVersion::class)($version->fresh(), $this->direccion);

    expect($aprobada->fecha_proxima_revision?->toDateString())
        ->toBe(Carbon::today()->addMonths(12)->toDateString());
});

/**
 * Sin periodicidad no hay fecha, y es una respuesta legítima: una Declaración de
 * Aplicabilidad se rehace cuando cambia el alcance, no cuando pasa un año.
 * Inventarle un plazo llenaría el calendario de vencimientos que nadie ha
 * decidido.
 */
it('sin periodicidad declarada no se inventa una fecha de revisión', function (): void {
    $this->documento->update(['periodicidad_revision_meses' => null]);

    $version = ($this->borrador)();
    app(EnviarARevision::class)($version);

    $aprobada = app(AprobarVersion::class)($version->fresh(), $this->direccion);

    expect($aprobada->fecha_proxima_revision)->toBeNull();
});

it('rechazar exige motivo, y la regla vive en el dominio', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version);

    app(RechazarVersion::class)($version->fresh(), '   ');
})->throws(AprobacionNoPermitida::class, 'exige escribir por qué');

it('rechazar deja el motivo escrito y retira cualquier firma', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version);

    $rechazada = app(RechazarVersion::class)($version->fresh(), 'Falta el apartado de responsabilidades.');

    expect($rechazada->estado)->toBe(EstadoDocumental::Rechazado)
        ->and($rechazada->motivo_rechazo)->toBe('Falta el apartado de responsabilidades.')
        ->and($rechazada->numero)->toBeNull()
        ->and($rechazada->aprobada_en)->toBeNull();
});

it('una versión rechazada se retoma sin gastar número', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version);
    app(RechazarVersion::class)($version->fresh(), 'Falta el apartado de responsabilidades.');

    // Se vuelve a mandar a revisión y se aprueba: sigue siendo la v1, porque un
    // número gastado en algo que nunca se entregó dejaría un hueco que el
    // auditor preguntaría.
    app(EnviarARevision::class)($version->fresh(), 'Corregido.');
    $aprobada = app(AprobarVersion::class)($version->fresh(), $this->direccion);

    expect($aprobada->numero)->toBe(1)
        ->and($aprobada->motivo_rechazo)->toBeNull();
});

it('no se aprueba dos veces lo ya aprobado', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version);
    $aprobada = app(AprobarVersion::class)($version->fresh(), $this->direccion);

    app(AprobarVersion::class)($aprobada, $this->direccion);
})->throws(AprobacionNoPermitida::class, 'ya está aprobada');

/*
|--------------------------------------------------------------------------
| Por las rutas de verdad
|--------------------------------------------------------------------------
*/

it('el flujo entero se recorre por HTTP', function (): void {
    $usuario = usuarioCon(Rol::ResponsableSeguridad);
    $version = ($this->borrador)();

    $this->actingAs($usuario)
        ->post("/documentos/{$this->documento->id}/revision", ['motivo' => 'Primera entrega.'])
        ->assertRedirect("/documentos/{$this->documento->id}");

    expect($version->fresh()->estado)->toBe(EstadoDocumental::EnRevision);

    $this->actingAs($usuario)
        ->post("/documentos/{$this->documento->id}/versiones/{$version->id}/aprobar", [
            'nota' => 'Comité del 3 de marzo.',
        ])
        ->assertRedirect("/documentos/{$this->documento->id}");

    expect($version->fresh()->estado)->toBe(EstadoDocumental::Aprobado);
});

it('rechazar sin motivo no pasa del FormRequest', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version);

    $this->actingAs(usuarioCon(Rol::ResponsableSeguridad))
        ->post("/documentos/{$this->documento->id}/versiones/{$version->id}/rechazar", ['motivo' => ''])
        ->assertSessionHasErrors('motivo');
});

it('la ficha manda el estado documental y sus transiciones', function (): void {
    $version = ($this->borrador)();
    app(EnviarARevision::class)($version);

    $props = $this->actingAs(usuarioCon(Rol::ResponsableSeguridad))
        ->get("/documentos/{$this->documento->id}")
        ->viewData('page')['props'];

    expect($props['versionEnCurso']['estado'])->toBe('en_revision')
        ->and($props['puedeAprobar'])->toBeTrue();

    $destinos = array_column($props['versionEnCurso']['transiciones'], 'valor');

    expect($destinos)->toContain('aprobado', 'rechazado', 'borrador');
});
