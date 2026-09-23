<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\Obligacion;
use App\Domain\Obligacion\RetirarCompromiso;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Lo que el módulo hacía mal
|--------------------------------------------------------------------------
|
| Ninguno de estos lo cazaba la suite: la primera versión del § 4.16 pasaba
| entera —1786 tests, Larastan, Pint, vue-tsc— con todos estos fallos dentro.
| Están juntos a propósito, para que se lea de dónde salieron.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

/**
 * Escribía el motivo en `notas`, que es un campo del formulario: retirar con
 * motivo **borraba lo que hubiera escrito** quien mantiene el compromiso.
 */
it('retirar guarda el motivo aparte y no toca las notas', function (): void {
    $compromiso = Compromiso::factory()->create(['notas' => 'Lo lleva el equipo de sistemas.']);

    app(RetirarCompromiso::class)($compromiso, 'El sistema se dio de baja en marzo.');

    expect($compromiso->fresh()?->notas)->toBe('Lo lleva el equipo de sistemas.')
        ->and($compromiso->fresh()?->motivo_retirada)->toBe('El sistema se dio de baja en marzo.')
        ->and($compromiso->fresh()?->activo)->toBeFalse();
});

/**
 * `proximaFecha()` consultaba la base aunque la relación estuviera cargada, y la
 * pintaban dos columnas: veinticinco filas eran cincuenta consultas de más.
 *
 * Se comprueba que **no crezca con las filas**, que es la definición del problema,
 * y no un número exacto —que cambiaría con cualquier `with()` nuevo y convertiría
 * este test en algo que hay que ir ajustando—.
 *
 * El registro de consultas se apaga mientras se siembra: las altas llevan su fila
 * de traza detrás y contarlas aquí mide el seeder, no la pantalla. Es lo que hacía
 * la primera versión de este test, que fallaba por el motivo equivocado.
 */
it('la tabla no consulta más por tener más filas', function (): void {
    $medir = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->usuario)->get('/obligaciones')->assertOk();

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $consultas;
    };

    Compromiso::factory()->count(3)->create();

    // La primera petición calienta la caché de permisos de spatie y sale más cara
    // que las siguientes; medirla como referencia daría un margen falso a favor.
    $medir();
    $conTres = $medir();

    Compromiso::factory()->count(12)->create();
    $conQuince = $medir();

    expect($conQuince)->toBeLessThanOrEqual($conTres, sprintf(
        'La tabla pasó de %d consultas con 3 filas a %d con 15: la próxima fecha se está '
        .'resolviendo por fila en vez de venir en el `select`.',
        $conTres,
        $conQuince,
    ));
});

it('ordena por lo que antes vence', function (): void {
    Compromiso::factory()->cada(12)->create(['titulo' => 'Zeta', 'computa_desde' => Carbon::today()->subMonths(11)]);
    Compromiso::factory()->cada(12)->create(['titulo' => 'Alfa', 'computa_desde' => Carbon::today()->subMonths(2)]);

    $this->actingAs($this->usuario)
        ->get('/obligaciones')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $titulos = array_column($pagina->toArray()['props']['filas'], 'titulo');

            // «Zeta» vence antes aunque alfabéticamente vaya la última.
            expect($titulos[0])->toBe('Zeta');
        });
});

/**
 * `codigo` es `nullable` en el request y `NOT NULL` en la base: el alta cubría el
 * nulo y la edición no, así que vaciar el campo daba un `QueryException`.
 */
it('editar con el código vacío propone uno en vez de reventar', function (): void {
    $compromiso = Compromiso::factory()->create();

    $this->actingAs($this->usuario)
        ->put("/obligaciones/{$compromiso->id}", [
            'codigo' => '',
            'titulo' => 'Sigue llamándose igual',
            'periodicidad_meses' => 12,
            'computa_desde' => Carbon::today()->subYear()->toDateString(),
        ])
        ->assertRedirect();

    expect($compromiso->fresh()?->codigo)->toStartWith('OBL-');
});

/**
 * La redirección nació dentro de `can:tareas.ver` por estar donde estaba la ruta
 * original, y eso le daba un 403 a quien tuviera `calendario.ver` sin
 * `tareas.ver` — el fallo exacto que el respaldo existe para evitar.
 */
it('la ruta vieja del calendario no exige el permiso de tareas', function (): void {
    $rol = Role::query()
        ->where('name', Rol::Tecnico->value)
        ->where('organizacion_id', $this->organizacion->id)
        ->firstOrFail();

    $rol->revokePermissionTo(Permiso::TareasVer->value);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $tecnico = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnico->fresh())
        ->get('/tareas/calendario?mes=2026-11')
        ->assertStatus(302)
        ->assertRedirectContains('/calendario');
});

it('quien sólo puede ver no recibe los botones de escritura', function (): void {
    Obligacion::factory()->create();

    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)
        ->get('/obligaciones')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('puedeGestionar', false));

    $this->actingAs($this->usuario)
        ->get('/obligaciones')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('puedeGestionar', true));
});

/**
 * El importador marca una obligación como no vigente cuando desaparece de una
 * revisión del marco, y eso significa que dejó de exigirse. `ObligacionesAplicables`
 * la filtraba, pero un POST directo la asumía igual.
 */
it('no se asume una obligación retirada del catálogo', function (): void {
    $retirada = Obligacion::factory()->retirada()->create();

    $this->actingAs($this->usuario)
        ->post("/obligaciones/asumir/{$retirada->id}")
        ->assertNotFound();

    expect(Compromiso::query()->count())->toBe(0);
});

it('se puede desasignar el responsable y el sistema', function (): void {
    $otro = User::factory()->create(['organizacion_id' => $this->organizacion->id]);
    $compromiso = Compromiso::factory()->create(['responsable_id' => $otro->id]);

    $this->actingAs($this->usuario)
        ->put("/obligaciones/{$compromiso->id}", [
            'codigo' => $compromiso->codigo,
            'titulo' => $compromiso->titulo,
            'periodicidad_meses' => 12,
            'computa_desde' => Carbon::today()->subYear()->toDateString(),
            // El centinela que manda el desplegable cuando se elige «Sin responsable».
            'responsable_id' => '__ninguno__',
            'sistema_id' => '__ninguno__',
        ])
        ->assertRedirect();

    expect($compromiso->fresh()?->responsable_id)->toBeNull()
        ->and($compromiso->fresh()?->sistema_id)->toBeNull();
});
