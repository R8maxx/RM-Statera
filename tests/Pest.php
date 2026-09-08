<?php

declare(strict_types=1);

use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Caso base
|--------------------------------------------------------------------------
|
| Toda la suite corre sobre PostgreSQL (base statera_test), nunca sobre
| SQLite: el esquema usa CTEs recursivas, JSONB con índices GIN y RLS.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // `set_config(..., false)` es de sesión, no de transacción: sobrevive al
    // rollback de RefreshDatabase. Se limpia antes de cada test para que ninguno
    // herede la organización del anterior y para que el punto de partida sea
    // siempre el de denegar por defecto.
    ->beforeEach(fn () => app(ContextoOrganizacion::class)->olvidar())
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectativas del dominio
|--------------------------------------------------------------------------
*/

expect()->extend('toBeCodigoRequisito', function () {
    expect($this->value)->toMatch('/^[A-Za-z0-9]+(\.[A-Za-z0-9]+)*$/');

    return $this;
});

/*
|--------------------------------------------------------------------------
| Ayudas del motor de categorización
|--------------------------------------------------------------------------
|
| La valoración de las cinco dimensiones es la entrada de casi todo test del
| motor, y escribirla entera cada vez esconde lo que el test quiere decir.
|
*/

/**
 * @param  array<string, NivelDimension|string>  $niveles  Indexado por código de dimensión (C, I, D, A, T).
 */
function valoracion(array $niveles): ValoracionDimensiones
{
    return ValoracionDimensiones::desdeArray($niveles);
}

/** Las cinco dimensiones al mismo nivel. */
function uniforme(NivelDimension $nivel): ValoracionDimensiones
{
    return new ValoracionDimensiones($nivel, $nivel, $nivel, $nivel, $nivel);
}

/*
|--------------------------------------------------------------------------
| Contexto de organización
|--------------------------------------------------------------------------
|
| Con Row Level Security activo, sin contexto no se ve ni se escribe ninguna
| fila de datos propios: la política deniega por defecto. Por eso todo test que
| toque datos de una organización tiene que declararla, y por eso el helper es
| la forma normal de empezar uno.
|
*/

function comoOrganizacion(Organizacion|int|null $organizacion = null): Organizacion
{
    $organizacion = match (true) {
        $organizacion instanceof Organizacion => $organizacion,
        is_int($organizacion) => Organizacion::query()->findOrFail($organizacion),
        default => Organizacion::factory()->create(),
    };

    app(ContextoOrganizacion::class)->establecer($organizacion);

    return $organizacion;
}

function sinOrganizacion(): void
{
    app(ContextoOrganizacion::class)->olvidar();
}
