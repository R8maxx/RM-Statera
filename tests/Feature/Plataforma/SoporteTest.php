<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Notifications\EntradaDeSoporte;
use App\Domain\Plataforma\Soporte\SesionDeSoporte;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La ventana de soporte (punto 44)
|--------------------------------------------------------------------------
|
| El cliente abre la puerta, por un tiempo; la plataforma entra a mirar, y sólo
| a mirar. Lo que se comprueba aquí es la frontera: sin puerta no se ve nada,
| con puerta no se escribe nada, y nunca se ve otra organización.
|
*/

function adminSoporte(): User
{
    $admin = User::factory()->plataforma()->create();
    $admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $admin;
}

beforeEach(function (): void {
    Notification::fake();
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->sistema = Sistema::factory()->create(['nombre' => 'Sistema del cliente']);
    $this->admin = adminSoporte();
});

/** El cliente abre la ventana desde su ficha. */
function abrirVentana(object $test, int $horas = 24): void
{
    $test->actingAs($test->responsable)->post('/organizacion/soporte', ['horas' => $horas])->assertRedirect();
}

/** El administrador entra, como lo haría desde la ficha de la plataforma. */
function entrarComoSoporte(object $test): void
{
    sinOrganizacion();
    $test->actingAs($test->admin)
        ->post("/plataforma/organizaciones/{$test->organizacion->id}/soporte")
        ->assertRedirect('/panel');
}

it('el cliente abre la ventana y queda en su traza', function (): void {
    abrirVentana($this);

    $organizacion = $this->organizacion->fresh();

    expect($organizacion?->soporteAbierto())->toBeTrue()
        ->and($organizacion?->soporte_abierto_por)->toBe($this->responsable->id);

    $evento = EventoAuditoria::query()->where('entidad', 'Organizacion')->latest('id')->first();
    expect($evento?->valor_nuevo)->toHaveKey('soporte_hasta');
});

it('sólo el responsable de seguridad abre la ventana', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnico)->post('/organizacion/soporte', ['horas' => 24])->assertForbidden();
});

it('sin ventana no se entra', function (): void {
    sinOrganizacion();

    $this->actingAs($this->admin)
        ->post("/plataforma/organizaciones/{$this->organizacion->id}/soporte")
        ->assertSessionHasErrors('soporte');

    $this->actingAs($this->admin)->get('/sistemas')->assertForbidden();
});

it('con ventana se entra, se ve y se avisa al responsable', function (): void {
    abrirVentana($this);
    entrarComoSoporte($this);

    $this->actingAs($this->admin)->get('/sistemas')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('organizacion.nombre', $this->organizacion->nombre)
            ->where('soporte.organizacion', $this->organizacion->nombre)
            ->where('filas.0.nombre', 'Sistema del cliente'));

    Notification::assertSentTo($this->responsable, EntradaDeSoporte::class);

    $entrada = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->organizacion,
        fn () => EventoAuditoria::query()->where('accion', 'soporte_entrada')->first(),
    );

    expect($entrada?->usuario_id)->toBe($this->admin->id);
});

it('dentro no se escribe nada', function (): void {
    abrirVentana($this);
    entrarComoSoporte($this);

    $this->actingAs($this->admin)
        ->post('/sistemas', ['codigo' => 'SIS-9', 'nombre' => 'Nuevo', 'marco_id' => $this->sistema->marco_id, 'estado' => 'activo'])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->put('/organizacion', ['nombre' => 'Cambiado por soporte'])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete('/organizacion/soporte')
        ->assertForbidden();

    app(ContextoOrganizacion::class)->establecer($this->organizacion);

    expect(Sistema::query()->count())->toBe(1)
        ->and($this->organizacion->fresh()?->nombre)->not->toBe('Cambiado por soporte');
});

it('dentro sólo tiene los permisos de lectura', function (): void {
    abrirVentana($this);
    entrarComoSoporte($this);

    $this->actingAs($this->admin)->get('/panel')->assertOk();
    $this->actingAs($this->admin)->get('/cuentas')->assertForbidden();
    $this->actingAs($this->admin)->get('/organizacion')->assertForbidden();
});

it('no ve nada de otra organización aunque esté dentro de una', function (): void {
    $otra = Organizacion::factory()->create();
    $ajeno = app(ContextoOrganizacion::class)->paraOrganizacion($otra, fn () => Sistema::factory()->create());

    abrirVentana($this);
    entrarComoSoporte($this);

    $this->actingAs($this->admin)->get("/sistemas/{$ajeno->id}")->assertNotFound();
});

it('sale en la siguiente petición si el cliente cierra la puerta', function (): void {
    abrirVentana($this);
    entrarComoSoporte($this);

    $this->organizacion->refresh();
    app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->organizacion,
        fn () => $this->organizacion->forceFill(['soporte_hasta' => null])->save(),
    );

    $this->actingAs($this->admin)->get('/sistemas')->assertRedirect('/plataforma/organizaciones');
    $this->actingAs($this->admin)->get('/sistemas')->assertForbidden();
});

it('sale solo cuando se acaba el plazo', function (): void {
    abrirVentana($this, 4);
    entrarComoSoporte($this);

    $this->travel(5)->hours();

    $this->actingAs($this->admin)->get('/sistemas')->assertRedirect('/plataforma/organizaciones');
});

it('salir deja la salida en la traza del cliente', function (): void {
    abrirVentana($this);
    entrarComoSoporte($this);

    $this->actingAs($this->admin)->post('/plataforma/soporte/salir')
        ->assertRedirect("/plataforma/organizaciones/{$this->organizacion->id}")
        ->assertSessionMissing(SesionDeSoporte::CLAVE);

    $salida = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->organizacion,
        fn () => EventoAuditoria::query()->where('accion', 'soporte_salida')->exists(),
    );

    expect($salida)->toBeTrue();
    $this->actingAs($this->admin)->get('/sistemas')->assertForbidden();
});

it('una cuenta de cliente no entra como soporte en otra', function (): void {
    abrirVentana($this);
    $otra = comoOrganizacion();
    $ajeno = usuarioCon(Rol::ResponsableSeguridad, $otra);
    sinOrganizacion();

    $this->actingAs($ajeno)
        ->post("/plataforma/organizaciones/{$this->organizacion->id}/soporte")
        ->assertForbidden();
});

it('si el cliente cierra y vuelve a abrir, hay que entrar de nuevo', function (): void {
    abrirVentana($this);
    entrarComoSoporte($this);

    $this->actingAs($this->responsable)->delete('/organizacion/soporte');
    abrirVentana($this, 48);

    $this->actingAs($this->admin)->get('/sistemas')->assertRedirect('/plataforma/organizaciones');
    $this->actingAs($this->admin)->get('/sistemas')->assertForbidden();
});
