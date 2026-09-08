<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Entrada a la aplicación
|--------------------------------------------------------------------------
|
| Sin registro self-service (§8 de la especificación) y con 2FA disponible
| (invariante 8: la herramienta entra en el alcance del propio SGSI).
|
*/

it('sirve la pantalla de acceso', function (): void {
    $this->get('/login')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('auth/Login')
            ->where('puedeRestablecer', true)
        );
});

it('deja entrar con credenciales correctas y lleva al panel', function (): void {
    $organizacion = comoOrganizacion();
    $usuario = User::factory()->create([
        'organizacion_id' => $organizacion->id,
        'password' => Hash::make('contrasena-correcta'),
    ]);

    $this->post('/login', ['email' => $usuario->email, 'password' => 'contrasena-correcta'])
        ->assertRedirect('/panel');

    $this->assertAuthenticatedAs($usuario);
});

it('rechaza credenciales incorrectas', function (): void {
    $usuario = User::factory()->create(['password' => Hash::make('la-buena')]);

    $this->post('/login', ['email' => $usuario->email, 'password' => 'la-mala'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('el panel comparte el usuario y su organización', function (): void {
    $organizacion = comoOrganizacion();
    $usuario = User::factory()->create(['organizacion_id' => $organizacion->id]);

    $this->actingAs($usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('Panel')
            ->where('auth.usuario.email', $usuario->email)
            ->where('auth.usuario.dosFactores', false)
            ->where('organizacion.nombre', $organizacion->nombre)
        );
});

it('con 2FA confirmado no autentica: manda al reto', function (): void {
    $usuario = User::factory()->create([
        'password' => Hash::make('contrasena-correcta'),
        'two_factor_secret' => encrypt('secreto'),
        'two_factor_recovery_codes' => encrypt(json_encode(['codigo-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post('/login', ['email' => $usuario->email, 'password' => 'contrasena-correcta'])
        ->assertRedirect('/two-factor-challenge');

    $this->assertGuest();
});

it('sirve la pantalla del reto de dos factores', function (): void {
    $usuario = User::factory()->create([
        'password' => Hash::make('contrasena-correcta'),
        'two_factor_secret' => encrypt('secreto'),
        'two_factor_recovery_codes' => encrypt(json_encode(['codigo-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post('/login', ['email' => $usuario->email, 'password' => 'contrasena-correcta']);

    $this->get('/two-factor-challenge')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->component('auth/DesafioDosFactores'));
});

it('no hay alta self-service', function (): void {
    expect(Features::enabled(Features::registration()))->toBeFalse();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

it('sirve la pantalla de recuperación de contraseña', function (): void {
    $this->get('/forgot-password')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->component('auth/OlvidePassword'));
});

it('cierra la sesión', function (): void {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post('/logout')->assertRedirect();

    $this->assertGuest();
});
