<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Aislamiento multi-tenant. Prioridad 2 de cobertura (§11 del stack).
 *
 * Esta herramienta guarda el inventario de activos y las vulnerabilidades de sus
 * clientes: una consulta que cruce la frontera de organización no es un fallo de
 * funcionalidad, es un incidente de seguridad. De ahí las tres capas, y de ahí
 * que se prueben las tres por separado.
 */
beforeEach(function (): void {
    $this->marco = app(ContextoOrganizacion::class)->comoMantenimiento(
        fn (): Marco => Marco::factory()->create()
    );

    $this->requisito = Requisito::factory()->create(['marco_id' => $this->marco->id]);

    // Dos organizaciones con un sistema y una implantación cada una.
    $this->alfa = Organizacion::factory()->create(['nombre' => 'Organización Alfa']);
    $this->beta = Organizacion::factory()->create(['nombre' => 'Organización Beta']);

    $contexto = app(ContextoOrganizacion::class);

    foreach (['alfa', 'beta'] as $clave) {
        $contexto->establecer($this->{$clave});

        $sistema = Sistema::factory()->de($this->{$clave})->conMarco($this->marco)->create();
        ValoracionDimension::factory()->create(['sistema_id' => $sistema->id]);
        Implantacion::factory()->create([
            'sistema_id' => $sistema->id,
            'requisito_id' => $this->requisito->id,
        ]);

        $this->{$clave.'Sistema'} = $sistema;
    }

    $contexto->olvidar();
});

/*
|--------------------------------------------------------------------------
| Capa 1 y 2: organizacion_id y el global scope de Eloquent
|--------------------------------------------------------------------------
*/

it('el scope global no devuelve filas de otra organización', function (string $modelo): void {
    comoOrganizacion($this->alfa);

    $suyas = $modelo::query()->get();

    expect($suyas)->not->toBeEmpty()
        ->and($suyas->pluck('organizacion_id')->unique()->all())->toBe([$this->alfa->id]);
})->with([
    Sistema::class,
    ValoracionDimension::class,
    Implantacion::class,
]);

it('rellena organizacion_id solo al crear', function (): void {
    comoOrganizacion($this->beta);

    $sistema = Sistema::query()->create([
        'marco_id' => $this->marco->id,
        'codigo' => 'SIN-TENANT',
        'nombre' => 'Sistema sin tenant explícito',
    ]);

    expect($sistema->organizacion_id)->toBe($this->beta->id);
});

it('sin contexto no se ve nada', function (): void {
    sinOrganizacion();

    expect(Sistema::query()->count())->toBe(0)
        ->and(Implantacion::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Capa 3: Row Level Security
|--------------------------------------------------------------------------
|
| Es la que separa tener tres capas de decir que se tienen: las dos primeras
| viven en la aplicación y se esquivan con un `withoutGlobalScopes()` de más o
| un `DB::table()` en crudo. Ésta vive en PostgreSQL.
|
*/

it('una consulta en crudo tampoco ve la otra organización', function (): void {
    comoOrganizacion($this->alfa);

    // Sin Eloquent, sin scope, sin modelo: SQL directo contra la tabla.
    $filas = DB::table('implantaciones')->get();

    expect($filas)->toHaveCount(1)
        ->and((int) $filas->first()->organizacion_id)->toBe($this->alfa->id);
});

it('withoutGlobalScopes sigue sin cruzar la frontera', function (): void {
    comoOrganizacion($this->alfa);

    // Quitar el scope es justo lo que CLAUDE.md prohíbe fuera de mantenimiento.
    // Aunque alguien lo haga, queda la política de PostgreSQL.
    $todas = Sistema::query()->withoutGlobalScopes()->get();

    expect($todas)->toHaveCount(1)
        ->and($todas->first()->organizacion_id)->toBe($this->alfa->id);
});

it('sin contexto la base tampoco devuelve nada en crudo', function (): void {
    sinOrganizacion();

    expect(DB::table('sistemas')->count())->toBe(0)
        ->and(DB::table('implantaciones')->count())->toBe(0);
});

it('no deja escribir en nombre de otra organización', function (): void {
    comoOrganizacion($this->alfa);

    // El WITH CHECK de la política rechaza la inserción aunque la aplicación
    // ponga a mano el organizacion_id de otra.
    DB::table('sistemas')->insert([
        'organizacion_id' => $this->beta->id,
        'marco_id' => $this->marco->id,
        'codigo' => 'INTRUSO',
        'nombre' => 'Sistema de otra organización',
        'estado' => 'activo',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

/*
|--------------------------------------------------------------------------
| La puerta de mantenimiento
|--------------------------------------------------------------------------
*/

it('el modo mantenimiento sí atraviesa las tres capas', function (): void {
    $total = app(ContextoOrganizacion::class)->comoMantenimiento(
        fn (): int => Sistema::query()->count()
    );

    expect($total)->toBe(2);
});

it('el modo mantenimiento se cierra al salir, incluso si el callback lanza', function (): void {
    $contexto = app(ContextoOrganizacion::class);

    comoOrganizacion($this->alfa);

    try {
        $contexto->comoMantenimiento(function (): void {
            throw new RuntimeException('algo falló a mitad del mantenimiento');
        });
    } catch (RuntimeException) {
        // Esperado.
    }

    expect($contexto->enMantenimiento())->toBeFalse()
        ->and(Sistema::query()->count())->toBe(1);
});

it('el middleware web no abre nunca el modo mantenimiento', function (): void {
    $usuario = usuarioCon(organizacion: $this->beta);

    $this->actingAs($usuario)->get('/');

    $contexto = app(ContextoOrganizacion::class);

    expect($contexto->enMantenimiento())->toBeFalse()
        ->and($contexto->id())->toBe($this->beta->id);
});

it('un usuario sin organización se queda sin contexto y no ve nada', function (): void {
    $usuario = User::factory()->create(['organizacion_id' => null]);

    $this->actingAs($usuario)->get('/');

    expect(app(ContextoOrganizacion::class)->hayContexto())->toBeFalse()
        ->and(Sistema::query()->count())->toBe(0);
});

it('paraOrganizacion restaura la anterior al terminar', function (): void {
    $contexto = app(ContextoOrganizacion::class);

    comoOrganizacion($this->alfa);

    $vistosEnBeta = $contexto->paraOrganizacion(
        $this->beta,
        fn (): array => Sistema::query()->pluck('organizacion_id')->all()
    );

    expect($vistosEnBeta)->toBe([$this->beta->id])
        ->and($contexto->id())->toBe($this->alfa->id);
});
