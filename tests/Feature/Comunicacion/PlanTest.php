<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Aviso\CalendarioVencimientos;
use App\Domain\Aviso\Fuente;
use App\Domain\Comunicacion\Enums\SentidoComunicacion;
use App\Domain\Comunicacion\Excepciones\ComunicacionInvalida;
use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use App\Domain\Comunicacion\RegistrarComunicacion;
use App\Domain\Comunicacion\RegistroComunicacion;
use App\Domain\Contexto\AnalisisEnCurso;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Panel\AlertasDelPanel;
use App\Http\Resources\Panel\Indicador;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El plan de comunicación (cláusula 7.4)
|--------------------------------------------------------------------------
|
| Lo que se fija aquí es la regla de los compromisos del § 4.16 aplicada a otra
| cosa: la próxima fecha **se deriva**, `cubre_hasta` **se congela** al
| registrar, y lo que no tiene cadencia no vence nunca. Más el «a quién», que
| sólo admite partes interesadas vigentes de la organización.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->registrar = app(RegistrarComunicacion::class);
});

it('lista el plan con su alerta y sus pendientes', function (): void {
    ComunicacionPrevista::factory()->count(2)->create();

    $this->actingAs($this->usuario)
        ->get('/plan-comunicacion')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('plan-comunicacion/Index')
            ->where('total', 2)
            ->has('alertas', 1)
            ->has('pendientes', 3)
            ->has('filas', 2));
});

it('propone el código siguiente, sin año', function (): void {
    ComunicacionPrevista::factory()->create(['codigo' => 'PC-01']);
    ComunicacionPrevista::factory()->create(['codigo' => 'PC-07']);

    $this->actingAs($this->usuario)
        ->get('/plan-comunicacion/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('sugerencia.codigo', 'PC-08'));
});

it('a quién sólo admite partes interesadas vigentes de la organización', function (): void {
    $vigente = ParteInteresada::factory()->create(['nombre' => 'Dirección']);
    $retirada = ParteInteresada::factory()->retirada(app(AnalisisEnCurso::class)->borradorObligatorio())->create();

    $ajena = app(ContextoOrganizacion::class)->paraOrganizacion(
        Organizacion::factory()->create(),
        fn (): ParteInteresada => ParteInteresada::factory()->create(),
    );

    $this->actingAs($this->usuario)
        ->post('/plan-comunicacion', [
            'codigo' => 'PC-01',
            'titulo' => 'Informe trimestral de seguridad',
            'canal' => 'reunion',
            'periodicidad_meses' => 3,
            'computa_desde' => Carbon::today()->toDateString(),
            'partes_interesadas' => [$vigente->id, $retirada->id],
        ])
        ->assertRedirect();

    $prevista = ComunicacionPrevista::query()->where('codigo', 'PC-01')->sole();

    expect($prevista->partesInteresadas()->pluck('partes_interesadas.id')->all())->toBe([$vigente->id]);

    // La ajena ni siquiera pasa la validación: no existe para esta organización.
    $this->actingAs($this->usuario)
        ->post('/plan-comunicacion', [
            'codigo' => 'PC-02',
            'titulo' => 'Otro',
            'canal' => 'correo',
            'partes_interesadas' => [$ajena->id],
        ])
        ->assertSessionHasErrors('partes_interesadas.0');
});

it('con cadencia hace falta desde cuándo cuenta, y sin ella se descarta', function (): void {
    $this->actingAs($this->usuario)
        ->post('/plan-comunicacion', [
            'codigo' => 'PC-01',
            'titulo' => 'Informe',
            'canal' => 'correo',
            'periodicidad_meses' => 6,
        ])
        ->assertSessionHasErrors('computa_desde');

    $this->actingAs($this->usuario)
        ->post('/plan-comunicacion', [
            'codigo' => 'PC-02',
            'titulo' => 'Aviso al cambiar la política',
            'canal' => 'intranet',
            'periodicidad_meses' => '__ninguno__',
            'computa_desde' => Carbon::today()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    $prevista = ComunicacionPrevista::query()->where('codigo', 'PC-02')->sole();

    expect($prevista->periodicidad_meses)->toBeNull()
        ->and($prevista->computa_desde)->toBeNull()
        ->and($prevista->proximaFecha())->toBeNull()
        ->and($prevista->vencida())->toBeFalse();
});

it('registrar lo comunicado congela hasta cuándo cubre y mueve la próxima', function (): void {
    $prevista = ComunicacionPrevista::factory()->cada(3, Carbon::today()->subMonths(4))->create();

    expect($prevista->vencida())->toBeTrue();

    $this->actingAs($this->usuario)
        ->post("/plan-comunicacion/{$prevista->id}/comunicaciones", [
            'sentido' => 'emitida',
            'fecha' => Carbon::today()->toDateString(),
            'asunto' => 'Informe del tercer trimestre',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $hecha = $prevista->comunicaciones()->sole();

    expect($hecha->cubre_hasta?->toDateString())->toBe(Carbon::today()->addMonthsNoOverflow(3)->toDateString())
        // Sin canal en el formulario, el de la línea del plan.
        ->and($hecha->canal)->toBe($prevista->canal)
        ->and($prevista->refresh()->vencida())->toBeFalse()
        ->and(ComunicacionPrevista::query()->vencidas()->count())->toBe(0);
});

it('cambiar la cadencia no repinta lo que ya estaba cubierto', function (): void {
    $prevista = ComunicacionPrevista::factory()->cada(12, Carbon::today()->subYear())->create();

    ($this->registrar)(SentidoComunicacion::Emitida, Carbon::today()->subMonths(4), ['asunto' => 'Aviso anual'], $this->usuario, $prevista);

    $prevista->update(['periodicidad_meses' => 3]);

    // Con trimestral desde hace cuatro meses estaría vencida; lo cubierto era anual.
    expect($prevista->refresh()->vencida())->toBeFalse()
        ->and($prevista->proximaFecha()?->toDateString())->toBe(Carbon::today()->subMonths(4)->addMonthsNoOverflow(12)->toDateString());
});

it('una comunicación es un hecho: la fecha no puede ser futura', function (): void {
    $prevista = ComunicacionPrevista::factory()->cada(3)->create();

    expect(fn () => ($this->registrar)(SentidoComunicacion::Emitida, Carbon::tomorrow(), ['asunto' => 'x'], $this->usuario, $prevista))
        ->toThrow(ComunicacionInvalida::class, 'todavía no ha pasado');

    $this->actingAs($this->usuario)
        ->post("/plan-comunicacion/{$prevista->id}/comunicaciones", [
            'sentido' => 'emitida',
            'fecha' => Carbon::tomorrow()->toDateString(),
            'asunto' => 'Futuro',
        ])
        ->assertSessionHasErrors('fecha');
});

it('retirar exige motivo, deja de vencer y no admite que se comunique contra ella', function (): void {
    $prevista = ComunicacionPrevista::factory()->cada(3, Carbon::today()->subMonths(4))->create();

    $this->actingAs($this->usuario)
        ->post("/plan-comunicacion/{$prevista->id}/retirar", [])
        ->assertSessionHasErrors('motivo_retirada');

    $this->actingAs($this->usuario)
        ->post("/plan-comunicacion/{$prevista->id}/retirar", ['motivo_retirada' => 'Se sustituye por el panel mensual.'])
        ->assertRedirect();

    $prevista->refresh();

    expect($prevista->estaRetirada())->toBeTrue()
        ->and($prevista->vencida())->toBeFalse()
        ->and(ComunicacionPrevista::query()->vencidas()->count())->toBe(0);

    expect(fn () => ($this->registrar)(SentidoComunicacion::Emitida, Carbon::today(), ['asunto' => 'x'], $this->usuario, $prevista))
        ->toThrow(ComunicacionInvalida::class, 'retirada');
});

it('la vencida es el único rojo, sube al panel y cae en «La organización»', function (): void {
    ComunicacionPrevista::factory()->cada(3, Carbon::today()->subMonths(4))->create();
    ComunicacionPrevista::factory()->create();

    $alertas = collect(app(AlertasDelPanel::class)($this->usuario));

    expect($alertas->firstWhere('base', '/plan-comunicacion')?->valor)->toBe(1)
        ->and(AlertasDelPanel::vistaDe('/plan-comunicacion'))->toBe('organizacion');

    foreach (app(RegistroComunicacion::class)->pendientes() as $pendiente) {
        expect($pendiente->tono)->not->toBe('caducada');
    }
});

it('cada indicador del plan cuenta lo mismo que su filtro', function (): void {
    ComunicacionPrevista::factory()->cada(3, Carbon::today()->subMonths(4))->create();
    ComunicacionPrevista::factory()->cada(1, Carbon::today()->subDays(20))->create(['responsable_id' => $this->usuario->id]);
    ComunicacionPrevista::factory()->create();

    $registro = app(RegistroComunicacion::class);

    foreach ([...$registro->alertas(), ...$registro->pendientes()] as $indicador) {
        /** @var Indicador $indicador */
        $this->actingAs($this->usuario)
            ->get("/plan-comunicacion?{$indicador->filtro}")
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', $indicador->valor));
    }
});

it('la periódica entra en el calendario y la que no tiene cadencia no', function (): void {
    $periodica = ComunicacionPrevista::factory()->cada(3, Carbon::today()->subMonths(2))->create();
    ComunicacionPrevista::factory()->create();

    $vencimientos = collect(app(CalendarioVencimientos::class)->entre(Carbon::today(), Carbon::today()->addMonths(2)))
        ->filter(fn ($vencimiento): bool => $vencimiento->fuente === Fuente::Comunicacion);

    expect($vencimientos->pluck('id')->all())->toBe([$periodica->id])
        ->and(Fuente::Comunicacion->url($periodica->id))->toBe("/plan-comunicacion/{$periodica->id}");
});

it('el técnico lleva el plan entero y el auditor sólo lo lee', function (): void {
    $prevista = ComunicacionPrevista::factory()->cada(3)->create();

    $tecnico = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnico)
        ->post("/plan-comunicacion/{$prevista->id}/comunicaciones", [
            'sentido' => 'emitida',
            'fecha' => Carbon::today()->toDateString(),
            'asunto' => 'Informe',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)->get('/plan-comunicacion')->assertOk();
    $this->actingAs($auditor)->get("/plan-comunicacion/{$prevista->id}")->assertOk();
    $this->actingAs($auditor)->get('/plan-comunicacion/crear')->assertForbidden();
    $this->actingAs($auditor)->post("/plan-comunicacion/{$prevista->id}/retirar", ['motivo_retirada' => 'x'])->assertForbidden();
});

it('no lista ni deja abrir el plan de otra organización', function (): void {
    ComunicacionPrevista::factory()->create(['codigo' => 'PC-PROPIA']);

    $suya = app(ContextoOrganizacion::class)->paraOrganizacion(
        Organizacion::factory()->create(),
        fn (): ComunicacionPrevista => ComunicacionPrevista::factory()->create(['codigo' => 'PC-AJENA']),
    );

    expect(ComunicacionPrevista::query()->pluck('codigo')->all())->toBe(['PC-PROPIA']);

    $this->actingAs($this->usuario)->get("/plan-comunicacion/{$suya->id}")->assertNotFound();
    $this->actingAs($this->usuario)
        ->post("/plan-comunicacion/{$suya->id}/comunicaciones", ['sentido' => 'emitida', 'fecha' => Carbon::today()->toDateString(), 'asunto' => 'x'])
        ->assertNotFound();
});
