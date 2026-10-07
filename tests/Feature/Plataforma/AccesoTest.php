<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Plataforma\AltaAdministrador;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Usuario\Notifications\InvitacionACuenta;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Quién entra en la plataforma, y qué no ve (punto 41)
|--------------------------------------------------------------------------
*/

function adminConDosFactores(): User
{
    $admin = User::factory()->plataforma()->create();
    $admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $admin;
}

it('una cuenta de organización no entra en la plataforma, sea cual sea su rol', function (Rol $rol): void {
    comoOrganizacion();
    $cuenta = usuarioCon($rol);
    sinOrganizacion();

    $this->actingAs($cuenta)->get('/plataforma/organizaciones')->assertForbidden();
})->with([Rol::ResponsableSeguridad, Rol::Tecnico, Rol::Auditor]);

it('el administrador entra en la plataforma y aterriza en ella', function (): void {
    $admin = adminConDosFactores();

    $this->actingAs($admin)->get('/plataforma/organizaciones')->assertOk();
    $this->actingAs($admin)->get('/inicio')->assertRedirect('/plataforma/organizaciones');
});

it('sin segundo factor la plataforma manda al perfil, también para leer', function (): void {
    config(['seguridad.exigir_dos_factores' => true]);
    $admin = User::factory()->plataforma()->create();

    $this->actingAs($admin)->get('/plataforma/organizaciones')->assertRedirect('/perfil');
});

it('el administrador no ve ningún dato de un tenant', function (): void {
    $organizacion = comoOrganizacion();
    Sistema::factory()->create();
    sinOrganizacion();

    $admin = adminConDosFactores();

    $this->actingAs($admin)->get('/sistemas')->assertForbidden();
    $this->actingAs($admin)->get('/panel')->assertForbidden();

    expect(Sistema::query()->count())->toBe(0)
        ->and($organizacion->exists)->toBeTrue();
});

it('la base impide que una cuenta sea de plataforma y de una organización', function (): void {
    $organizacion = comoOrganizacion();
    $cuenta = usuarioCon(Rol::Tecnico);

    expect(fn () => DB::table('users')->where('id', $cuenta->id)->update(['es_plataforma' => true]))
        ->toThrow(QueryException::class);

    expect($organizacion->id)->toBeInt();
});

it('crea al administrador desde la consola con su invitación', function (): void {
    Notification::fake();

    $this->artisan('plataforma:administrador', ['email' => 'admin@statera.test', 'nombre' => 'Ada Admin'])
        ->assertSuccessful();

    $admin = User::query()->whereNull('organizacion_id')->where('email', 'admin@statera.test')->firstOrFail();

    expect($admin->esPlataforma())->toBeTrue();
    Notification::assertSentTo($admin, InvitacionACuenta::class);

    expect(EventoPlataforma::query()->where('accion', AccionPlataforma::AdministradorCreado->value)->exists())->toBeTrue();
});

it('la consola no crea un administrador con un correo ya usado', function (): void {
    comoOrganizacion();
    $cuenta = usuarioCon(Rol::Tecnico);
    sinOrganizacion();

    $this->artisan('plataforma:administrador', ['email' => $cuenta->email, 'nombre' => 'Repetido'])
        ->assertFailed();
});

it('la entrada del administrador queda en la traza de la plataforma', function (): void {
    Notification::fake();
    app(AltaAdministrador::class)('Ada', 'ada@statera.test');
    $admin = User::query()->whereNull('organizacion_id')->where('email', 'ada@statera.test')->firstOrFail();
    $admin->forceFill(['activada_en' => now(), 'password' => 'una-contrasena-larga-1'])->save();

    $this->post('/login', ['email' => 'ada@statera.test', 'password' => 'una-contrasena-larga-1']);

    expect(EventoPlataforma::query()
        ->where('usuario_id', $admin->id)
        ->where('accion', AccionPlataforma::InicioSesion->value)
        ->exists())->toBeTrue();
});

it('la traza de la plataforma no se puede reescribir', function (): void {
    Notification::fake();
    app(AltaAdministrador::class)('Ada', 'ada@statera.test');

    expect(fn () => DB::table('eventos_plataforma')->update(['accion' => 'inicio_sesion']))
        ->toThrow(QueryException::class);
});

it('el administrador abre su perfil aunque no tenga organización', function (): void {
    $this->actingAs(adminConDosFactores())->get('/perfil')->assertOk();
});
