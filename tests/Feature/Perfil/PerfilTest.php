<?php

declare(strict_types=1);

use App\Domain\Organizacion\Models\Organizacion;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

/*
|--------------------------------------------------------------------------
| Mi cuenta
|--------------------------------------------------------------------------
|
| Requisito no funcional del § 6: segundo factor obligatorio para quien pueda
| escribir. Lo que se prueba aquí sobre todo es que el secreto y los códigos de
| recuperación NO se sirven sin reconfirmar la contraseña: son lo que se lleva
| quien se siente delante de una sesión abierta.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = User::factory()->create(['organizacion_id' => $this->organizacion->id]);
});

it('sirve la pantalla con el estado del segundo factor', function (): void {
    $this->actingAs($this->usuario)
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('perfil/Index')
            ->where('dosFactores.confirmado', false)
            ->where('dosFactores.pendiente', false)
            ->has('passkeys', 0)
        );
});

it('la pantalla normal nunca lleva el secreto ni los códigos de recuperación', function (): void {
    app(EnableTwoFactorAuthentication::class)($this->usuario);

    $this->actingAs($this->usuario->fresh())
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            // Con secreto generado, pero sin confirmar: el segundo factor
            // todavía no protege nada y la pantalla lo dice.
            ->where('dosFactores.pendiente', true)
            ->where('dosFactores.confirmado', false)
            ->where('secreto', null)
        );
});

it('el secreto exige reconfirmar la contraseña', function (): void {
    app(EnableTwoFactorAuthentication::class)($this->usuario);

    $this->actingAs($this->usuario->fresh())
        ->get('/perfil/dos-factores')
        ->assertRedirect('/user/confirm-password');
});

it('con la contraseña reconfirmada sirve el QR, la clave y los códigos', function (): void {
    app(EnableTwoFactorAuthentication::class)($this->usuario);

    $this->actingAs($this->usuario->fresh())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/perfil/dos-factores')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('perfil/Index')
            ->has('secreto.qr')
            ->has('secreto.clave')
            // Ocho códigos de un solo uso: la puerta de vuelta si se pierde el
            // teléfono.
            ->has('secreto.codigos', 8)
        );
});

it('sin segundo factor activo no hay secreto que enseñar', function (): void {
    $this->actingAs($this->usuario)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/perfil/dos-factores')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('secreto', null));
});

it('sólo enseña las passkeys de quien ha entrado', function (): void {
    $otro = User::factory()->create(['organizacion_id' => $this->organizacion->id]);

    $otro->passkeys()->create([
        'name' => 'Llave de otra persona',
        'credential_id' => 'credencial-sintetica',
        'credential' => ['tipo' => 'sintetica'],
    ]);

    $this->actingAs($this->usuario)
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('passkeys', 0));

    $this->actingAs($otro)
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('passkeys', 1)
            ->where('passkeys.0.nombre', 'Llave de otra persona')
        );
});

it('el segundo factor confirmado se refleja en la pantalla', function (): void {
    app(EnableTwoFactorAuthentication::class)($this->usuario);

    $this->usuario->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->actingAs($this->usuario->fresh())
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('dosFactores.confirmado', true)
            ->where('dosFactores.pendiente', false)
        );
});

it('un usuario de otra organización ve su propia cuenta, no la de nadie más', function (): void {
    $otra = Organizacion::factory()->create();
    $ajeno = User::factory()->create(['organizacion_id' => $otra->id, 'name' => 'De otra casa']);

    $this->actingAs($ajeno)
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('usuario.nombre', 'De otra casa'));
});

it('exige sesión iniciada', function (): void {
    $this->get('/perfil')->assertRedirect('/login');
    $this->get('/perfil/dos-factores')->assertRedirect('/login');
});
