<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Plataforma\AdministradoresDePlataforma;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Domain\Plataforma\Excepciones\AdministracionNoPermitida;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Usuario\Notifications\InvitacionACuenta;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Los administradores, desde la web (punto 49)
|--------------------------------------------------------------------------
|
| Invitar, cambiar el perfil y retirar, siempre con la contraseña de quien lo
| hace. Nadie sobre sí mismo, y la plataforma nunca sin Administración.
|
*/

function administradorCon(PerfilPlataforma $perfil = PerfilPlataforma::Administracion): User
{
    $cuenta = User::factory()->plataforma()->create(['perfil_plataforma' => $perfil->value]);
    $cuenta->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $cuenta;
}

beforeEach(function (): void {
    Notification::fake();
    $this->yo = administradorCon();
});

it('lista a quienes administran, y sólo a ellos', function (): void {
    administradorCon(PerfilPlataforma::Comercial);
    comoOrganizacion();
    usuarioCon(Rol::ResponsableSeguridad);
    sinOrganizacion();

    $this->actingAs($this->yo)->get('/plataforma/administradores')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->has('administradores', 2));
});

it('invita con perfil y con la contraseña de quien invita', function (): void {
    $this->actingAs($this->yo)->post('/plataforma/administradores', [
        'name' => 'Carla', 'email' => 'carla@statera.test', 'perfil' => 'comercial', 'password' => 'mala',
    ])->assertSessionHasErrors('password');

    $this->actingAs($this->yo)->post('/plataforma/administradores', [
        'name' => 'Carla', 'email' => 'carla@statera.test', 'perfil' => 'comercial', 'password' => 'password',
    ])->assertRedirect('/plataforma/administradores');

    $carla = User::query()->whereNull('organizacion_id')->where('email', 'carla@statera.test')->firstOrFail();

    expect($carla->perfil_plataforma)->toBe(PerfilPlataforma::Comercial);
    Notification::assertSentTo($carla, InvitacionACuenta::class);
});

it('cambia el perfil de otro y lo deja en la traza', function (): void {
    $otro = administradorCon(PerfilPlataforma::Comercial);

    $this->actingAs($this->yo)
        ->put("/plataforma/administradores/{$otro->id}/perfil", ['perfil' => 'administracion', 'password' => 'password'])
        ->assertRedirect('/plataforma/administradores');

    expect($otro->fresh()?->perfil_plataforma)->toBe(PerfilPlataforma::Administracion)
        ->and(EventoPlataforma::query()->where('accion', AccionPlataforma::AdministradorPerfilCambiado->value)->exists())->toBeTrue();
});

it('nadie se cambia ni se retira a sí mismo', function (): void {
    administradorCon();

    $this->actingAs($this->yo)
        ->put("/plataforma/administradores/{$this->yo->id}/perfil", ['perfil' => 'comercial', 'password' => 'password'])
        ->assertSessionHasErrors('administrador');

    $this->actingAs($this->yo)
        ->post("/plataforma/administradores/{$this->yo->id}/retirar", ['password' => 'password'])
        ->assertSessionHasErrors('administrador');
});

it('gestión comercial no gestiona administradores', function (): void {
    $this->actingAs(administradorCon(PerfilPlataforma::Comercial))
        ->get('/plataforma/administradores')
        ->assertForbidden();
});

it('no deja sin Administración: una desactivada no cuenta', function (): void {
    $desactivada = administradorCon();
    $desactivada->forceFill(['desactivada_en' => now()])->save();
    $comercial = administradorCon(PerfilPlataforma::Comercial);

    // Soy la única de Administración activa: nadie puede retirarme ni bajarme.
    $administradores = app(AdministradoresDePlataforma::class);

    expect(fn () => $administradores->retirar($comercial, $this->yo))
        ->toThrow(AdministracionNoPermitida::class);

    expect(fn () => $administradores->cambiarPerfil($comercial, $this->yo, PerfilPlataforma::Comercial))
        ->toThrow(AdministracionNoPermitida::class);

    // Con otra activa, sí.
    $segunda = administradorCon();

    $this->actingAs($segunda)
        ->post("/plataforma/administradores/{$this->yo->id}/retirar", ['password' => 'password'])
        ->assertRedirect('/plataforma/administradores');

    expect($this->yo->fresh()?->esPlataforma())->toBeFalse();
});

it('retirar a quien no es de ninguna organización desactiva su cuenta', function (): void {
    $otro = administradorCon(PerfilPlataforma::Comercial);

    $this->actingAs($this->yo)
        ->post("/plataforma/administradores/{$otro->id}/retirar", ['password' => 'password'])
        ->assertRedirect();

    $otro->refresh();

    expect($otro->esPlataforma())->toBeFalse()
        ->and($otro->perfil_plataforma)->toBeNull()
        ->and($otro->desactivada_en)->not->toBeNull();
});

it('retirar a quien es además de una organización le deja como usuario suyo', function (): void {
    $organizacion = comoOrganizacion();
    $miembro = usuarioCon(Rol::Tecnico);
    $miembro->forceFill(['es_plataforma' => true, 'perfil_plataforma' => 'comercial'])->save();
    sinOrganizacion();

    $this->actingAs($this->yo)
        ->post("/plataforma/administradores/{$miembro->id}/retirar", ['password' => 'password'])
        ->assertRedirect();

    $miembro->refresh();

    expect($miembro->esPlataforma())->toBeFalse()
        ->and($miembro->desactivada_en)->toBeNull()
        ->and($miembro->organizacion_id)->toBe($organizacion->id);
});

it('una cuenta de cliente no se gestiona desde aquí', function (): void {
    comoOrganizacion();
    $cliente = usuarioCon(Rol::Tecnico);
    sinOrganizacion();

    $this->actingAs($this->yo)
        ->put("/plataforma/administradores/{$cliente->id}/perfil", ['perfil' => 'comercial', 'password' => 'password'])
        ->assertNotFound();
});
