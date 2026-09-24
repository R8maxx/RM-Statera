<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Domain\Usuario\CambiarRol;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;

/*
|--------------------------------------------------------------------------
| Cambiar el rol de una cuenta (§ 4.19)
|--------------------------------------------------------------------------
|
| La única regla que no puede romperse: ninguna organización se queda sin un
| responsable de seguridad que entre. Se impide, no se avisa.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
});

it('cambia el rol y lo deja en la traza con su propio verbo', function (): void {
    $cuenta = usuarioCon(Rol::Tecnico);

    $this->actingAs($this->responsable)
        ->put("/cuentas/{$cuenta->id}", ['rol' => 'responsable_seguridad'])
        ->assertSessionHasNoErrors();

    expect($cuenta->fresh()?->rol())->toBe(Rol::ResponsableSeguridad);

    $evento = EventoAuditoria::query()
        ->where('entidad', 'User')->where('entidad_id', $cuenta->id)->where('accion', 'rol_cambiado')
        ->firstOrFail();

    expect($evento->valor_anterior)->toBe(['rol' => 'tecnico'])
        ->and($evento->valor_nuevo)->toBe(['rol' => 'responsable_seguridad'])
        ->and($evento->usuario_id)->toBe($this->responsable->id);
});

it('no deja a la organización sin responsable de seguridad', function (): void {
    $otro = usuarioCon(Rol::ResponsableSeguridad);
    $otro->forceFill(['desactivada_en' => now()])->save();

    // El único que entra es el que hace la petición; el otro está desactivado.
    expect(fn () => app(CambiarRol::class)($otro, $this->responsable, Rol::Tecnico))
        ->toThrow(OperacionDeCuentaNoPermitida::class);

    expect($this->responsable->fresh()?->rol())->toBe(Rol::ResponsableSeguridad);
});

it('un responsable no se quita el rol a sí mismo', function (): void {
    usuarioCon(Rol::ResponsableSeguridad);

    $this->actingAs($this->responsable)
        ->put("/cuentas/{$this->responsable->id}", ['rol' => 'tecnico'])
        ->assertSessionHasErrors('rol');

    expect($this->responsable->fresh()?->rol())->toBe(Rol::ResponsableSeguridad);
});

it('con otro responsable activo, sí se le puede quitar el rol a uno', function (): void {
    $otro = usuarioCon(Rol::ResponsableSeguridad);

    $this->actingAs($this->responsable)
        ->put("/cuentas/{$otro->id}", ['rol' => 'tecnico'])
        ->assertSessionHasNoErrors();

    expect($otro->fresh()?->rol())->toBe(Rol::Tecnico);
});

it('pasar de auditor a técnico borra el alcance y la fecha de fin', function (): void {
    $sistema = Sistema::factory()->create();
    $cuenta = usuarioCon(Rol::Tecnico);

    $this->actingAs($this->responsable)->put("/cuentas/{$cuenta->id}", [
        'rol' => 'auditor', 'sistemas' => [$sistema->id], 'acceso_hasta' => today()->addWeek()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($cuenta->alcance()->count())->toBe(1);

    $this->actingAs($this->responsable)->put("/cuentas/{$cuenta->id}", ['rol' => 'tecnico'])
        ->assertSessionHasNoErrors();

    $cuenta->refresh();

    expect($cuenta->alcance()->count())->toBe(0)
        ->and($cuenta->acceso_hasta)->toBeNull();
});

it('no acepta una fecha de fin pasada', function (): void {
    $sistema = Sistema::factory()->create();
    $cuenta = usuarioCon(Rol::Tecnico);

    $this->actingAs($this->responsable)->put("/cuentas/{$cuenta->id}", [
        'rol' => 'auditor', 'sistemas' => [$sistema->id], 'acceso_hasta' => today()->subDay()->toDateString(),
    ])->assertSessionHasErrors('acceso_hasta');
});

it('el nombre y el correo no los reescribe otro', function (): void {
    $cuenta = usuarioCon(Rol::Tecnico);

    $this->actingAs($this->responsable)
        ->put("/cuentas/{$cuenta->id}", ['rol' => 'tecnico', 'email' => 'mio@ejemplo.test'])
        ->assertSessionHasErrors('email');

    expect($cuenta->fresh()?->email)->not->toBe('mio@ejemplo.test');
});
