<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Persona\Models\Persona;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\Notifications\InvitacionACuenta;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| El alta de una cuenta por invitación (§ 4.19)
|--------------------------------------------------------------------------
|
| Sin registro self-service: la única puerta es que un responsable de
| seguridad invite a alguien. Nadie escribe la contraseña de otro; la
| invitación lleva un enlace para fijarla, y hasta entonces la cuenta no entra.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    Notification::fake();
});

/** El enlace que llegó en el correo, como lo pulsaría quien lo recibe. */
function enlaceDeInvitacion(User $cuenta): string
{
    $enlace = null;

    Notification::assertSentTo($cuenta, InvitacionACuenta::class, function (InvitacionACuenta $aviso) use (&$enlace, $cuenta): bool {
        $enlace = (fn () => $this->enlace)->call($aviso);

        return str_contains((string) $enlace, urlencode($cuenta->email));
    });

    return (string) $enlace;
}

it('invita una cuenta con su rol y le manda el enlace', function (): void {
    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana Técnica', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico'])
        ->assertRedirect();

    $cuenta = User::query()->where('organizacion_id', $this->organizacion->id)->where('email', 'ana@ejemplo.test')->firstOrFail();

    expect($cuenta->estadoCuenta())->toBe(EstadoCuenta::Invitada)
        ->and($cuenta->rol())->toBe(Rol::Tecnico)
        ->and($cuenta->organizacion_id)->toBe($this->organizacion->id);

    expect(enlaceDeInvitacion($cuenta))->toContain('/invitacion/');
});

it('queda en la traza quién invitó a quién y con qué rol', function (): void {
    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico']);

    $cuenta = User::query()->where('organizacion_id', $this->organizacion->id)->where('email', 'ana@ejemplo.test')->firstOrFail();

    $evento = EventoAuditoria::query()->where('entidad', 'User')->where('entidad_id', $cuenta->id)->firstOrFail();

    expect($evento->accion->value)->toBe('creado')
        ->and($evento->usuario_id)->toBe($this->responsable->id)
        ->and($evento->valor_nuevo['rol'])->toBe('tecnico');
});

it('una cuenta invitada no entra con ninguna contraseña hasta aceptar', function (): void {
    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico']);

    auth()->logout();

    $this->post('/login', ['email' => 'ana@ejemplo.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('aceptar la invitación fija la contraseña y deja la cuenta activa', function (): void {
    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico']);

    $cuenta = User::query()->where('organizacion_id', $this->organizacion->id)->where('email', 'ana@ejemplo.test')->firstOrFail();
    $token = basename((string) parse_url(enlaceDeInvitacion($cuenta), PHP_URL_PATH));

    auth()->logout();
    sinOrganizacion();

    $this->get("/invitacion/{$token}?email=ana@ejemplo.test")->assertOk();

    $this->post('/invitacion', [
        'token' => $token,
        'email' => 'ana@ejemplo.test',
        'password' => 'una-contraseña-larga',
        'password_confirmation' => 'una-contraseña-larga',
    ])->assertRedirect('/login');

    expect($cuenta->fresh()?->estadoCuenta())->toBe(EstadoCuenta::Activa);

    $this->post('/login', ['email' => 'ana@ejemplo.test', 'password' => 'una-contraseña-larga']);
    $this->assertAuthenticatedAs($cuenta->fresh());
});

it('el enlace sirve una sola vez', function (): void {
    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico']);

    $cuenta = User::query()->where('organizacion_id', $this->organizacion->id)->where('email', 'ana@ejemplo.test')->firstOrFail();
    $token = basename((string) parse_url(enlaceDeInvitacion($cuenta), PHP_URL_PATH));
    auth()->logout();

    $datos = ['token' => $token, 'email' => 'ana@ejemplo.test', 'password' => 'una-contraseña-larga', 'password_confirmation' => 'una-contraseña-larga'];

    $this->post('/invitacion', $datos)->assertRedirect('/login');
    $this->post('/invitacion', $datos)->assertSessionHasErrors('email');
});

it('un token de restablecer contraseña no acepta una invitación', function (): void {
    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico']);

    $cuenta = User::query()->where('organizacion_id', $this->organizacion->id)->where('email', 'ana@ejemplo.test')->firstOrFail();
    $token = Password::broker('users')->createToken($cuenta);
    auth()->logout();

    $this->post('/invitacion', [
        'token' => $token, 'email' => 'ana@ejemplo.test',
        'password' => 'una-contraseña-larga', 'password_confirmation' => 'una-contraseña-larga',
    ])->assertSessionHasErrors('email');

    expect($cuenta->fresh()?->estadoCuenta())->toBe(EstadoCuenta::Invitada);
});

it('reenviar invalida el enlace anterior', function (): void {
    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico']);

    $cuenta = User::query()->where('organizacion_id', $this->organizacion->id)->where('email', 'ana@ejemplo.test')->firstOrFail();
    $primero = basename((string) parse_url(enlaceDeInvitacion($cuenta), PHP_URL_PATH));

    Notification::fake();
    $this->actingAs($this->responsable)->post("/cuentas/{$cuenta->id}/reenviar")->assertRedirect();
    auth()->logout();

    $this->post('/invitacion', [
        'token' => $primero, 'email' => 'ana@ejemplo.test',
        'password' => 'una-contraseña-larga', 'password_confirmation' => 'una-contraseña-larga',
    ])->assertSessionHasErrors('email');
});

it('el correo es único en todo Statera y no sólo en la organización', function (): void {
    $otra = comoOrganizacion();
    usuarioCon(Rol::Tecnico, $otra)->forceFill(['email' => 'repetido@ejemplo.test'])->save();
    comoOrganizacion($this->organizacion);

    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'repetido@ejemplo.test', 'rol' => 'tecnico'])
        ->assertSessionHasErrors('email');
});

it('un auditor se invita con sus sistemas y su fecha de fin, o no se invita', function (): void {
    $sistema = Sistema::factory()->create();

    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Auditora', 'email' => 'aud@ejemplo.test', 'rol' => 'auditor'])
        ->assertSessionHasErrors(['sistemas', 'acceso_hasta']);

    $this->actingAs($this->responsable)
        ->post('/cuentas', [
            'name' => 'Auditora', 'email' => 'aud@ejemplo.test', 'rol' => 'auditor',
            'sistemas' => [$sistema->id], 'acceso_hasta' => today()->addMonth()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    $cuenta = User::query()->where('organizacion_id', $this->organizacion->id)->where('email', 'aud@ejemplo.test')->firstOrFail();

    expect($cuenta->alcance()->pluck('sistema_id')->all())->toBe([$sistema->id])
        ->and($cuenta->acceso_hasta?->toDateString())->toBe(today()->addMonth()->toDateString());
});

it('un técnico no lleva ni sistemas ni fecha de fin', function (): void {
    $sistema = Sistema::factory()->create();

    $this->actingAs($this->responsable)
        ->post('/cuentas', [
            'name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico',
            'sistemas' => [$sistema->id], 'acceso_hasta' => today()->addMonth()->toDateString(),
        ])
        ->assertSessionHasErrors(['sistemas', 'acceso_hasta']);
});

it('enlaza la cuenta con una persona libre y no con una ya enlazada', function (): void {
    $libre = Persona::factory()->create();
    $ocupada = Persona::factory()->create(['user_id' => $this->responsable->id]);

    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico', 'persona_id' => $ocupada->id])
        ->assertSessionHasErrors('persona_id');

    $this->actingAs($this->responsable)
        ->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico', 'persona_id' => $libre->id])
        ->assertSessionHasNoErrors();

    expect($libre->fresh()?->user_id)->not->toBeNull();
});

it('sólo el responsable de seguridad gestiona cuentas', function (Rol $rol): void {
    $otro = usuarioCon($rol);

    $this->actingAs($otro)->get('/cuentas')->assertForbidden();
    $this->actingAs($otro)->post('/cuentas', ['name' => 'X', 'email' => 'x@ejemplo.test', 'rol' => 'tecnico'])->assertForbidden();
})->with([Rol::Tecnico, Rol::Auditor]);

it('la lista y la ficha se pintan', function (): void {
    $this->actingAs($this->responsable)->get('/cuentas')->assertOk();
    $this->actingAs($this->responsable)->get('/cuentas/crear')->assertOk();
    $this->actingAs($this->responsable)->get("/cuentas/{$this->responsable->id}")->assertOk();
    $this->actingAs($this->responsable)->get("/cuentas/{$this->responsable->id}/editar")->assertOk();
});
