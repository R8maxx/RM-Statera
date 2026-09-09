<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Sistema\Models\Sistema;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

/*
|--------------------------------------------------------------------------
| Segundo factor obligatorio para quien escribe
|--------------------------------------------------------------------------
|
| Requisito no funcional del § 6. La herramienta entra en el alcance del propio
| SGSI: contiene el inventario, las vulnerabilidades y las evidencias, así que
| una contraseña robada no puede bastar para tocarlos.
|
| Va por defecto activado y se apaga sólo en desarrollo, con una variable de
| entorno. Estos tests lo encienden a mano para probar lo que hará en producción.
|
*/

beforeEach(function (): void {
    config(['seguridad.exigir_dos_factores' => true]);

    $this->organizacion = comoOrganizacion();
    $this->marco = Marco::factory()->create();
});

it('sin segundo factor, quien puede escribir no escribe', function (): void {
    $responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->actingAs($responsable)
        ->post('/sistemas', [
            'codigo' => 'SIS-01',
            'nombre' => 'Plataforma',
            'marco_id' => $this->marco->id,
            'estado' => 'activo',
        ])
        ->assertRedirect('/perfil');

    expect(Sistema::query()->count())->toBe(0);
});

it('la lectura sigue abierta: no se encierra a nadie fuera', function (): void {
    $responsable = usuarioCon(Rol::ResponsableSeguridad);

    foreach (['/panel', '/sistemas', '/implantaciones', '/evidencias', '/perfil'] as $ruta) {
        $this->actingAs($responsable)->get($ruta)->assertOk();
    }
});

it('al auditor no se le pide: no puede alterar nada', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    // Sin segundo factor y sin fricción, porque sólo lee. Exigírselo sería
    // molestia sin ganancia.
    $this->actingAs($auditor)->get('/implantaciones')->assertOk();
});

it('con el segundo factor confirmado se escribe con normalidad', function (): void {
    $responsable = usuarioCon(Rol::ResponsableSeguridad);

    app(EnableTwoFactorAuthentication::class)($responsable);
    $responsable->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->actingAs($responsable->fresh())
        ->post('/sistemas', [
            'codigo' => 'SIS-01',
            'nombre' => 'Plataforma',
            'marco_id' => $this->marco->id,
            'estado' => 'activo',
        ])
        ->assertRedirect('/sistemas');

    expect(Sistema::query()->count())->toBe(1);
});

it('un secreto generado y sin confirmar no vale: ahí no protege nada', function (): void {
    $responsable = usuarioCon(Rol::ResponsableSeguridad);

    app(EnableTwoFactorAuthentication::class)($responsable);

    $this->actingAs($responsable->fresh())
        ->post('/sistemas', [
            'codigo' => 'SIS-01',
            'nombre' => 'Plataforma',
            'marco_id' => $this->marco->id,
            'estado' => 'activo',
        ])
        ->assertRedirect('/perfil');

    expect(Sistema::query()->count())->toBe(0);
});

it('apagado por entorno, no estorba', function (): void {
    config(['seguridad.exigir_dos_factores' => false]);

    $responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->actingAs($responsable)
        ->post('/sistemas', [
            'codigo' => 'SIS-02',
            'nombre' => 'Plataforma',
            'marco_id' => $this->marco->id,
            'estado' => 'activo',
        ])
        ->assertRedirect('/sistemas');

    expect(Sistema::query()->count())->toBe(1);
});
