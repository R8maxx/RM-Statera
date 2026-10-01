<?php

declare(strict_types=1);

use App\Domain\Comunicacion\Enums\TipoRetroalimentacion;
use App\Domain\Comunicacion\Models\Comunicacion;
use App\Domain\Documento\Contenido\ActaRevisionDireccion;
use App\Domain\Documento\Cuerpo\CuerpoDeFabrica;
use App\Domain\Documento\Cuerpo\MaterializarCuerpo;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\RevisionDireccion\AprobarRevision;
use App\Domain\RevisionDireccion\EntradasRevision;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| La entrada e) de la revisión por la dirección
|--------------------------------------------------------------------------
|
| Hasta la 7.4, el acta declaraba que la retroalimentación de las partes
| interesadas se aportaba fuera. Ahora sale de lo recibido en el periodo. Y las
| actas aprobadas antes **siguen diciendo lo que decían**: lo que la dirección
| tuvo delante no se reescribe.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->contenido = function (RevisionDireccion $revision) {
        // Reutilizados entre llamadas: un acta por revisión y un borrador por acta.
        $documento = Documento::query()->where('revision_direccion_id', $revision->id)->first()
            ?? Documento::factory()->actaRevision($revision)->create();
        $version = $documento->versiones()->whereNull('numero')->first()
            ?? DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(ActaRevisionDireccion::class)->construir($documento->fresh(), $version);
    };

    $this->limitaciones = fn (RevisionDireccion $revision): string => implode(' ', ($this->contenido)($revision)->limitaciones);

    $this->cuerpo = fn (RevisionDireccion $revision): string => (string) json_encode(
        app(MaterializarCuerpo::class)(
            CuerpoDeFabrica::para(TipoDocumento::ActaRevision),
            ($this->contenido)($revision),
            TipoDocumento::ActaRevision,
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
});

it('recoge lo recibido dentro del periodo revisado, y sólo eso', function (): void {
    $revision = RevisionDireccion::factory()
        ->delPeriodo(Carbon::today()->subMonths(6), Carbon::today())
        ->enCurso()
        ->create();

    Comunicacion::factory()->recibida(TipoRetroalimentacion::Queja)->create(['fecha' => Carbon::today()->subMonth(), 'asunto' => 'Dentro']);
    Comunicacion::factory()->recibida(TipoRetroalimentacion::Encuesta, 'Se presentó al comité.')->create(['fecha' => Carbon::today()->subMonths(2)]);
    Comunicacion::factory()->recibida()->create(['fecha' => Carbon::today()->subYear(), 'asunto' => 'Fuera']);
    // Lo emitido no es retroalimentación.
    Comunicacion::factory()->create(['fecha' => Carbon::today()->subMonth()]);

    $entrada = app(EntradasRevision::class)->para($revision)['retroalimentacion'];

    expect($entrada['total'])->toBe(2)
        ->and($entrada['sinRespuesta'])->toBe(1)
        ->and(collect($entrada['detalle'])->pluck('asunto')->all())->not->toContain('Fuera')
        ->and(collect($entrada['porTipo'])->pluck('tipo')->all())->toBe(['Queja', 'Resultado de encuesta']);
});

it('el acta nueva ya no dice que la retroalimentación se aporta fuera', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    $limitaciones = ($this->limitaciones)($revision->refresh());

    expect($limitaciones)->toContain('retroalimentación')
        ->and($limitaciones)->toContain('no comprueba que se haya registrado todo')
        ->and($limitaciones)->not->toContain('se aporta fuera de este documento');
});

it('el cuerpo del acta nueva lleva la e) en su apartado, con lo recibido', function (): void {
    Comunicacion::factory()->recibida(TipoRetroalimentacion::Sugerencia)->create([
        'fecha' => Carbon::today()->subMonth(),
        'asunto' => 'Ampliar el horario del soporte',
    ]);

    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    $cuerpo = ($this->cuerpo)($revision->refresh());

    expect($cuerpo)->toContain('e) Retroalimentación de las partes interesadas')
        ->and($cuerpo)->toContain('Ampliar el horario del soporte')
        ->and($cuerpo)->not->toContain('c) y e)');
});

it('un acta aprobada antes de la 7.4 sigue diciendo lo que decía', function (): void {
    // La instantánea de entonces, sin la clave de la e). Se crea ya aprobada
    // porque un acta aprobada no se modifica: lo blinda un trigger.
    $instantanea = app(EntradasRevision::class)->para(RevisionDireccion::factory()->enCurso()->make());
    unset($instantanea['retroalimentacion']);

    $revision = RevisionDireccion::factory()
        ->aprobada($this->usuario->id)
        ->create(['instantanea' => $instantanea]);

    $limitaciones = ($this->limitaciones)($revision);

    expect($limitaciones)->toContain('se aporta fuera de este documento')
        ->and(($this->cuerpo)($revision))->toContain('c) y e) Partes interesadas');
});
