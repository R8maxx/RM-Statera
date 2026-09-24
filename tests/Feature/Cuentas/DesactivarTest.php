<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\Models\CuentaSistema;

/*
|--------------------------------------------------------------------------
| Desactivar y caducar (§ 4.19)
|--------------------------------------------------------------------------
|
| Desactivar no borra: la cuenta sigue siendo autora de lo que hizo. Lo que
| cambia es que ya no entra, ni por la puerta del login ni por una sesión que
| ya estaba abierta. Caducar es lo mismo por fecha, sin que nadie tenga que
| acordarse.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
});

it('una cuenta desactivada no entra aunque la contraseña sea la buena', function (): void {
    $cuenta = usuarioCon(Rol::Tecnico);

    $this->actingAs($this->responsable)
        ->post("/cuentas/{$cuenta->id}/desactivar", ['motivo' => 'Baja en la empresa'])
        ->assertSessionHasNoErrors();

    expect($cuenta->fresh()?->estadoCuenta())->toBe(EstadoCuenta::Desactivada)
        ->and($cuenta->fresh()?->motivo_desactivacion)->toBe('Baja en la empresa');

    auth()->logout();

    $this->post('/login', ['email' => $cuenta->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Esta cuenta ya no tiene acceso a Statera.']);

    $this->assertGuest();
});

it('con la contraseña mala no dice si la cuenta está desactivada', function (): void {
    $cuenta = usuarioCon(Rol::Tecnico);
    $cuenta->forceFill(['desactivada_en' => now()])->save();

    // El mismo mensaje que para cualquier contraseña mala: decir «esta cuenta
    // está desactivada» a quien prueba correos al azar es decirle cuáles existen.
    $this->post('/login', ['email' => $cuenta->email, 'password' => 'otra'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
});

it('la sesión que ya estaba abierta se cierra en la siguiente petición', function (): void {
    $cuenta = usuarioCon(Rol::Tecnico);

    $this->actingAs($cuenta)->get('/panel')->assertOk();

    $cuenta->forceFill(['desactivada_en' => now()])->save();

    $this->actingAs($cuenta->fresh())->get('/panel')->assertRedirect('/login');
    $this->assertGuest();
});

it('no se desactiva la propia cuenta ni la del último responsable', function (): void {
    $this->actingAs($this->responsable)
        ->post("/cuentas/{$this->responsable->id}/desactivar")
        ->assertSessionHasErrors('cuenta');

    expect($this->responsable->fresh()?->desactivada_en)->toBeNull();
});

it('reactivar le devuelve la entrada', function (): void {
    $cuenta = usuarioCon(Rol::Tecnico);
    $cuenta->forceFill(['desactivada_en' => now(), 'motivo_desactivacion' => 'Error'])->save();

    $this->actingAs($this->responsable)->post("/cuentas/{$cuenta->id}/reactivar")->assertRedirect();

    expect($cuenta->fresh()?->estadoCuenta())->toBe(EstadoCuenta::Activa)
        ->and($cuenta->fresh()?->motivo_desactivacion)->toBeNull();
});

it('un auditor caduca solo al pasar su fecha de fin', function (): void {
    $sistema = Sistema::factory()->create();
    $auditor = usuarioCon(Rol::Auditor);
    CuentaSistema::query()->create(['user_id' => $auditor->id, 'sistema_id' => $sistema->id]);
    $auditor->forceFill(['acceso_hasta' => today()->addDays(2)])->save();

    $this->actingAs($auditor)->get('/panel')->assertOk();

    // El último día todavía entra: la fecha es inclusiva.
    $this->travelTo(today()->addDays(2)->setTime(23, 0));
    $this->actingAs($auditor->fresh())->get('/panel')->assertOk();

    $this->travelTo(today()->addDay()->setTime(0, 5));
    $this->actingAs($auditor->fresh())->get('/panel')->assertRedirect('/login');

    expect($auditor->fresh()?->estadoCuenta())->toBe(EstadoCuenta::Caducada);
});
