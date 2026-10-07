<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\CambiarSuscripcion;
use App\Domain\Plataforma\Enums\EstadoSuscripcion;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\Models\TransicionSuscripcion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El plan y la suscripción (punto 43)
|--------------------------------------------------------------------------
|
| Modelados y sin cobrar. Lo que se comprueba es lo que el producto hace con
| ellos: los límites, el aviso en gracia y la sólo lectura, que nunca es
| perder nada.
|
*/

function adminDePlataforma(): User
{
    $admin = User::factory()->plataforma()->create();
    $admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $admin;
}

function conPlan(Organizacion $organizacion, Plan $plan, ?Carbon $venceEn): Organizacion
{
    app(CambiarSuscripcion::class)($organizacion, $plan, $venceEn);
    app(ContextoOrganizacion::class)->establecer($organizacion);

    return $organizacion->fresh() ?? $organizacion;
}

beforeEach(function (): void {
    Notification::fake();
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->marco = Marco::factory()->create(['codigo' => 'ENS-RD311-2022']);
});

it('sin plan la suscripción no vence', function (): void {
    expect($this->organizacion->estadoSuscripcion())->toBe(EstadoSuscripcion::Vigente);
});

it('pasa de vigente a gracia y de gracia a sólo lectura con el tiempo', function (): void {
    $plan = Plan::factory()->create(['dias_gracia' => 10]);
    $organizacion = conPlan($this->organizacion, $plan, Carbon::parse('2026-11-30 23:59:59'));

    expect(EstadoSuscripcion::de($organizacion, Carbon::parse('2026-11-30 12:00')))->toBe(EstadoSuscripcion::Vigente)
        ->and(EstadoSuscripcion::de($organizacion, Carbon::parse('2026-12-05 12:00')))->toBe(EstadoSuscripcion::EnGracia)
        ->and(EstadoSuscripcion::de($organizacion, Carbon::parse('2026-12-11 12:00')))->toBe(EstadoSuscripcion::SoloLectura);
});

it('en sólo lectura no se escribe, pero se lee', function (): void {
    $plan = Plan::factory()->create(['dias_gracia' => 0]);
    conPlan($this->organizacion, $plan, Carbon::now()->subDays(2));

    $this->actingAs($this->responsable)
        ->post('/sistemas', ['codigo' => 'SIS-1', 'nombre' => 'Uno', 'marco_id' => $this->marco->id, 'estado' => 'activo'])
        ->assertRedirect();

    expect(Sistema::query()->count())->toBe(0);

    $this->actingAs($this->responsable)->get('/sistemas')->assertOk();
});

it('en sólo lectura la cuenta propia se sigue protegiendo', function (): void {
    $plan = Plan::factory()->create(['dias_gracia' => 0]);
    conPlan($this->organizacion, $plan, Carbon::now()->subDays(2));

    $this->actingAs($this->responsable)
        ->put('/perfil/tema', ['tema' => 'oscuro'])
        ->assertSuccessful();

    expect($this->responsable->fresh()?->tema->value)->toBe('oscuro');
});

it('en gracia se escribe y se avisa', function (): void {
    $plan = Plan::factory()->create(['dias_gracia' => 15]);
    conPlan($this->organizacion, $plan, Carbon::now()->subDays(2));

    $this->actingAs($this->responsable)
        ->post('/sistemas', ['codigo' => 'SIS-1', 'nombre' => 'Uno', 'marco_id' => $this->marco->id, 'estado' => 'activo']);

    expect(Sistema::query()->count())->toBe(1);

    $this->actingAs($this->responsable)->get('/sistemas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('suscripcion.estado', 'en_gracia'));
});

it('vigente no manda aviso', function (): void {
    $this->actingAs($this->responsable)->get('/sistemas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('suscripcion', null));
});

it('respeta el límite de sistemas del plan', function (): void {
    conPlan($this->organizacion, Plan::factory()->conLimites(null, 1)->create(), null);
    Sistema::factory()->conMarco($this->marco)->create();

    $this->actingAs($this->responsable)
        ->post('/sistemas', ['codigo' => 'SIS-2', 'nombre' => 'Dos', 'marco_id' => $this->marco->id, 'estado' => 'activo'])
        ->assertSessionHasErrors('codigo');

    expect(Sistema::query()->count())->toBe(1);
});

it('respeta el límite de cuentas, sin contar al auditor externo', function (): void {
    conPlan($this->organizacion, Plan::factory()->conLimites(1)->create(), null);
    $sistema = Sistema::factory()->conMarco($this->marco)->create();

    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico'])
        ->assertSessionHasErrors('rol');

    $this->actingAs($this->responsable)
        ->post('/cuentas', [
            'name' => 'Aitor Auditor',
            'email' => 'aitor@ejemplo.test',
            'rol' => 'auditor',
            'sistemas' => [$sistema->id],
            'acceso_hasta' => today()->addMonth()->toDateString(),
        ])
        ->assertSessionHasNoErrors();
});

it('el cambio de plan deja histórico y traza en el tenant', function (): void {
    $plan = Plan::factory()->create();
    $admin = adminDePlataforma();
    sinOrganizacion();

    $this->actingAs($admin)
        ->put("/plataforma/organizaciones/{$this->organizacion->id}/suscripcion", [
            'plan_id' => $plan->id,
            'vence_en' => '2027-06-30',
            'motivo' => 'Contrato anual',
        ])
        ->assertRedirect();

    $transicion = TransicionSuscripcion::query()->where('organizacion_afectada_id', $this->organizacion->id)->sole();

    expect($transicion->plan_nuevo_id)->toBe($plan->id)
        ->and($transicion->usuario_id)->toBe($admin->id)
        ->and($transicion->motivo)->toBe('Contrato anual');

    $evento = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->organizacion,
        fn () => EventoAuditoria::query()->where('entidad', 'Organizacion')->latest('id')->first(),
    );

    expect($evento?->valor_nuevo)->toHaveKey('plan_id');
});

it('el histórico de suscripciones no se puede reescribir', function (): void {
    conPlan($this->organizacion, Plan::factory()->create(), null);

    expect(fn () => DB::table('transiciones_suscripcion')->update(['motivo' => 'otro']))
        ->toThrow(QueryException::class);
});

it('el cliente no puede cambiarse el plan desde su ficha', function (): void {
    $plan = Plan::factory()->create();

    $this->actingAs($this->responsable)
        ->put('/organizacion', [
            'nombre' => $this->organizacion->nombre,
            'plan_id' => $plan->id,
            'suscripcion_vence_en' => '2099-01-01',
        ]);

    expect($this->organizacion->fresh()?->plan_id)->toBeNull();
});

it('un cliente no entra en la gestión de planes', function (): void {
    $this->actingAs($this->responsable)->get('/plataforma/planes')->assertForbidden();
});

it('la plataforma crea un plan', function (): void {
    sinOrganizacion();

    $this->actingAs(adminDePlataforma())
        ->post('/plataforma/planes', [
            'codigo' => 'basica-5',
            'nombre' => 'Básica 5',
            'limite_cuentas' => 5,
            'dias_gracia' => 15,
            'activo' => '1',
        ])
        ->assertRedirect('/plataforma/planes');

    expect(Plan::query()->where('codigo', 'basica-5')->value('limite_cuentas'))->toBe(5);
});

it('la lista de clientes enseña el plan y el estado de cada uno', function (): void {
    conPlan($this->organizacion, Plan::factory()->create(['nombre' => 'Básica', 'dias_gracia' => 0]), Carbon::now()->subDay());
    sinOrganizacion();

    $this->actingAs(adminDePlataforma())->get('/plataforma/organizaciones')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.plan_nombre', 'Básica')
            ->where('filas.0.suscripcion.valor', 'solo_lectura'));
});
