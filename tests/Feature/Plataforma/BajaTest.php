<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| La baja de una organización (punto 46)
|--------------------------------------------------------------------------
|
| Un estado que se deshace, nunca un borrado: nadie suyo entra, la plataforma
| no entra como soporte, y todo lo que tiene se conserva.
|
*/

function adminDeBaja(): User
{
    $admin = User::factory()->plataforma()->create();
    $admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $admin;
}

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->responsable->forceFill(['password' => 'una-contrasena-larga-1'])->save();
    Sistema::factory()->create();
    sinOrganizacion();
    $this->admin = adminDeBaja();
});

function darDeBaja(object $test): void
{
    $test->actingAs($test->admin)
        ->post("/plataforma/organizaciones/{$test->organizacion->id}/baja", ['motivo' => 'Fin del contrato'])
        ->assertRedirect();
}

it('exige un motivo', function (): void {
    $this->actingAs($this->admin)
        ->post("/plataforma/organizaciones/{$this->organizacion->id}/baja", ['motivo' => ''])
        ->assertSessionHasErrors('motivo');
});

it('deja la organización de baja sin borrar nada, y en las dos trazas', function (): void {
    darDeBaja($this);

    $organizacion = $this->organizacion->fresh();

    expect($organizacion?->estaDeBaja())->toBeTrue()
        ->and($organizacion?->activa)->toBeFalse()
        ->and($organizacion?->motivo_baja)->toBe('Fin del contrato')
        ->and(EventoPlataforma::query()->where('accion', AccionPlataforma::OrganizacionBaja->value)->exists())->toBeTrue();

    $sistemas = app(ContextoOrganizacion::class)->paraOrganizacion($this->organizacion, fn () => Sistema::query()->count());
    expect($sistemas)->toBe(1);
});

it('nadie de la organización entra', function (): void {
    darDeBaja($this);
    auth()->logout();

    $this->post('/login', ['email' => $this->responsable->email, 'password' => 'una-contrasena-larga-1'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('a quien ya estaba dentro le saca en la siguiente petición', function (): void {
    darDeBaja($this);

    $this->actingAs($this->responsable)->get('/panel')->assertRedirect('/login');
});

it('cierra el acceso de soporte y no deja abrir otro', function (): void {
    app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->organizacion,
        fn () => $this->organizacion->forceFill(['soporte_hasta' => now()->addDay()])->save(),
    );
    sinOrganizacion();

    darDeBaja($this);

    expect($this->organizacion->fresh()?->soporte_hasta)->toBeNull();

    $this->actingAs($this->admin)
        ->post("/plataforma/organizaciones/{$this->organizacion->id}/soporte")
        ->assertSessionHasErrors('soporte');
});

it('el administrador que además es de ella sigue entrando a la plataforma', function (): void {
    $miembro = adminDeBaja();
    $miembro->forceFill(['organizacion_id' => $this->organizacion->id])->save();

    darDeBaja($this);

    $this->actingAs($miembro)->get('/plataforma/organizaciones')->assertOk();
    $this->actingAs($miembro)->get('/sistemas')->assertForbidden();
});

it('se reactiva y todo vuelve', function (): void {
    darDeBaja($this);

    $this->actingAs($this->admin)
        ->post("/plataforma/organizaciones/{$this->organizacion->id}/reactivar")
        ->assertRedirect();

    expect($this->organizacion->fresh()?->estaDeBaja())->toBeFalse();

    $this->actingAs($this->responsable)->get('/panel')->assertOk();
});

it('un cliente no puede dar de baja organizaciones', function (): void {
    $this->actingAs($this->responsable)
        ->post("/plataforma/organizaciones/{$this->organizacion->id}/baja", ['motivo' => 'x'])
        ->assertForbidden();
});
