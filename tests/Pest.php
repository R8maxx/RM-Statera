<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
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
| Gotenberg
|--------------------------------------------------------------------------
|
| Casi todos los tests de documentos usan el doble `GotenbergFalso`: probar el
| HTML es probar el documento, y Gotenberg sólo es la impresora. El único que
| habla con el contenedor de verdad es el de integración, y se salta si no está
| levantado para que la suite corra en cualquier máquina.
|
*/

function gotenbergDisponible(): bool
{
    $url = (string) config('services.gotenberg.url');

    $socket = @fsockopen(
        (string) (parse_url($url, PHP_URL_HOST) ?: '127.0.0.1'),
        (int) (parse_url($url, PHP_URL_PORT) ?: 3000),
        $errno,
        $error,
        0.3,
    );

    if ($socket === false) {
        return false;
    }

    fclose($socket);

    return true;
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

/*
|--------------------------------------------------------------------------
| Usuarios con rol
|--------------------------------------------------------------------------
|
| Desde que las rutas van con `can:`, un usuario sin rol no puede hacer nada:
| es el comportamiento correcto y no un estorbo del test. Por eso el alta normal
| de un usuario en un test pasa por aquí y lleva rol.
|
| Los roles son POR ORGANIZACIÓN (`teams = true` con `organizacion_id`), así que
| hay que sembrarlos y asignarlos dentro del contexto de la suya.
|
*/

function usuarioCon(Rol $rol = Rol::ResponsableSeguridad, Organizacion|int|null $organizacion = null): User
{
    $contexto = app(ContextoOrganizacion::class);

    $organizacion = match (true) {
        $organizacion instanceof Organizacion => $organizacion,
        is_int($organizacion) => Organizacion::query()->findOrFail($organizacion),
        default => Organizacion::query()->findOrFail($contexto->idObligatorio()),
    };

    return $contexto->paraOrganizacion($organizacion, function () use ($organizacion, $rol): User {
        app(SembrarRoles::class)->paraOrganizacion($organizacion);

        $usuario = User::factory()->create(['organizacion_id' => $organizacion->id]);
        $usuario->syncRoles([$rol->value]);

        return $usuario->fresh() ?? $usuario;
    });
}

/*
|--------------------------------------------------------------------------
| Recargas parciales
|--------------------------------------------------------------------------
|
| Los props opcionales (`Inertia::optional()`) sólo viajan cuando el cliente los
| pide con `router.reload({ only: [...] })`. Reproducirlo en un test es cuestión
| de cabeceras, y la de versión tiene que ser la buena: si no coincide, el
| middleware responde 409 y el test falla diciendo que la respuesta no es de
| Inertia, que no ayuda nada.
|
| `Inertia::getVersion()` sólo devuelve el valor real después de que el
| middleware haya corrido, así que este helper se usa SIEMPRE tras una primera
| petición normal a la misma página.
|
| Y sobre la respuesta: una recarga parcial devuelve JSON, no la vista con el
| objeto `page`, así que `assertInertia` no sirve —falla con «Not a valid
| Inertia response»—. Se afirma con `assertJsonPath('props.…')`.
|
*/

/**
 * @param  list<string>  $solo  Los props que se piden.
 * @return array<string, string>
 */
function recargaParcial(string $componente, array $solo): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) Inertia::getVersion(),
        'X-Inertia-Partial-Component' => $componente,
        'X-Inertia-Partial-Data' => implode(',', $solo),
    ];
}

/*
|--------------------------------------------------------------------------
| La definición de un recurso
|--------------------------------------------------------------------------
|
| Los filtros viajan como lista, y afirmar sobre `recurso.filtros.2` ata el
| test al orden de declaración: añadir un filtro al principio rompía tres
| pruebas que no tenían nada que ver. Se busca por clave, que es lo que de
| verdad importa.
|
*/

/**
 * @return array<string, mixed>
 */
function filtroDeclarado(AssertableInertia $pagina, string $clave): array
{
    /** @var array<int, array<string, mixed>> $filtros */
    $filtros = data_get($pagina->toArray(), 'props.recurso.filtros', []);

    $filtro = collect($filtros)->firstWhere('clave', $clave);

    expect($filtro)->not->toBeNull("El recurso no declara ningún filtro `{$clave}`.");

    return $filtro;
}
