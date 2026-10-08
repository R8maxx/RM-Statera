<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\EstadoSuscripcion;
use App\Domain\Plataforma\Enums\OrigenCambioSuscripcion;
use App\Domain\Plataforma\Enums\PeriodoFacturacion;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\Models\TransicionSuscripcion;
use App\Domain\Plataforma\PresupuestarCambioPlan;
use App\Domain\Plataforma\PresupuestoCambioPlan;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La organización contrata su plan (punto 51)
|--------------------------------------------------------------------------
|
| Lo que se fija es el dinero —el prorrateo, con sus dos casos—, lo que no se
| puede —bajar a un plan donde no cabe lo que se usa, contratar el que asigna
| sólo la plataforma— y que el cambio deja rastro en los tres sitios de
| siempre, ahora diciendo que lo hizo la organización.
|
| El reloj se fija el 8 de octubre de 2026: con una suscripción anual que
| vence el 19 de marzo de 2027 quedan 163 días de 365.
|
*/

/** La suscripción puesta a mano, sin pasar por ninguna de las dos puertas. */
function suscribir(Organizacion $organizacion, Plan $plan, PeriodoFacturacion $periodo, string $inicia, string $vence): Organizacion
{
    $organizacion->forceFill([
        'plan_id' => $plan->id,
        'suscripcion_periodo' => $periodo,
        'suscripcion_inicia_en' => Carbon::parse($inicia),
        'suscripcion_vence_en' => Carbon::parse($vence)->endOfDay(),
    ])->save();

    return $organizacion->fresh(['plan']) ?? $organizacion;
}

/** @return array{cuentas: int, sistemas: int} */
function usoMinimo(): array
{
    return ['cuentas' => 1, 'sistemas' => 0];
}

beforeEach(function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-10-08 10:00:00');

    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->basica = Plan::factory()->contratable(4900)->conLimites(5, 1)->create(['nombre' => 'Básica', 'dias_gracia' => 15]);
    $this->profesional = Plan::factory()->contratable(12900)->conLimites(25, 5)->create(['nombre' => 'Profesional', 'dias_gracia' => 30]);
    $this->ilimitado = Plan::factory()->create(['nombre' => 'Ilimitado']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('subir a mitad de periodo cobra la diferencia de los días que quedan y conserva la renovación', function (): void {
    $organizacion = suscribir($this->organizacion, $this->basica, PeriodoFacturacion::Anual, '2026-03-19', '2027-03-19');

    $presupuesto = app(PresupuestarCambioPlan::class)($organizacion, $this->profesional, PeriodoFacturacion::Anual, usoMinimo());

    // (154 800 − 58 800) × 163 / 365
    expect($presupuesto->permitido())->toBeTrue()
        ->and($presupuesto->periodoNuevo)->toBeFalse()
        ->and($presupuesto->diasRestantes)->toBe(163)
        ->and($presupuesto->ajusteCentimos)->toBe(42871)
        ->and($presupuesto->importeHoyCentimos())->toBe(42871)
        ->and($presupuesto->venceEn->toDateString())->toBe('2027-03-19')
        ->and($presupuesto->siguienteCobroCentimos)->toBe(154800);
});

it('bajar deja saldo a favor y no cobra nada hoy', function (): void {
    $organizacion = suscribir($this->organizacion, $this->profesional, PeriodoFacturacion::Anual, '2026-03-19', '2027-03-19');

    $presupuesto = app(PresupuestarCambioPlan::class)($organizacion, $this->basica, PeriodoFacturacion::Anual, usoMinimo());

    expect($presupuesto->ajusteCentimos)->toBe(-42871)
        ->and($presupuesto->importeHoyCentimos())->toBe(0)
        ->and($presupuesto->saldoAFavorCentimos())->toBe(42871);
});

it('cambiar de periodo empieza uno nuevo hoy y descuenta lo que quedaba', function (): void {
    $organizacion = suscribir($this->organizacion, $this->basica, PeriodoFacturacion::Anual, '2026-03-19', '2027-03-19');

    $presupuesto = app(PresupuestarCambioPlan::class)($organizacion, $this->basica, PeriodoFacturacion::Mensual, usoMinimo());

    // 4 900 − 58 800 × 163 / 365
    expect($presupuesto->periodoNuevo)->toBeTrue()
        ->and($presupuesto->ajusteCentimos)->toBe(4900 - 26259)
        ->and($presupuesto->venceEn->toDateString())->toBe('2026-11-08');
});

it('el descuento anual se aplica al año y no al mes', function (): void {
    $plan = Plan::factory()->contratable(10000, 20)->create();

    expect($plan->precioDelPeriodo(PeriodoFacturacion::Mensual))->toBe(10000)
        ->and($plan->precioDelPeriodo(PeriodoFacturacion::Anual))->toBe(96000)
        ->and($plan->precioMensualEn(PeriodoFacturacion::Anual))->toBe(8000);
});

it('con la suscripción vencida se renueva desde hoy y se paga el periodo entero', function (): void {
    $organizacion = suscribir($this->organizacion, $this->basica, PeriodoFacturacion::Anual, '2025-08-01', '2026-08-01');

    expect($organizacion->estadoSuscripcion())->toBe(EstadoSuscripcion::SoloLectura);

    $presupuesto = app(PresupuestarCambioPlan::class)($organizacion, $this->basica, PeriodoFacturacion::Anual, usoMinimo());

    expect($presupuesto->permitido())->toBeTrue()
        ->and($presupuesto->periodoNuevo)->toBeTrue()
        ->and($presupuesto->ajusteCentimos)->toBe(58800)
        ->and($presupuesto->venceEn->toDateString())->toBe('2027-10-08');
});

it('el plan y el periodo que ya se tienen no se vuelven a contratar', function (): void {
    $organizacion = suscribir($this->organizacion, $this->basica, PeriodoFacturacion::Anual, '2026-03-19', '2027-03-19');

    $presupuesto = app(PresupuestarCambioPlan::class)($organizacion, $this->basica, PeriodoFacturacion::Anual, usoMinimo());

    expect($presupuesto->bloqueo)->toBe(PresupuestoCambioPlan::ES_EL_ACTUAL);
});

it('contratar cambia el plan y lo deja en el histórico, en la plataforma y en el tenant', function (): void {
    suscribir($this->organizacion, $this->basica, PeriodoFacturacion::Anual, '2026-03-19', '2027-03-19');

    $this->actingAs($this->responsable)
        ->post('/organizacion/plan', ['plan_id' => $this->profesional->id, 'periodo' => 'anual'])
        ->assertRedirect('/organizacion')
        ->assertSessionHasNoErrors();

    $organizacion = $this->organizacion->fresh();
    $transicion = TransicionSuscripcion::query()->where('organizacion_afectada_id', $organizacion?->id)->latest('id')->first();

    expect($organizacion?->plan_id)->toBe($this->profesional->id)
        ->and($organizacion?->suscripcion_vence_en?->toDateString())->toBe('2027-03-19')
        ->and($transicion?->origen)->toBe(OrigenCambioSuscripcion::Organizacion)
        ->and($transicion?->usuario_id)->toBe($this->responsable->id)
        ->and($transicion?->plan_anterior_id)->toBe($this->basica->id)
        ->and($transicion?->periodo_nuevo)->toBe(PeriodoFacturacion::Anual)
        ->and($transicion?->importe_centimos)->toBe(42871)
        ->and(EventoPlataforma::query()->where('accion', AccionPlataforma::PlanContratado->value)->where('organizacion_afectada_id', $organizacion?->id)->exists())->toBeTrue();

    $evento = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->organizacion,
        fn () => EventoAuditoria::query()->where('entidad', 'Organizacion')->latest('id')->first(),
    );

    expect($evento?->valor_nuevo)->toHaveKey('plan_id');
});

it('no se baja a un plan donde no cabe lo que se usa', function (): void {
    suscribir($this->organizacion, $this->profesional, PeriodoFacturacion::Anual, '2026-03-19', '2027-03-19');
    $pequeno = Plan::factory()->contratable(1900)->conLimites(1, 1)->create();
    usuarioCon(Rol::Tecnico);

    $this->actingAs($this->responsable)
        ->post('/organizacion/plan', ['plan_id' => $pequeno->id, 'periodo' => 'anual'])
        ->assertSessionHasErrors(['plan_id' => 'Lo que usáis no cabe en el plan '.$pequeno->nombre.': os sobra 1 cuenta. Dadlo de baja antes de cambiar.']);

    expect($this->organizacion->fresh()?->plan_id)->toBe($this->profesional->id);
});

it('el plan que asigna sólo la plataforma no se contrata', function (): void {
    $this->actingAs($this->responsable)
        ->post('/organizacion/plan', ['plan_id' => $this->ilimitado->id, 'periodo' => 'anual'])
        ->assertSessionHasErrors('plan_id');

    expect($this->organizacion->fresh()?->plan_id)->toBeNull();
});

it('en sólo lectura se puede renovar, y la organización vuelve a escribir', function (): void {
    suscribir($this->organizacion, $this->basica, PeriodoFacturacion::Anual, '2025-08-01', '2026-08-01');

    $this->actingAs($this->responsable)
        ->post('/organizacion/plan', ['plan_id' => $this->basica->id, 'periodo' => 'anual'])
        ->assertRedirect('/organizacion');

    expect($this->organizacion->fresh()?->estadoSuscripcion())->toBe(EstadoSuscripcion::Vigente);
});

it('sólo el responsable de seguridad cambia el plan', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnico)->get('/organizacion/plan')->assertForbidden();
    $this->actingAs($tecnico)
        ->post('/organizacion/plan', ['plan_id' => $this->basica->id, 'periodo' => 'anual'])
        ->assertForbidden();

    expect($this->organizacion->fresh()?->plan_id)->toBeNull();
});

it('la página de planes trae los contratables con su presupuesto y aparte los reservados', function (): void {
    $this->actingAs($this->responsable)->get('/organizacion/plan')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('organizacion/Planes')
            ->has('planes', 2)
            ->where('planes.0.nombre', 'Básica')
            ->where('planes.0.presupuestos.anual.siguienteCobroCentimos', 58800)
            ->where('planes.0.presupuestos.mensual.importeHoyCentimos', 4900)
            ->has('reservados', 1)
            ->where('reservados.0.nombre', 'Ilimitado')
            ->where('uso.cuentas', 1));
});

it('la ficha enseña la suscripción con su consumo y su histórico', function (): void {
    suscribir($this->organizacion, $this->basica, PeriodoFacturacion::Anual, '2026-03-19', '2027-03-19');
    $this->actingAs($this->responsable)->post('/organizacion/plan', ['plan_id' => $this->profesional->id, 'periodo' => 'anual']);

    $this->actingAs($this->responsable)->get('/organizacion')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('contrato.plan', 'Profesional')
            ->where('contrato.estado.valor', 'vigente')
            ->where('contrato.dias', 162)
            ->where('contrato.uso.cuentas', 1)
            ->where('contrato.limiteCuentas', 25)
            ->where('contrato.puedeContratar', true)
            ->where('contrato.historico.0.que', 'Cambio de plan: Básica → Profesional, anual')
            ->where('contrato.historico.0.quien', $this->responsable->name));
});

it('un plan contratable sin precio no se puede guardar', function (): void {
    expect(fn () => Plan::factory()->create(['contratable' => true, 'precio_mensual_centimos' => null]))
        ->toThrow(QueryException::class);
});

it('la plataforma pone precio con coma y lo guarda en céntimos', function (): void {
    sinOrganizacion();
    $admin = User::factory()->plataforma()->create();
    $admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    $this->actingAs($admin)
        ->post('/plataforma/planes', [
            'codigo' => 'media',
            'nombre' => 'Media',
            'dias_gracia' => 15,
            'activo' => '1',
            'precio_mensual' => '49,90',
            'descuento_anual' => 15,
            'contratable' => '1',
        ])
        ->assertRedirect('/plataforma/planes');

    $plan = Plan::query()->where('codigo', 'media')->sole();

    expect($plan->precio_mensual_centimos)->toBe(4990)
        ->and($plan->descuento_anual)->toBe(15)
        ->and($plan->contratable)->toBeTrue();

    $this->actingAs($admin)
        ->post('/plataforma/planes', ['codigo' => 'sin-precio', 'nombre' => 'Sin precio', 'dias_gracia' => 15, 'contratable' => '1'])
        ->assertSessionHasErrors('precio_mensual');
});
