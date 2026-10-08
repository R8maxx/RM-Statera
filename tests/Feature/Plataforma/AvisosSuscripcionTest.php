<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Plataforma\CambiarSuscripcion;
use App\Domain\Plataforma\Enums\HitoSuscripcion;
use App\Domain\Plataforma\Models\AvisoSuscripcion;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\Notifications\ResumenDeVencimientos;
use App\Domain\Plataforma\Notifications\VencimientoDeSuscripcion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

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
    expect(AvisoSuscripcion::query()->count())->toBe(0);
});

it('no avisa a una organización de baja ni a una sin vencimiento', function (): void {
    venceEl($this, '2026-11-08');
    $this->organizacion->forceFill(['baja_en' => now(), 'activa' => false])->saveQuietly();

    $this->artisan('suscripciones:avisar');

    Notification::assertNothingSent();
});
