<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Domain\Plataforma\Models\Plan;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El cuadro de mando de la plataforma (punto 53)
|--------------------------------------------------------------------------
|
| Cada cifra es una lista filtrada: se comprueba que cuentan lo mismo.
|
*/

function clienteCon(array $columnas, ?Plan $plan = null): Organizacion
{
    $organizacion = Organizacion::factory()->create();
    app(ContextoOrganizacion::class)->paraOrganizacion($organizacion, fn () => $organizacion->forceFill([
        'plan_id' => $plan?->id,
        ...$columnas,
    ])->save());

    return $organizacion;
}

function verPlataforma(PerfilPlataforma $perfil = PerfilPlataforma::Administracion): User
{
    $cuenta = User::factory()->plataforma()->create(['perfil_plataforma' => $perfil->value]);
    $cuenta->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $cuenta;
}

beforeEach(function (): void {
    $this->plan = Plan::factory()->create(['dias_gracia' => 10, 'limite_cuentas' => 1]);

    clienteCon(['suscripcion_vence_en' => now()->addDays(5)], $this->plan);
    clienteCon(['suscripcion_vence_en' => now()->subDays(3)], $this->plan);
    clienteCon(['suscripcion_vence_en' => now()->subDays(30)], $this->plan);
    clienteCon(['baja_en' => now(), 'activa' => false]);
    clienteCon(['soporte_hasta' => now()->addDay()]);

    $sobre = clienteCon(['suscripcion_vence_en' => now()->addYear()], $this->plan);
    usuarioCon(Rol::ResponsableSeguridad, $sobre);
    usuarioCon(Rol::Tecnico, $sobre);
    usuarioCon(Rol::Auditor, $sobre);
    sinOrganizacion();
});

it('cuenta lo que requiere atención', function (): void {
    $this->actingAs(verPlataforma())->get('/plataforma')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('plataforma/Inicio')
            ->where('cifras.vencenPronto', 1)
            ->where('cifras.enGracia', 1)
            ->where('cifras.enSoloLectura', 1)
            ->where('cifras.deBaja', 1)
            ->where('cifras.soporteAbierto', 1)
            ->where('cifras.sobreSuPlan', 1));
});

it('cada cifra lleva a una lista con las mismas filas', function (string $filtro, int $esperadas): void {
    $this->actingAs(verPlataforma())->get("/plataforma/organizaciones?filter[{$filtro}]=1")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', $esperadas));
})->with([
    ['vence_pronto', 1],
    ['en_gracia', 1],
    ['solo_lectura', 1],
    ['de_baja', 1],
    ['soporte_abierto', 1],
    ['sobre_su_plan', 1],
]);

it('gestión comercial también lo ve, y es la casa de la plataforma', function (): void {
    $comercial = verPlataforma(PerfilPlataforma::Comercial);

    $this->actingAs($comercial)->get('/plataforma')->assertOk();
    $this->actingAs($comercial)->get('/inicio')->assertRedirect('/plataforma');
});
