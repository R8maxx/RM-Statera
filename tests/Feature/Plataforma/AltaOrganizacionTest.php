<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\AltaOrganizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Excepciones\AltaNoPermitida;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\Notifications\InvitacionACuenta;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| El alta de una organización cliente (punto 41)
|--------------------------------------------------------------------------
|
| La única receta para que exista un tenant: la fila, los roles de su «team»
| y la invitación del primer responsable de seguridad. Lo demás lo hace el
| propio cliente.
|
*/

function administrador(): User
{
    $admin = User::factory()->plataforma()->create();
    $admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $admin;
}

beforeEach(function (): void {
    Notification::fake();
    Marco::factory()->create(['codigo' => 'ENS-RD311-2022']);
    $this->admin = administrador();
});

it('da de alta la organización con su responsable invitado', function (): void {
    $this->actingAs($this->admin)
        ->post('/plataforma/organizaciones', [
            'nombre' => 'Cliente Sintético',
            'razon_social' => 'Cliente Sintético, S.L.',
            'cif' => 'B11111111',
            'responsable_nombre' => 'Rosa Responsable',
            'responsable_email' => 'rosa@cliente.test',
        ])
        ->assertRedirect();

    $organizacion = Organizacion::query()->where('cif', 'B11111111')->firstOrFail();
    $responsable = User::query()->where('organizacion_id', $organizacion->id)->where('email', 'rosa@cliente.test')->firstOrFail();

    expect($responsable->estadoCuenta())->toBe(EstadoCuenta::Invitada)
        ->and($responsable->rol())->toBe(Rol::ResponsableSeguridad)
        ->and($responsable->esPlataforma())->toBeFalse();

    Notification::assertSentTo($responsable, InvitacionACuenta::class);
});

it('deja el alta en la traza de la plataforma y en la del tenant', function (): void {
    $organizacion = app(AltaOrganizacion::class)(['nombre' => 'Cliente'], 'Rosa', 'rosa@cliente.test');

    expect(EventoPlataforma::query()
        ->where('organizacion_afectada_id', $organizacion->id)
        ->where('accion', AccionPlataforma::OrganizacionAlta->value)
        ->exists())->toBeTrue();

    $eventos = app(ContextoOrganizacion::class)->paraOrganizacion(
        $organizacion,
        fn () => EventoAuditoria::query()->where('entidad', 'Organizacion')->where('entidad_id', $organizacion->id)->get(),
    );

    expect($eventos)->toHaveCount(1)
        ->and($eventos->first()?->accion->value)->toBe('creado');
});

it('no deja contexto puesto al terminar', function (): void {
    app(AltaOrganizacion::class)(['nombre' => 'Cliente'], 'Rosa', 'rosa@cliente.test');

    expect(app(ContextoOrganizacion::class)->hayContexto())->toBeFalse();
});

it('no da de alta nada sin el catálogo importado', function (): void {
    Marco::query()->delete();

    expect(fn () => app(AltaOrganizacion::class)(['nombre' => 'Cliente'], 'Rosa', 'rosa@cliente.test'))
        ->toThrow(AltaNoPermitida::class);

    expect(Organizacion::query()->count())->toBe(0);
});

it('rechaza un correo que ya tiene cuenta en cualquier organización', function (): void {
    $otra = comoOrganizacion();
    $existente = usuarioCon(Rol::Tecnico, $otra);
    sinOrganizacion();

    $this->actingAs($this->admin)
        ->post('/plataforma/organizaciones', [
            'nombre' => 'Cliente',
            'responsable_nombre' => 'Repetida',
            'responsable_email' => $existente->email,
        ])
        ->assertSessionHasErrors('responsable_email');
});

it('el responsable acepta la invitación y entra en su organización', function (): void {
    $organizacion = app(AltaOrganizacion::class)(['nombre' => 'Cliente'], 'Rosa', 'rosa@cliente.test');
    $responsable = User::query()->where('organizacion_id', $organizacion->id)->firstOrFail();

    $enlace = null;
    Notification::assertSentTo($responsable, InvitacionACuenta::class, function (InvitacionACuenta $aviso) use (&$enlace): bool {
        $enlace = (fn () => $this->enlace)->call($aviso);

        return true;
    });

    preg_match('#/invitacion/([^?]+)#', (string) $enlace, $token);

    $this->post('/invitacion', [
        'token' => $token[1],
        'email' => 'rosa@cliente.test',
        'password' => 'una-contrasena-larga-1',
        'password_confirmation' => 'una-contrasena-larga-1',
    ])->assertRedirect('/login');

    expect($responsable->fresh()?->estadoCuenta())->toBe(EstadoCuenta::Activa);

    $this->post('/login', ['email' => 'rosa@cliente.test', 'password' => 'una-contrasena-larga-1']);
    $this->get('/panel')->assertOk();
});
