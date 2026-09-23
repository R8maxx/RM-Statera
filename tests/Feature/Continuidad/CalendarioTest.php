<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| El calendario: las dos fuentes de continuidad (§ 4.11 dentro de § 4.16)
|--------------------------------------------------------------------------
|
| `tests/Feature/Calendario/CalendarioTest.php` ya cubre las nueve fuentes por
| igual con `sembrarVencimiento()` —el tono, el rojo, el permiso—, así que esto
| no repite esa cobertura: comprueba lo que es propio de estas dos, contra la
| ruta real y no sólo contra `CalendarioVencimientos`.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('una prueba de continuidad planificada en fecha sale en el mes con su tono', function (): void {
    Carbon::setTestNow('2026-09-01');

    PruebaContinuidad::factory()->planificada()->create([
        'codigo' => 'PC-2026-01',
        'titulo' => 'Simulacro del centro alternativo',
        'fecha_prevista' => '2026-09-20',
    ]);

    $this->actingAs($this->usuario)
        ->get('/calendario?mes=2026-09')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $pagina): void {
            $vencimiento = collect($pagina->toArray()['props']['vencimientos'])
                ->firstWhere('fuente', 'prueba_continuidad');

            expect($vencimiento)->not->toBeNull()
                ->and($vencimiento['titulo'])->toBe('PC-2026-01 — Simulacro del centro alternativo')
                ->and($vencimiento['estadoTono'])->toBe(EstadoPrueba::Planificada->tono())
                ->and($vencimiento['estadoEtiqueta'])->toBe(EstadoPrueba::Planificada->etiqueta())
                ->and($vencimiento['url'])->toBe("/continuidad/pruebas/{$vencimiento['id']}");
        });

    Carbon::setTestNow();
});

it('una prueba de continuidad vencida sale en rojo', function (): void {
    Carbon::setTestNow('2026-09-15');

    PruebaContinuidad::factory()->planificada()->create(['fecha_prevista' => '2026-09-10']);

    $this->actingAs($this->usuario)
        ->get('/calendario?mes=2026-09')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $vencimiento = collect($pagina->toArray()['props']['vencimientos'])
                ->firstWhere('fuente', 'prueba_continuidad');

            expect($vencimiento)->not->toBeNull()
                ->and($vencimiento['estadoTono'])->toBe('caducada')
                ->and($vencimiento['estadoEtiqueta'])->toBe('Sin realizar');
        });

    Carbon::setTestNow();
});

it('una prueba de continuidad ya realizada no sale en el calendario', function (): void {
    PruebaContinuidad::factory()->realizada()->create(['fecha_prevista' => '2026-09-10']);

    $this->actingAs($this->usuario)
        ->get('/calendario?mes=2026-09')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $fuentes = array_column($pagina->toArray()['props']['vencimientos'], 'fuente');

            expect($fuentes)->not->toContain('prueba_continuidad');
        });
});

it('un BIA en borrador no sale en el calendario, aunque conserve una fecha de revisión vieja', function (): void {
    // La misma fila que deja `CambiarEstadoBia` tras volver a borrador: el
    // estado cambia y `fecha_revision` se queda, a propósito. El calendario no
    // puede leer esa fecha como una revisión vigente de algo que ni está
    // aprobado.
    BiaServicio::factory()->create([
        'estado' => EstadoBia::Borrador->value,
        'fecha_revision' => '2026-09-10',
    ]);

    $this->actingAs($this->usuario)
        ->get('/calendario?mes=2026-09')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $fuentes = array_column($pagina->toArray()['props']['vencimientos'], 'fuente');

            expect($fuentes)->not->toContain('bia');
        });
});

it('un BIA aprobado sale en su fecha de revisión', function (): void {
    Carbon::setTestNow('2026-09-01');

    $bia = BiaServicio::factory()
        ->enEstado(EstadoBia::Aprobado)
        ->create(['fecha_revision' => '2026-09-20']);

    $this->actingAs($this->usuario)
        ->get('/calendario?mes=2026-09')
        ->assertInertia(function (AssertableInertia $pagina) use ($bia): void {
            $vencimiento = collect($pagina->toArray()['props']['vencimientos'])
                ->firstWhere('fuente', 'bia');

            expect($vencimiento)->not->toBeNull()
                ->and($vencimiento['titulo'])->toContain('BIA — ')
                ->and($vencimiento['estadoTono'])->toBe('implantado')
                ->and($vencimiento['estadoEtiqueta'])->toBe('Vigente')
                ->and($vencimiento['url'])->toBe("/continuidad/bia/{$bia->id}");
        });

    Carbon::setTestNow();
});

it('un usuario sin continuidad.ver no ve ninguna de las dos fuentes', function (): void {
    PruebaContinuidad::factory()->planificada()->create(['fecha_prevista' => '2026-09-10']);
    BiaServicio::factory()->enEstado(EstadoBia::Aprobado)->create(['fecha_revision' => '2026-09-12']);

    $rol = Role::query()
        ->where('name', Rol::ResponsableSeguridad->value)
        ->where('organizacion_id', $this->organizacion->id)
        ->firstOrFail();

    $rol->revokePermissionTo(Permiso::ContinuidadVer->value);

    app()->forgetInstance(PermissionRegistrar::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->usuario->fresh())
        ->get('/calendario?mes=2026-09')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $pagina): void {
            $fuentes = array_column($pagina->toArray()['props']['vencimientos'], 'fuente');

            expect($fuentes)->not->toContain('prueba_continuidad')
                ->and($fuentes)->not->toContain('bia');

            $opciones = collect($pagina->toArray()['props']['filtros'])
                ->firstWhere('clave', 'fuente')['opciones'];

            expect(array_column($opciones, 'valor'))
                ->not->toContain('prueba_continuidad')
                ->not->toContain('bia');
        });
});
