<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Traza\Models\EventoAuditoria;

/*
|--------------------------------------------------------------------------
| El registro de sesiones y el bloqueo por inactividad (§ 6)
|--------------------------------------------------------------------------
|
| Quién entró, quién salió y quién lo intentó, en la misma traza inmutable que
| el resto. Y una sesión quieta demasiado rato se cierra sola.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->cuenta = usuarioCon(Rol::Tecnico);
    sinOrganizacion();
});

/** @return list<string> */
function accionesDeSesion(int $cuentaId, Organizacion $organizacion): array
{
    comoOrganizacion($organizacion);

    return EventoAuditoria::query()
        ->where('entidad', 'User')
        ->where('entidad_id', $cuentaId)
        ->orderBy('id')
        ->pluck('accion')
        ->map(fn ($accion): string => $accion->value)
        ->all();
}

it('registra la entrada, la salida y el último acceso', function (): void {
    $this->post('/login', ['email' => $this->cuenta->email, 'password' => 'password']);
    $this->assertAuthenticated();

    $this->post('/logout');
    $this->assertGuest();

    expect(accionesDeSesion($this->cuenta->id, $this->organizacion))->toBe(['inicio_sesion', 'cierre_sesion'])
        ->and($this->cuenta->fresh()?->ultimo_acceso_en)->not->toBeNull();
});

it('registra el intento fallido de una cuenta que existe', function (): void {
    $this->post('/login', ['email' => $this->cuenta->email, 'password' => 'mala']);

    expect(accionesDeSesion($this->cuenta->id, $this->organizacion))->toBe(['intento_fallido']);
});

it('un correo que no es de nadie no deja rastro en ninguna organización', function (): void {
    $this->post('/login', ['email' => 'nadie@ejemplo.test', 'password' => 'mala']);

    comoOrganizacion($this->organizacion);
    expect(EventoAuditoria::query()->where('accion', 'intento_fallido')->count())->toBe(0);
});

it('la traza de la entrada es de la organización de la cuenta y de ninguna otra', function (): void {
    $otra = Organizacion::factory()->create();

    $this->post('/login', ['email' => $this->cuenta->email, 'password' => 'password']);

    comoOrganizacion($otra);
    expect(EventoAuditoria::query()->count())->toBe(0);
});

it('cierra la sesión tras el rato sin actividad', function (): void {
    config(['seguridad.inactividad_minutos' => 30]);

    $this->actingAs($this->cuenta)->get('/panel')->assertOk();

    $this->travel(29)->minutes();
    $this->actingAs($this->cuenta)->get('/panel')->assertOk();

    // Cuenta desde la última petición, no desde la primera.
    $this->travel(29)->minutes();
    $this->actingAs($this->cuenta)->get('/panel')->assertOk();

    $this->travel(31)->minutes();
    $this->get('/panel')->assertRedirect('/login');
    $this->assertGuest();
});

it('con cero minutos el bloqueo está apagado', function (): void {
    config(['seguridad.inactividad_minutos' => 0]);

    $this->actingAs($this->cuenta)->get('/panel')->assertOk();
    $this->travel(8)->hours();
    $this->get('/panel')->assertOk();
});
