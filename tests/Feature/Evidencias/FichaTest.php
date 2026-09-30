<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Evidencia\Vigencia;
use App\Domain\Evidencia\VincularEvidencia;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La ficha de una evidencia
|--------------------------------------------------------------------------
|
| Tres cosas que la ficha hace además de enseñar: dice hasta cuándo prueba con
| la misma regla que la tabla, vincula varios requisitos de golpe desde el lado
| de la evidencia, y renueva —otra evidencia con los mismos vínculos, que apaga
| el aviso de la anterior sin borrarla—.
|
*/

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-30');

    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->ens = Marco::factory()->create(['codigo' => 'ENS-SINTETICO', 'nombre' => 'Esquema sintético']);
    $this->iso = Marco::factory()->create(['codigo' => 'ISO-SINTETICO', 'nombre' => 'Norma sintética']);

    $this->sistemaEns = Sistema::factory()->de($this->organizacion)->conMarco($this->ens)->create(['codigo' => 'SIS-01']);
    $this->sistemaIso = Sistema::factory()->de($this->organizacion)->conMarco($this->iso)->create(['codigo' => 'SGSI-01']);

    $this->implantacion = function (Marco $marco, Sistema $sistema, string $codigo, array $campos = []): Implantacion {
        $requisito = Requisito::factory()->conCodigo($codigo)->create(['marco_id' => $marco->id]);

        return Implantacion::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'sistema_id' => $sistema->id,
            'requisito_id' => $requisito->id,
            ...$campos,
        ]);
    };

    /** @param array<string, mixed> $campos */
    $this->evidencia = fn (array $campos = []): Evidencia => Evidencia::factory()->create([
        'organizacion_id' => $this->organizacion->id,
        'url_externa' => 'https://interno.ejemplo/panel',
        'fecha_obtencion' => '2026-08-23',
        'fecha_caducidad' => null,
        'periodicidad_renovacion' => null,
        ...$campos,
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/*
| La vigencia
*/

it('dice hasta cuándo prueba, con el periodo contado en días', function (): void {
    $vigencia = Vigencia::de(($this->evidencia)(['fecha_caducidad' => '2027-02-23']));

    expect($vigencia->estado)->toBe('vigente')
        ->and($vigencia->diasTotales)->toBe(184)
        ->and($vigencia->diasTranscurridos)->toBe(38)
        ->and($vigencia->diasRestantes)->toBe(146);
});

it('distingue por caducar, caducada, sin caducidad y renovada', function (): void {
    $nueva = ($this->evidencia)();

    expect(Vigencia::de(($this->evidencia)(['fecha_caducidad' => '2026-10-12']))->etiqueta)->toBe('Caduca en 12 días')
        ->and(Vigencia::de(($this->evidencia)(['fecha_caducidad' => '2026-09-29']))->estado)->toBe('caducada')
        ->and(Vigencia::de(($this->evidencia)())->estado)->toBe('sin_caducidad')
        ->and(Vigencia::de(($this->evidencia)([
            'fecha_caducidad' => '2026-09-29',
            'renovada_por_id' => $nueva->id,
        ]))->estado)->toBe('renovada');
});

it('la ficha manda la vigencia y los marcos implantados, también los que suman cero', function (): void {
    $medida = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.1');
    ($this->implantacion)($this->iso, $this->sistemaIso, 'A.5.18');

    $evidencia = ($this->evidencia)(['fecha_caducidad' => '2027-02-23']);
    app(VincularEvidencia::class)->vincular($evidencia, $medida, null, 'Prueba el mecanismo de autenticación.');

    $this->actingAs($this->usuario)
        ->get("/evidencias/{$evidencia->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('evidencias/Ficha')
            ->where('vigencia.estado', 'vigente')
            ->where('vigencia.diasRestantes', 146)
            ->where('marcos.0.codigo', 'ENS-SINTETICO')
            ->where('marcos.0.requisitos', 1)
            ->where('marcos.1.codigo', 'ISO-SINTETICO')
            ->where('marcos.1.requisitos', 0)
            ->where('vinculos.0.nota', 'Prueba el mecanismo de autenticación.')
            ->where('vinculos.0.editable', true)
            ->where('puede.vincular', true)
            ->missing('implantacionesDisponibles')
        );
});

it('el auditor ve la ficha sin los botones de escribir', function (): void {
    $evidencia = ($this->evidencia)();
    app(VincularEvidencia::class)->vincular($evidencia, ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.1'));

    $this->actingAs(usuarioCon(Rol::Auditor))
        ->get("/evidencias/{$evidencia->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('puede.gestionar', false)
            ->where('puede.vincular', false)
            ->where('vinculos.0.editable', false)
        );
});

/*
| Vincular requisitos desde la evidencia
*/

it('ofrece, sólo si se piden, los requisitos que todavía no prueba', function (): void {
    $vinculada = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.1');
    $libre = ($this->implantacion)($this->iso, $this->sistemaIso, 'A.5.18');

    $evidencia = ($this->evidencia)();
    app(VincularEvidencia::class)->vincular($evidencia, $vinculada);

    // Primero la petición normal: el helper lee la versión que fija el middleware.
    $this->actingAs($this->usuario)->get("/evidencias/{$evidencia->id}");

    $this->actingAs($this->usuario)
        ->get("/evidencias/{$evidencia->id}", recargaParcial('evidencias/Ficha', ['implantacionesDisponibles']))
        ->assertOk()
        ->assertJsonCount(1, 'props.implantacionesDisponibles')
        ->assertJsonPath('props.implantacionesDisponibles.0.valor', (string) $libre->id)
        ->assertJsonPath('props.implantacionesDisponibles.0.descripcion', 'ISO-SINTETICO · SGSI-01');
});

it('vincula varios requisitos de marcos distintos de una vez, con la misma nota', function (): void {
    $medida = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.5');
    $control = ($this->implantacion)($this->iso, $this->sistemaIso, 'A.8.5');
    $evidencia = ($this->evidencia)();

    $this->actingAs($this->usuario)
        ->post("/evidencias/{$evidencia->id}/requisitos", [
            'implantaciones' => [$medida->id, $control->id],
            'nota' => 'Prueba el segundo factor.',
        ])
        ->assertSessionHasNoErrors();

    $vinculos = $evidencia->implantaciones()->get();

    expect($vinculos)->toHaveCount(2)
        ->and($vinculos->map(fn (Implantacion $implantacion) => $implantacion->getRelationValue('pivot')->getAttribute('nota'))->unique()->all())
        ->toBe(['Prueba el segundo factor.']);
});

it('el técnico no vincula lo que está a cargo de otro, y se le dice cuántas se saltaron', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $suya = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.1', ['responsable_id' => $tecnico->id]);
    $ajena = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.2', ['responsable_id' => $this->usuario->id]);
    $evidencia = ($this->evidencia)();

    $this->actingAs($tecnico)->get("/evidencias/{$evidencia->id}");

    $this->actingAs($tecnico)
        ->get("/evidencias/{$evidencia->id}", recargaParcial('evidencias/Ficha', ['implantacionesDisponibles']))
        ->assertJsonCount(1, 'props.implantacionesDisponibles')
        ->assertJsonPath('props.implantacionesDisponibles.0.valor', (string) $suya->id);

    $this->actingAs($tecnico)
        ->post("/evidencias/{$evidencia->id}/requisitos", ['implantaciones' => [$suya->id, $ajena->id]])
        ->assertSessionHasNoErrors();

    expect($evidencia->implantaciones()->pluck('implantaciones.id')->all())->toBe([$suya->id]);
});

it('no vincula una implantación de otra organización', function (): void {
    $evidencia = ($this->evidencia)();

    $otra = Organizacion::factory()->create();
    comoOrganizacion($otra);
    $ajena = Implantacion::factory()->create([
        'organizacion_id' => $otra->id,
        'sistema_id' => Sistema::factory()->de($otra)->create()->id,
    ]);
    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->post("/evidencias/{$evidencia->id}/requisitos", ['implantaciones' => [$ajena->id]])
        ->assertSessionHasErrors('implantaciones.0');

    expect($evidencia->implantaciones()->count())->toBe(0);
});

/*
| Renovar
*/

it('el formulario de renovación llega relleno con lo que no cambia', function (): void {
    $anterior = ($this->evidencia)([
        'titulo' => 'Configuración del segundo factor',
        'periodicidad_renovacion' => PeriodicidadRenovacion::Semestral->value,
        'fecha_caducidad' => '2026-10-12',
    ]);

    $this->actingAs($this->usuario)
        ->get("/evidencias/{$anterior->id}/renovar")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('evidencias/Formulario')
            ->where('renueva.id', $anterior->id)
            ->where('renueva.plantilla.titulo', 'Configuración del segundo factor')
            ->where('renueva.plantilla.periodicidad_renovacion', 'semestral')
            ->where('renueva.plantilla.fecha_obtencion', '2026-09-30')
            ->where('renueva.plantilla.fecha_caducidad', null)
        );
});

it('renovar da de alta otra con los mismos vínculos y apaga el aviso de la anterior', function (): void {
    Storage::fake('evidencias');

    $medida = ($this->implantacion)($this->ens, $this->sistemaEns, 'op.acc.5');
    $control = ($this->implantacion)($this->iso, $this->sistemaIso, 'A.8.5');

    $anterior = ($this->evidencia)(['fecha_caducidad' => '2026-10-12']);
    app(VincularEvidencia::class)->vincular($anterior, $medida, null, 'El factor del IdP.');
    app(VincularEvidencia::class)->vincular($anterior, $control, null, 'La política de MFA.');

    expect(Evidencia::query()->porCaducar()->count())->toBe(1);

    $this->actingAs($this->usuario)
        ->post("/evidencias/{$anterior->id}/renovacion", [
            'titulo' => $anterior->titulo,
            'tipo' => $anterior->tipo->value,
            'fichero' => UploadedFile::fake()->createWithContent('captura.png', 'contenido sintetico'),
            'fecha_obtencion' => '2026-09-30',
            'periodicidad_renovacion' => PeriodicidadRenovacion::Semestral->value,
        ])
        ->assertSessionHasNoErrors();

    $nueva = Evidencia::query()->whereKeyNot($anterior->id)->sole();
    $anterior->refresh();

    expect($anterior->renovada_por_id)->toBe($nueva->id)
        // La anterior se conserva con lo que probaba: el auditor puede preguntar por su periodo.
        ->and($anterior->implantaciones()->count())->toBe(2)
        ->and($nueva->implantaciones()->pluck('nota', 'implantaciones.id')->sortKeys()->all())->toBe([
            $medida->id => 'El factor del IdP.',
            $control->id => 'La política de MFA.',
        ])
        ->and($nueva->fecha_caducidad?->toDateString())->toBe('2027-03-30')
        // La vieja ya no avisa: ni por caducar hoy, ni caducada cuando llegue su fecha.
        ->and(Evidencia::query()->porCaducar()->count())->toBe(0);

    Carbon::setTestNow('2026-11-01');

    expect(Evidencia::query()->caducadas()->count())->toBe(0);
});

it('una evidencia se renueva una sola vez', function (): void {
    $sustituta = ($this->evidencia)();
    $anterior = ($this->evidencia)(['renovada_por_id' => $sustituta->id]);

    $this->actingAs($this->usuario)
        ->get("/evidencias/{$anterior->id}/renovar")
        ->assertRedirect("/evidencias/{$sustituta->id}");

    $this->actingAs($this->usuario)
        ->post("/evidencias/{$anterior->id}/renovacion", [
            'titulo' => 'Otra',
            'tipo' => $anterior->tipo->value,
            'url_externa' => 'https://interno.ejemplo/otra',
            'fecha_obtencion' => '2026-09-30',
        ])
        ->assertRedirect("/evidencias/{$sustituta->id}");

    expect(Evidencia::query()->count())->toBe(2);
});

it('la renovación exige su propia prueba, fichero o enlace', function (): void {
    $anterior = ($this->evidencia)();

    $this->actingAs($this->usuario)
        ->post("/evidencias/{$anterior->id}/renovacion", [
            'titulo' => $anterior->titulo,
            'tipo' => $anterior->tipo->value,
            'fecha_obtencion' => '2026-09-30',
        ])
        ->assertSessionHasErrors(['fichero', 'url_externa']);
});

it('la ficha de la renovada dice quién la sustituye', function (): void {
    $sustituta = ($this->evidencia)(['titulo' => 'La de este semestre']);
    $anterior = ($this->evidencia)(['renovada_por_id' => $sustituta->id]);

    $this->actingAs($this->usuario)
        ->get("/evidencias/{$anterior->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('vigencia.estado', 'renovada')
            ->where('renovadaPor.titulo', 'La de este semestre')
        );

    $this->actingAs($this->usuario)
        ->get("/evidencias/{$sustituta->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('renuevaA.id', $anterior->id));
});
