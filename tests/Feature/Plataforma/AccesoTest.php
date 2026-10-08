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
use Inertia\Testing\AssertableInertia;

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
    $this->actingAs($admin)->get('/inicio')->assertRedirect('/plataforma');
});

it('en la lista de organizaciones, el nombre abre la ficha', function (): void {
    $organizacion = comoOrganizacion();
    sinOrganizacion();

    $this->actingAs(adminConDosFactores())->get('/plataforma/organizaciones')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.nombre.etiqueta', $organizacion->nombre)
            ->where('filas.0.nombre.url', "/plataforma/organizaciones/{$organizacion->id}"));
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

it('crea al administrador desde la consola con su invitación', function (): void {
    Notification::fake();

    $this->artisan('plataforma:administrador', ['email' => 'admin@statera.test', 'nombre' => 'Ada Admin'])
        ->assertSuccessful();

    $admin = User::query()->whereNull('organizacion_id')->where('email', 'admin@statera.test')->firstOrFail();

    expect($admin->esPlataforma())->toBeTrue();
    Notification::assertSentTo($admin, InvitacionACuenta::class);

    expect(EventoPlataforma::query()->where('accion', AccionPlataforma::AdministradorCreado->value)->exists())->toBeTrue();
});

it('la consola promueve una cuenta de cliente en vez de crear otra', function (): void {
    $organizacion = comoOrganizacion();
    $cuenta = usuarioCon(Rol::Tecnico);
    sinOrganizacion();

    $this->artisan('plataforma:administrador', ['email' => $cuenta->email, 'nombre' => 'Repetido'])
        ->assertSuccessful();

    $cuenta->refresh();

    expect($cuenta->esPlataforma())->toBeTrue()
        ->and($cuenta->organizacion_id)->toBe($organizacion->id)
        ->and($cuenta->rol())->toBe(Rol::Tecnico)
        ->and(User::query()->where('email', $cuenta->email)->whereNotNull('organizacion_id')->count())->toBe(1);
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

it('la raíz lleva a cada cuenta a su inicio, también al administrador', function (): void {
    $this->actingAs(adminConDosFactores())->get('/')->assertRedirect('/inicio');
    $this->actingAs(adminConDosFactores())->get('/inicio')->assertRedirect('/plataforma');
});
