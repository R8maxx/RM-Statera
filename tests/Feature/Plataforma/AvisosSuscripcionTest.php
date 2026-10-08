<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Plataforma\CambiarSuscripcion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\HitoSuscripcion;
use App\Domain\Plataforma\Models\AvisoSuscripcion;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\Notifications\ResumenDeVencimientos;
use App\Domain\Plataforma\Notifications\VencimientoDeSuscripcion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Los avisos de vencimiento por correo (punto 46)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-11-01 07:15', 'Europe/Madrid'));
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->tecnico = usuarioCon(Rol::Tecnico);
    $this->admin = User::factory()->plataforma()->create();
    $this->plan = Plan::factory()->create(['dias_gracia' => 10]);
});

afterEach(fn () => Carbon::setTestNow());

function venceEl(object $test, string $fecha): void
{
    app(CambiarSuscripcion::class)($test->organizacion, $test->plan, Carbon::parse($fecha)->endOfDay());
    app(ContextoOrganizacion::class)->olvidar();
}

it('elige el hito por días de calendario', function (string $vence, ?HitoSuscripcion $esperado): void {
    venceEl($this, $vence);

    expect(HitoSuscripcion::de($this->organizacion->fresh()))->toBe($esperado);
})->with([
    'en dos meses' => ['2027-01-01', null],
    'en un mes' => ['2026-12-01', HitoSuscripcion::Faltan30],
    'en una semana' => ['2026-11-08', HitoSuscripcion::Faltan7],
    'mañana' => ['2026-11-02', HitoSuscripcion::Falta1],
    'venció hace dos días' => ['2026-10-30', HitoSuscripcion::EnGracia],
    'venció hace un mes' => ['2026-10-01', HitoSuscripcion::SoloLectura],
]);

it('avisa a los responsables, y a la plataforma con un resumen', function (): void {
    venceEl($this, '2026-11-08');

    $this->artisan('suscripciones:avisar')->assertSuccessful();

    Notification::assertSentTo($this->responsable, VencimientoDeSuscripcion::class);
    Notification::assertNotSentTo($this->tecnico, VencimientoDeSuscripcion::class);
    Notification::assertSentTo($this->admin, ResumenDeVencimientos::class);
});

it('cada aviso sale una sola vez', function (): void {
    venceEl($this, '2026-11-08');

    $this->artisan('suscripciones:avisar');
    $this->artisan('suscripciones:avisar');

    Notification::assertSentToTimes($this->responsable, VencimientoDeSuscripcion::class, 1);
    expect(AvisoSuscripcion::query()->count())->toBe(1);
});

it('al renovar, los avisos vuelven a empezar', function (): void {
    venceEl($this, '2026-11-08');
    $this->artisan('suscripciones:avisar');

    venceEl($this, '2026-11-07');
    $this->artisan('suscripciones:avisar');

    Notification::assertSentToTimes($this->responsable, VencimientoDeSuscripcion::class, 2);
});

it('en simulación no envía ni anota nada', function (): void {
    venceEl($this, '2026-11-08');

    $this->artisan('suscripciones:avisar', ['--dry-run' => true])->assertSuccessful();

    Notification::assertNothingSent();
    expect(AvisoSuscripcion::query()->count())->toBe(0)
        ->and(EventoPlataforma::query()->where('accion', AccionPlataforma::AvisoVencimientoEnviado->value)->exists())->toBeFalse();
});

it('deja cada aviso en la traza de la plataforma, con el hito y a quién fue', function (): void {
    venceEl($this, '2026-11-08');

    $this->artisan('suscripciones:avisar');
    $this->artisan('suscripciones:avisar');

    $eventos = EventoPlataforma::query()->where('accion', AccionPlataforma::AvisoVencimientoEnviado->value)->get();

    expect($eventos)->toHaveCount(1)
        ->and($eventos->first()?->organizacion_afectada_id)->toBe($this->organizacion->id)
        ->and($eventos->first()?->detalle['hito'] ?? null)->toBe(HitoSuscripcion::Faltan7->value)
        ->and($eventos->first()?->resumen())->toBe("Vence en una semana · a {$this->responsable->email}");
});

it('también anota el aviso que no tenía a quién llegar', function (): void {
    venceEl($this, '2026-11-08');
    $this->responsable->forceFill(['desactivada_en' => now()])->save();

    $this->artisan('suscripciones:avisar');

    expect(EventoPlataforma::query()->where('accion', AccionPlataforma::AvisoVencimientoEnviado->value)->first()?->resumen())
        ->toBe('Vence en una semana · sin responsable de seguridad que pudiera recibirlo');
});

it('la ficha del cliente enseña el aviso en lo que ha hecho la plataforma', function (): void {
    venceEl($this, '2026-11-08');
    $this->artisan('suscripciones:avisar');
    sinOrganizacion();
    $this->admin->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    $this->actingAs($this->admin)->get("/plataforma/organizaciones/{$this->organizacion->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('traza.0.accion', 'Aviso de vencimiento enviado')
            ->where('traza.0.resumen', "Vence en una semana · a {$this->responsable->email}"));
});

it('no avisa a una organización de baja ni a una sin vencimiento', function (): void {
    venceEl($this, '2026-11-08');
    $this->organizacion->forceFill(['baja_en' => now(), 'activa' => false])->saveQuietly();

    $this->artisan('suscripciones:avisar');

    Notification::assertNothingSent();
});
