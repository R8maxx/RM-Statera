<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\CambiarSuscripcion;
use App\Domain\Plataforma\LimitesDelPlan;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\Notifications\InvitacionACuenta;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Quien administra la plataforma y además trabaja en una organización (punto 45)
|--------------------------------------------------------------------------
|
| Una sola cuenta. En su organización trabaja con su rol, como cualquiera; en
| las demás sólo entra como soporte y sólo lee. No ocupa asiento, no puede ser
| auditor externo, y el cliente no puede dejarle fuera de la plataforma.
|
*/

/** Una cuenta de la organización activa, con su rol, que además administra la plataforma. */
function administradorMiembro(Rol $rol = Rol::ResponsableSeguridad): User
{
    $cuenta = usuarioCon($rol);
    $cuenta->forceFill(['es_plataforma' => true, 'perfil_plataforma' => 'administracion', 'two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $cuenta->fresh() ?? $cuenta;
}

beforeEach(function (): void {
    Notification::fake();
    $this->organizacion = comoOrganizacion();
    $this->marco = Marco::factory()->create(['codigo' => 'ENS-RD311-2022']);
});

it('en su organización trabaja con su rol, no en sólo lectura', function (): void {
    $admin = administradorMiembro(Rol::ResponsableSeguridad);
    sinOrganizacion();

    $this->actingAs($admin)
        ->post('/sistemas', ['codigo' => 'SIS-1', 'nombre' => 'Uno', 'marco_id' => $this->marco->id, 'estado' => 'activo'])
        ->assertRedirect('/sistemas');

    app(ContextoOrganizacion::class)->establecer($this->organizacion);
    expect(Sistema::query()->count())->toBe(1);
});

it('entra a su panel y ve también la plataforma', function (): void {
    $admin = administradorMiembro();
    sinOrganizacion();

    $this->actingAs($admin)->get('/inicio')->assertRedirect('/panel');

    $this->actingAs($admin)->get('/panel')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('soporte', null)
            ->where('organizacion.id', $this->organizacion->id)
            ->where('auth.permisos', fn ($permisos): bool => collect($permisos)->contains('plataforma.clientes.ver')
                && collect($permisos)->contains('sistemas.gestionar')));

    $this->actingAs($admin)->get('/plataforma/organizaciones')->assertOk();
});

it('en otra organización sólo entra como soporte, y en lectura', function (): void {
    $admin = administradorMiembro();
    $otra = Organizacion::factory()->create();
    app(ContextoOrganizacion::class)->paraOrganizacion(
        $otra,
        fn () => $otra->forceFill(['soporte_hasta' => now()->addDay()])->save(),
    );
    sinOrganizacion();

    $this->actingAs($admin)->post("/plataforma/organizaciones/{$otra->id}/soporte")->assertRedirect('/panel');

    $this->actingAs($admin)
        ->post('/sistemas', ['codigo' => 'SIS-9', 'nombre' => 'Ajeno', 'marco_id' => $this->marco->id, 'estado' => 'activo'])
        ->assertForbidden();

    $this->actingAs($admin)->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('organizacion.id', $otra->id)->whereNot('soporte', null));

    // Al salir vuelve a la suya, con su rol.
    $this->actingAs($admin)->post('/plataforma/soporte/salir');
    $this->actingAs($admin)
        ->post('/sistemas', ['codigo' => 'SIS-2', 'nombre' => 'Propio', 'marco_id' => $this->marco->id, 'estado' => 'activo'])
        ->assertRedirect('/sistemas');
});

it('no entra como soporte en su propia organización', function (): void {
    $admin = administradorMiembro();
    app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->organizacion,
        fn () => $this->organizacion->forceFill(['soporte_hasta' => now()->addDay()])->save(),
    );
    sinOrganizacion();

    $this->actingAs($admin)
        ->post("/plataforma/organizaciones/{$this->organizacion->id}/soporte")
        ->assertSessionHasErrors('soporte');
});

it('no ocupa asiento del plan', function (): void {
    administradorMiembro(Rol::Tecnico);
    usuarioCon(Rol::Tecnico);
    app(CambiarSuscripcion::class)($this->organizacion, Plan::factory()->conLimites(1)->create(), null);
    app(ContextoOrganizacion::class)->establecer($this->organizacion);

    expect(app(LimitesDelPlan::class)->cuentasOcupadas($this->organizacion->fresh() ?? $this->organizacion))->toBe(1);
});

it('un cliente no puede meter a un administrador invitando su correo', function (): void {
    $responsable = usuarioCon(Rol::ResponsableSeguridad);
    $admin = User::factory()->plataforma()->create(['email' => 'ada@statera.test']);

    $this->actingAs($responsable)
        ->post('/cuentas', ['name' => 'Ada', 'email' => 'ada@statera.test', 'rol' => 'tecnico'])
        ->assertSessionHasErrors(['email' => 'Ya hay una cuenta con ese correo en Statera.']);

    expect($admin->fresh()?->organizacion_id)->toBeNull();
});

it('la plataforma le une al dar de alta la organización, sin invitación', function (): void {
    $admin = User::factory()->plataforma()->create(['email' => 'ada@statera.test']);
    $admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();
    sinOrganizacion();

    $this->actingAs($admin)->post('/plataforma/organizaciones', [
        'nombre' => 'Cliente con administrador',
        'responsable_nombre' => 'Ada',
        'responsable_email' => 'ada@statera.test',
    ])->assertRedirect();

    $admin->refresh();
    $nueva = Organizacion::query()->where('nombre', 'Cliente con administrador')->firstOrFail();

    expect($admin->organizacion_id)->toBe($nueva->id)
        ->and($admin->rol())->toBe(Rol::ResponsableSeguridad)
        ->and($admin->estadoCuenta())->toBe(EstadoCuenta::Activa);

    Notification::assertNotSentTo($admin, InvitacionACuenta::class);
});

it('la consola le une a una organización con su rol', function (): void {
    $admin = User::factory()->plataforma()->create(['email' => 'ada@statera.test']);
    sinOrganizacion();

    $this->artisan('plataforma:administrador', [
        'email' => 'ada@statera.test',
        'nombre' => 'Ada',
        '--organizacion' => $this->organizacion->id,
        '--rol' => 'tecnico',
    ])->assertSuccessful();

    expect($admin->fresh()?->organizacion_id)->toBe($this->organizacion->id)
        ->and($admin->fresh()?->rol())->toBe(Rol::Tecnico);
});

it('no se le puede dar el rol de auditor externo', function (): void {
    User::factory()->plataforma()->create(['email' => 'ada@statera.test']);
    sinOrganizacion();

    $this->artisan('plataforma:administrador', [
        'email' => 'ada@statera.test',
        'nombre' => 'Ada',
        '--organizacion' => $this->organizacion->id,
        '--rol' => 'auditor',
    ])->assertFailed();

    app(ContextoOrganizacion::class)->establecer($this->organizacion);
    $responsable = usuarioCon(Rol::ResponsableSeguridad);
    $miembro = administradorMiembro(Rol::Tecnico);
    $sistema = Sistema::factory()->conMarco($this->marco)->create();

    $this->actingAs($responsable)
        ->put("/cuentas/{$miembro->id}", [
            'rol' => 'auditor',
            'sistemas' => [$sistema->id],
            'acceso_hasta' => today()->addMonth()->toDateString(),
        ])
        ->assertSessionHasErrors('rol');
});

it('el cliente le saca de la organización pero no le desactiva', function (): void {
    $responsable = usuarioCon(Rol::ResponsableSeguridad);
    $admin = administradorMiembro(Rol::Tecnico);

    $this->actingAs($responsable)
        ->post("/cuentas/{$admin->id}/desactivar", ['motivo' => 'Ya no trabaja aquí'])
        ->assertRedirect('/cuentas');

    $admin->refresh();

    expect($admin->organizacion_id)->toBeNull()
        ->and($admin->desactivada_en)->toBeNull()
        ->and($admin->esPlataforma())->toBeTrue();

    sinOrganizacion();
    $this->actingAs($admin)->get('/plataforma/organizaciones')->assertOk();
    $this->actingAs($admin)->get('/sistemas')->assertForbidden();
});

it('la plataforma reenvía la invitación de un cliente', function (): void {
    $invitada = User::factory()->invitada()->create(['organizacion_id' => $this->organizacion->id]);
    $otra = Organizacion::factory()->create();
    sinOrganizacion();
    $admin = User::factory()->plataforma()->create();
    $admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    // Acotada a la organización de la ruta: desde otra, no existe.
    $this->actingAs($admin)
        ->post("/plataforma/organizaciones/{$otra->id}/cuentas/{$invitada->id}/reenviar")
        ->assertNotFound();

    $this->actingAs($admin)
        ->post("/plataforma/organizaciones/{$this->organizacion->id}/cuentas/{$invitada->id}/reenviar")
        ->assertRedirect("/plataforma/organizaciones/{$this->organizacion->id}");

    Notification::assertSentTo($invitada, InvitacionACuenta::class);
});
