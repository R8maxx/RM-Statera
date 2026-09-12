<?php

declare(strict_types=1);

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\ResumenPlanDeAccion;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\Reparto;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Las cifras del plan de acción
|--------------------------------------------------------------------------
|
| El error caro no es contar de menos: es contar de más. Una cifra que dice 12
| y una lista que enseña 9 rompen la confianza en el panel entero, y a partir de
| ahí da igual lo bien que esté contado el resto.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->resumen = app(ResumenPlanDeAccion::class);

    $this->valor = function (string $clave): int {
        $indicador = collect([...$this->resumen->alertas(), ...$this->resumen->pendientesDeCompletar()])
            ->first(fn (Indicador $uno): bool => $uno->clave === $clave);

        expect($indicador)->not->toBeNull("No existe el indicador `{$clave}`.");

        return $indicador->valor;
    };
});

it('cuenta como vencida la que se pasó de fecha y sigue abierta', function (): void {
    Tarea::factory()->vencida(3)->create();

    // Cerrada con el plazo pasado: no vence, está cerrada.
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->paraElDia(Carbon::today()->subMonth())->create();

    // Abierta y en plazo.
    Tarea::factory()->paraElDia(Carbon::today()->addWeek())->create();

    expect(($this->valor)('vencidas'))->toBe(1);
});

it('cuenta las bloqueadas aparte de las que no ha cogido nadie', function (): void {
    Tarea::factory()->enEstado(EstadoTarea::Bloqueada)->create();
    Tarea::factory()->count(2)->create();

    expect(($this->valor)('bloqueadas'))->toBe(1)
        ->and(($this->valor)('sin_responsable'))->toBe(3);
});

it('una tarea sin plazo no vence, y por eso se cuenta aparte', function (): void {
    Tarea::factory()->count(2)->create(['fecha_limite' => null]);
    Tarea::factory()->paraElDia(Carbon::today()->addDays(10))->create();

    expect(($this->valor)('sin_plazo'))->toBe(2)
        ->and(($this->valor)('vencidas'))->toBe(0)
        ->and(($this->valor)('por_vencer'))->toBe(1);
});

it('lo cerrado no aparece en ningún indicador', function (): void {
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->paraElDia(Carbon::today()->subMonth())->create();
    Tarea::factory()->enEstado(EstadoTarea::Descartada)->create(['fecha_limite' => null]);

    foreach ([...$this->resumen->alertas(), ...$this->resumen->pendientesDeCompletar()] as $indicador) {
        expect($indicador->valor)->toBe(0, "El indicador `{$indicador->clave}` cuenta una tarea cerrada.");
    }
});

/*
|--------------------------------------------------------------------------
| Cada cifra tiene que llevar a su lista
|--------------------------------------------------------------------------
*/

it('el filtro de cada indicador devuelve exactamente su cifra', function (): void {
    Tarea::factory()->vencida(5)->create(['titulo' => 'Vencida']);
    Tarea::factory()->enEstado(EstadoTarea::Bloqueada)->create(['titulo' => 'Bloqueada']);
    Tarea::factory()->paraElDia(Carbon::today()->addDays(9))->create(['titulo' => 'Vence pronto']);
    Tarea::factory()->create(['titulo' => 'Sin nada', 'fecha_limite' => null]);
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->create(['titulo' => 'Hecha']);

    foreach ([...$this->resumen->alertas(), ...$this->resumen->pendientesDeCompletar()] as $indicador) {
        expect($indicador->base)->toBe('/tareas');

        $this->actingAs($this->usuario)
            ->get($indicador->base.'?'.$indicador->filtro)
            ->assertInertia(
                fn (AssertableInertia $pagina) => $pagina->has('filas', $indicador->valor),
                "El filtro de `{$indicador->clave}` no coincide con su indicador.",
            );
    }
});

it('el filtro de cada tramo del reparto devuelve exactamente su tramo', function (): void {
    Tarea::factory()->count(2)->create();
    Tarea::factory()->enEstado(EstadoTarea::EnCurso)->create();
    Tarea::factory()->conPrioridad(PrioridadTarea::Critica)->create();

    $panel = $this->resumen->paraElPanel();

    foreach ([...$panel->porEstado, ...$panel->porPrioridad] as $tramo) {
        /** @var Reparto $tramo */
        $this->actingAs($this->usuario)
            ->get('/tareas?'.$tramo->filtro.'&filter[abiertas]=1')
            ->assertInertia(
                fn (AssertableInertia $pagina) => $pagina->has('filas', $tramo->valor),
                "El filtro del tramo `{$tramo->clave}` no coincide con su cifra.",
            );
    }
});

/*
|--------------------------------------------------------------------------
| El panel
|--------------------------------------------------------------------------
*/

it('lo abierto viaja siempre con su denominador', function (): void {
    Tarea::factory()->count(3)->create();
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->create();

    $panel = $this->resumen->paraElPanel();

    expect($panel->abiertas)->toBe(3)
        ->and($panel->total)->toBe(4);
});

it('el reparto por estado no pinta los tramos vacíos', function (): void {
    Tarea::factory()->count(2)->create();

    $tramos = $this->resumen->paraElPanel()->porEstado;

    expect($tramos)->toHaveCount(1)
        ->and($tramos[0]->clave)->toBe(EstadoTarea::Pendiente->value);
});

it('el reparto por prioridad va de lo que más corre a lo que menos', function (): void {
    Tarea::factory()->conPrioridad(PrioridadTarea::Baja)->create();
    Tarea::factory()->conPrioridad(PrioridadTarea::Critica)->create();
    Tarea::factory()->conPrioridad(PrioridadTarea::Media)->create();

    $claves = array_map(
        static fn (Reparto $tramo): string => $tramo->clave,
        $this->resumen->paraElPanel()->porPrioridad,
    );

    // Sin «alta»: los tramos a cero no se pintan.
    expect($claves)->toBe(['critica', 'media', 'baja']);
});

it('no cuenta las tareas de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    Tarea::factory()->vencida()->count(4)->create();

    comoOrganizacion($this->organizacion);
    Tarea::factory()->vencida()->create();

    expect(($this->valor)('vencidas'))->toBe(1)
        ->and($this->resumen->paraElPanel()->total)->toBe(1);
});
