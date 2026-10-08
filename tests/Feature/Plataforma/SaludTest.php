<?php

declare(strict_types=1);

use App\Domain\Copia\EstadoDeLasCopias;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La salud del servicio (punto 55)
|--------------------------------------------------------------------------
*/

function deSalud(PerfilPlataforma $perfil = PerfilPlataforma::Administracion): User
{
    $cuenta = User::factory()->plataforma()->create(['perfil_plataforma' => $perfil->value]);
    $cuenta->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $cuenta;
}

function copiaEn(Carbon $cuando): void
{
    Storage::disk((string) config('copias.disco'))->put('bases/'.$cuando->format('Y-m-d\THis').'/manifiesto.json', '{}');
}

function verificacionEn(Carbon $cuando, bool $correcta): void
{
    Storage::disk((string) config('copias.disco'))->put(
        'verificaciones/'.$cuando->format('Y-m-d\THis').'.json',
        (string) json_encode(['verificada_en' => $cuando->toIso8601String(), 'correcta' => $correcta]),
    );
}

beforeEach(fn () => Storage::fake((string) config('copias.disco')));

it('las copias al día y probadas salen bien', function (): void {
    copiaEn(now()->subHours(5));
    verificacionEn(now()->subDays(2), true);

    $resumen = app(EstadoDeLasCopias::class)->resumen();

    expect($resumen['copiaAlDia'])->toBeTrue()
        ->and($resumen['verificacionAlDia'])->toBeTrue();
});

it('una copia vieja o una verificación fallida salen en rojo', function (): void {
    copiaEn(now()->subDays(3));
    verificacionEn(now()->subDay(), false);

    $resumen = app(EstadoDeLasCopias::class)->resumen();

    expect($resumen['copiaAlDia'])->toBeFalse()
        ->and($resumen['verificacionCorrecta'])->toBeFalse()
        ->and($resumen['verificacionAlDia'])->toBeFalse();
});

it('sin copias ni verificaciones, nada está al día', function (): void {
    $resumen = app(EstadoDeLasCopias::class)->resumen();

    expect($resumen['ultimaCopia'])->toBeNull()
        ->and($resumen['copiaAlDia'])->toBeFalse();
});

it('de los trabajos fallidos no enseña nunca los datos', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'notificaciones',
        'payload' => (string) json_encode(['displayName' => 'App\\Domain\\Plataforma\\Notifications\\AvisoDeRescate', 'data' => ['email' => 'secreta@cliente.test']]),
        'exception' => "RuntimeException: el correo no salió\n#0 traza larga",
        'failed_at' => now(),
    ]);

    $respuesta = $this->actingAs(deSalud())->get('/plataforma/salud');

    $respuesta->assertOk()->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->where('fallidos.0.trabajo', 'AvisoDeRescate')
        ->where('fallidos.0.error', 'RuntimeException: el correo no salió'));

    expect(json_encode($respuesta->viewData('page')))->not->toContain('secreta@cliente.test');
});

it('gestión comercial no ve la salud ni Horizon', function (): void {
    $comercial = deSalud(PerfilPlataforma::Comercial);

    $this->actingAs($comercial)->get('/plataforma/salud')->assertForbidden();

    expect(Gate::forUser($comercial)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(deSalud())->allows('viewHorizon'))->toBeTrue();
});

it('el cuadro de mando avisa de la salud sólo a quien puede verla', function (): void {
    copiaEn(now()->subDays(3));

    $this->actingAs(deSalud())->get('/plataforma')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('cifras.copiasAlDia', false));

    $this->actingAs(deSalud(PerfilPlataforma::Comercial))->get('/plataforma')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('cifras.copiasAlDia', null));
});
