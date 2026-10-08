<?php

declare(strict_types=1);

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La traza de la plataforma, consultable (punto 50)
|--------------------------------------------------------------------------
*/

function lectorDeTraza(PerfilPlataforma $perfil = PerfilPlataforma::Administracion): User
{
    $cuenta = User::factory()->plataforma()->create(['perfil_plataforma' => $perfil->value]);
    $cuenta->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $cuenta;
}

function evento(AccionPlataforma $accion, ?Organizacion $organizacion = null, array $detalle = [], ?int $usuarioId = null): void
{
    EventoPlataforma::query()->create([
        'usuario_id' => $usuarioId,
        'organizacion_afectada_id' => $organizacion?->id,
        'accion' => $accion->value,
        'detalle' => $detalle === [] ? null : $detalle,
        'created_at' => now(),
    ]);
}

it('la leen los dos perfiles', function (PerfilPlataforma $perfil): void {
    $this->actingAs(lectorDeTraza($perfil))->get('/plataforma/traza')->assertOk();
})->with([PerfilPlataforma::Administracion, PerfilPlataforma::Comercial]);

it('un cliente no la lee', function (): void {
    comoOrganizacion();
    $cliente = usuarioCon();
    sinOrganizacion();

    $this->actingAs($cliente)->get('/plataforma/traza')->assertForbidden();
});

it('filtra por cliente y por acción', function (): void {
    $lector = lectorDeTraza();
    $una = Organizacion::factory()->create();
    $otra = Organizacion::factory()->create();
    evento(AccionPlataforma::OrganizacionAlta, $una);
    evento(AccionPlataforma::OrganizacionBaja, $una);
    evento(AccionPlataforma::OrganizacionAlta, $otra);

    $this->actingAs($lector)->get("/plataforma/traza?filter[organizacion_afectada_id]={$una->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));

    $this->actingAs($lector)->get('/plataforma/traza?filter[accion]=organizacion_baja')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 1));
});

it('nunca enseña una clave secreta del detalle', function (): void {
    $lector = lectorDeTraza();
    evento(AccionPlataforma::AdministradorCreado, null, ['email' => 'ada@statera.test', 'password' => 'no-debe-salir', 'token' => 'tampoco']);

    $this->actingAs($lector)->get('/plataforma/traza?filter[accion]=administrador_creado')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.detalle', fn (?string $detalle): bool => $detalle !== null
                && str_contains($detalle, 'ada@statera.test')
                && ! str_contains($detalle, 'no-debe-salir')
                && ! str_contains($detalle, 'tampoco')));
});
