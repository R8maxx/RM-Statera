<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route as Rutas;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Los perfiles de la plataforma (punto 48)
|--------------------------------------------------------------------------
|
| Administración puede todo; Gestión comercial, clientes, planes y la traza.
| El primer test descubre las rutas en vez de enumerarlas: una ruta nueva de
| `/plataforma` sin capacidad lo pone rojo.
|
*/

/** Las rutas de la plataforma que no exigen capacidad, cada una con su motivo. */
const RUTAS_SIN_CAPACIDAD = [
    'plataforma.soporte.salir' => 'Quien está dentro de un cliente como soporte tiene que poder salir siempre.',
];

function deLaPlataforma(PerfilPlataforma $perfil): User
{
    $cuenta = User::factory()->plataforma()->create(['perfil_plataforma' => $perfil->value]);
    $cuenta->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $cuenta;
}

it('toda ruta de la plataforma exige una capacidad', function (): void {
    $rutas = collect(Rutas::getRoutes()->getRoutes())
        ->filter(fn (Route $ruta): bool => str_starts_with((string) $ruta->getName(), 'plataforma.'));

    expect($rutas)->not->toBeEmpty('No se encuentran las rutas de la plataforma.');

    foreach ($rutas as $ruta) {
        $nombre = (string) $ruta->getName();
        $conCapacidad = collect($ruta->gatherMiddleware())->contains(fn ($m): bool => is_string($m) && str_starts_with($m, 'plataforma:'));

        expect($conCapacidad || array_key_exists($nombre, RUTAS_SIN_CAPACIDAD))->toBeTrue(
            "La ruta {$nombre} no exige ninguna capacidad: añádele `plataforma:<capacidad>` o decláralo en RUTAS_SIN_CAPACIDAD con su motivo.",
        );
    }
});

it('gestión comercial lleva clientes y planes, y no entra en lo demás', function (): void {
    $organizacion = Organizacion::factory()->create();
    $comercial = deLaPlataforma(PerfilPlataforma::Comercial);

    $this->actingAs($comercial)->get('/plataforma/organizaciones')->assertOk();
    $this->actingAs($comercial)->get("/plataforma/organizaciones/{$organizacion->id}")->assertOk();
    $this->actingAs($comercial)->get('/plataforma/planes')->assertOk();

    $this->actingAs($comercial)->post("/plataforma/organizaciones/{$organizacion->id}/soporte")->assertForbidden();
    $this->actingAs($comercial)->post("/plataforma/organizaciones/{$organizacion->id}/baja", ['motivo' => 'x'])->assertForbidden();
});

it('gestión comercial no entra como soporte aunque la ventana esté abierta', function (): void {
    $organizacion = Organizacion::factory()->create();
    app(ContextoOrganizacion::class)->paraOrganizacion(
        $organizacion,
        fn () => $organizacion->forceFill(['soporte_hasta' => now()->addDay()])->save(),
    );

    $this->actingAs(deLaPlataforma(PerfilPlataforma::Comercial))
        ->post("/plataforma/organizaciones/{$organizacion->id}/soporte")
        ->assertForbidden();
});

it('cada perfil recibe sólo sus capacidades para pintar', function (): void {
    $this->actingAs(deLaPlataforma(PerfilPlataforma::Comercial))->get('/plataforma/organizaciones')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('auth.permisos', fn ($permisos): bool => collect($permisos)->contains('plataforma.clientes.ver')
            && ! collect($permisos)->contains('plataforma.soporte.entrar')));

    $this->actingAs(deLaPlataforma(PerfilPlataforma::Administracion))->get('/plataforma/organizaciones')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('auth.permisos', fn ($permisos): bool => collect($permisos)->contains('plataforma.soporte.entrar')
            && collect($permisos)->contains('plataforma.salud.ver')));
});

it('la base no admite un administrador sin perfil ni un perfil sin administrador', function (): void {
    $administrador = deLaPlataforma(PerfilPlataforma::Administracion);
    $cliente = User::factory()->create();

    expect(fn () => DB::table('users')->where('id', $administrador->id)->update(['perfil_plataforma' => null]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('users')->where('id', $cliente->id)->update(['perfil_plataforma' => 'comercial']))
        ->toThrow(QueryException::class);
});

it('la consola crea un administrador con el perfil pedido', function (): void {
    Notification::fake();

    $this->artisan('plataforma:administrador', ['email' => 'carla@statera.test', 'nombre' => 'Carla', '--perfil' => 'comercial'])
        ->assertSuccessful();

    $cuenta = User::query()->whereNull('organizacion_id')->where('email', 'carla@statera.test')->firstOrFail();

    expect($cuenta->perfil_plataforma)->toBe(PerfilPlataforma::Comercial);
});
