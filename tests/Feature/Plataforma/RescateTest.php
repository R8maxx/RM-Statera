<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\EstadoSolicitud;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Domain\Plataforma\Excepciones\RescateNoPermitido;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Plataforma\Models\SolicitudPlataforma;
use App\Domain\Plataforma\Notifications\AvisoDeRescate;
use App\Domain\Plataforma\Rescate\ResolverRescate;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\Notifications\InvitacionACuenta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Rescatar cuentas con dos personas (punto 52)
|--------------------------------------------------------------------------
|
| Restablecer el segundo factor o designar un nuevo responsable toca la llave
| de una cuenta ajena: lo pide una persona, con la verificación escrita, y lo
| ejecuta otra. Todo queda en las dos trazas y el cliente recibe un correo.
|
*/

const VERIFICACION = 'Llamada al teléfono de la ficha, confirmada con su director general.';

function rescatador(PerfilPlataforma $perfil = PerfilPlataforma::Administracion): User
{
    $cuenta = User::factory()->plataforma()->create(['perfil_plataforma' => $perfil->value]);
    $cuenta->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $cuenta;
}

beforeEach(function (): void {
    Notification::fake();
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->tecnico = usuarioCon(Rol::Tecnico);
    $this->tecnico->forceFill(['two_factor_secret' => 'secreto', 'two_factor_recovery_codes' => 'codigos', 'two_factor_confirmed_at' => now()])->save();
    DB::table('passkeys')->insert(['user_id' => $this->tecnico->id, 'name' => 'Llave', 'credential_id' => 'abc', 'credential' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    sinOrganizacion();

    $this->pide = rescatador();
    $this->ejecuta = rescatador();
});

function pedirSegundoFactor(object $test): SolicitudPlataforma
{
    $test->actingAs($test->pide)
        ->post("/plataforma/organizaciones/{$test->organizacion->id}/rescates", [
            'tipo' => 'restablecer_segundo_factor',
            'cuenta_id' => $test->tecnico->id,
            'verificacion' => VERIFICACION,
        ])
        ->assertRedirect();

    return SolicitudPlataforma::query()->latest('id')->firstOrFail();
}

it('pedir no cambia nada en la cuenta', function (): void {
    $solicitud = pedirSegundoFactor($this);

    expect($solicitud->estado())->toBe(EstadoSolicitud::Pendiente)
        ->and($this->tecnico->fresh()?->two_factor_secret)->not->toBeNull();
});

it('exige una verificación que diga algo', function (): void {
    $this->actingAs($this->pide)
        ->post("/plataforma/organizaciones/{$this->organizacion->id}/rescates", [
            'tipo' => 'restablecer_segundo_factor',
            'cuenta_id' => $this->tecnico->id,
            'verificacion' => 'ok',
        ])
        ->assertSessionHasErrors('verificacion');
});

it('quien pide no ejecuta si hay otra persona de Administración', function (): void {
    $solicitud = pedirSegundoFactor($this);

    $this->actingAs($this->pide)->post("/plataforma/solicitudes/{$solicitud->id}/ejecutar")
        ->assertSessionHasErrors('solicitud');

    expect($solicitud->fresh()?->estado())->toBe(EstadoSolicitud::Pendiente);
});

it('otra persona ejecuta: borra el segundo factor, las passkeys y avisa', function (): void {
    $solicitud = pedirSegundoFactor($this);

    $this->actingAs($this->ejecuta)->post("/plataforma/solicitudes/{$solicitud->id}/ejecutar")
        ->assertRedirect('/plataforma/solicitudes');

    $tecnico = $this->tecnico->fresh();

    expect($tecnico?->two_factor_secret)->toBeNull()
        ->and($tecnico?->two_factor_recovery_codes)->toBeNull()
        ->and($tecnico?->two_factor_confirmed_at)->toBeNull()
        ->and(DB::table('passkeys')->where('user_id', $this->tecnico->id)->count())->toBe(0)
        ->and($solicitud->fresh()?->estado())->toBe(EstadoSolicitud::Ejecutada)
        ->and($solicitud->fresh()?->resuelta_por)->toBe($this->ejecuta->id);

    Notification::assertSentTo($this->tecnico, AvisoDeRescate::class);
    Notification::assertSentTo($this->responsable, AvisoDeRescate::class);

    $enElCliente = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->organizacion,
        fn () => EventoAuditoria::query()->where('entidad', 'User')->where('entidad_id', $this->tecnico->id)->latest('id')->first(),
    );
    expect($enElCliente?->valor_nuevo['segundo_factor'] ?? null)->toBe('restablecido por la plataforma')
        ->and(EventoPlataforma::query()->where('accion', AccionPlataforma::RescateEjecutado->value)->exists())->toBeTrue();
});

it('con una sola persona de Administración se ejecuta, y queda dicho', function (): void {
    $this->ejecuta->forceFill(['desactivada_en' => now()])->save();
    $solicitud = pedirSegundoFactor($this);

    $this->actingAs($this->pide)->post("/plataforma/solicitudes/{$solicitud->id}/ejecutar")
        ->assertRedirect('/plataforma/solicitudes');

    expect($solicitud->fresh()?->sin_segunda_persona)->toBeTrue();
});

it('«Ejecutar» sólo se ofrece a quien puede ejecutarla', function (): void {
    pedirSegundoFactor($this);

    $this->actingAs($this->pide)->get('/plataforma/solicitudes')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('solicitudes.0.pedidaPorMi', true)
            ->where('solicitudes.0.puedoEjecutar', false));

    $this->actingAs($this->ejecuta)->get('/plataforma/solicitudes')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('solicitudes.0.puedoEjecutar', true));

    // Sin otra persona de Administración, quien la pidió sí puede.
    $this->ejecuta->forceFill(['desactivada_en' => now()])->save();
    $this->travel(1)->minutes();
    $sola = pedirSegundoFactor($this);

    $this->actingAs($this->pide)->get('/plataforma/solicitudes')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('solicitudes.0.id', $sola->id)
            ->where('solicitudes.0.puedoEjecutar', true));
});

it('una solicitud caduca a las 72 horas', function (): void {
    $solicitud = pedirSegundoFactor($this);
    $this->travel(73)->hours();

    expect($solicitud->fresh()?->estado())->toBe(EstadoSolicitud::Caducada);

    $this->actingAs($this->ejecuta)->post("/plataforma/solicitudes/{$solicitud->id}/ejecutar")
        ->assertSessionHasErrors('solicitud');
});

it('rechazar no toca la cuenta y guarda el motivo', function (): void {
    $solicitud = pedirSegundoFactor($this);

    $this->actingAs($this->ejecuta)->post("/plataforma/solicitudes/{$solicitud->id}/rechazar", ['motivo' => 'No contesta nadie en el teléfono de la ficha.'])
        ->assertRedirect();

    expect($solicitud->fresh()?->estado())->toBe(EstadoSolicitud::Rechazada)
        ->and($this->tecnico->fresh()?->two_factor_secret)->not->toBeNull();
});

it('designa como responsable a una cuenta que ya tiene', function (): void {
    $this->actingAs($this->pide)->post("/plataforma/organizaciones/{$this->organizacion->id}/rescates", [
        'tipo' => 'designar_responsable',
        'cuenta_id' => $this->tecnico->id,
        'verificacion' => VERIFICACION,
    ]);
    $solicitud = SolicitudPlataforma::query()->latest('id')->firstOrFail();

    $this->actingAs($this->ejecuta)->post("/plataforma/solicitudes/{$solicitud->id}/ejecutar");

    expect($this->tecnico->fresh()?->rol())->toBe(Rol::ResponsableSeguridad);
});

it('designa a una persona nueva invitándola', function (): void {
    $this->actingAs($this->pide)->post("/plataforma/organizaciones/{$this->organizacion->id}/rescates", [
        'tipo' => 'designar_responsable',
        'nombre' => 'Nueva Responsable',
        'email' => 'nueva@cliente.test',
        'verificacion' => VERIFICACION,
    ])->assertSessionHasNoErrors();
    $solicitud = SolicitudPlataforma::query()->latest('id')->firstOrFail();

    $this->actingAs($this->ejecuta)->post("/plataforma/solicitudes/{$solicitud->id}/ejecutar");

    $nueva = User::query()->where('organizacion_id', $this->organizacion->id)->where('email', 'nueva@cliente.test')->firstOrFail();

    expect($nueva->rol())->toBe(Rol::ResponsableSeguridad)
        ->and($nueva->estadoCuenta())->toBe(EstadoCuenta::Invitada);
    Notification::assertSentTo($nueva, InvitacionACuenta::class);
});

it('no se rescata la cuenta de un administrador ni la de otro cliente', function (): void {
    app(ContextoOrganizacion::class)->establecer($this->organizacion);
    $miembro = usuarioCon(Rol::Tecnico);
    $miembro->forceFill(['es_plataforma' => true, 'perfil_plataforma' => 'comercial'])->save();
    $otra = comoOrganizacion();
    $ajeno = usuarioCon(Rol::Tecnico, $otra);
    sinOrganizacion();

    foreach ([$miembro, $ajeno] as $cuenta) {
        $this->actingAs($this->pide)->post("/plataforma/organizaciones/{$this->organizacion->id}/rescates", [
            'tipo' => 'restablecer_segundo_factor',
            'cuenta_id' => $cuenta->id,
            'verificacion' => VERIFICACION,
        ])->assertSessionHasErrors('cuenta_id');
    }
});

it('gestión comercial no pide ni resuelve rescates', function (): void {
    $comercial = rescatador(PerfilPlataforma::Comercial);

    $this->actingAs($comercial)->get('/plataforma/solicitudes')->assertForbidden();
    $this->actingAs($comercial)->post("/plataforma/organizaciones/{$this->organizacion->id}/rescates", [
        'tipo' => 'restablecer_segundo_factor',
        'cuenta_id' => $this->tecnico->id,
        'verificacion' => VERIFICACION,
    ])->assertForbidden();
});

it('retirar a la otra persona después de pedir no permite ejecutarla a solas', function (): void {
    $solicitud = pedirSegundoFactor($this);

    // Quien pidió deja sola su perfil de Administración y lo intenta.
    $this->ejecuta->forceFill(['desactivada_en' => now()])->save();

    $this->actingAs($this->pide)->post("/plataforma/solicitudes/{$solicitud->id}/ejecutar")
        ->assertSessionHasErrors('solicitud');

    expect($this->tecnico->fresh()?->two_factor_secret)->not->toBeNull();
});

it('una solicitud ya resuelta no se vuelve a ejecutar', function (): void {
    $solicitud = pedirSegundoFactor($this);

    $this->actingAs($this->ejecuta)->post("/plataforma/solicitudes/{$solicitud->id}/ejecutar");

    // La misma instancia, cargada antes de que se ejecutara: es lo que vería
    // una segunda petición que llegó a la vez.
    expect(fn () => app(ResolverRescate::class)->ejecutar($this->ejecuta, $solicitud))
        ->toThrow(RescateNoPermitido::class);
});
