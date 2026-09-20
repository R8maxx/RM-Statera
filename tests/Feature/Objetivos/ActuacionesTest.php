<?php

declare(strict_types=1);

use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Objetivo\VincularActuacion;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\ResumenPlanDeAccion;
use App\Http\Resources\Panel\Indicador as IndicadorPanel;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Qué se hará (6.2, planificación a)
|--------------------------------------------------------------------------
|
| Las actuaciones son tareas, y por eso el fallo caro de este módulo es
| aritmético: **trece sitios del producto cuentan tareas**, y un vínculo nuevo no
| puede mover ninguno de ellos. Es el mismo argumento que dejó las subtareas
| fuera de `tareas` y el que obliga a que el coste se cuente sobre tareas
| distintas.
|
| Y la otra mitad: **sin doble vínculo**, a diferencia de la acción correctiva
| del § 4.13. Allí hacía falta porque el plan de adecuación imprimía «sin trabajo
| planificado» sobre una medida que sí lo tenía; aquí no hay medida detrás por
| construcción, y atarla a una arbitraria sería inventarse una trazabilidad.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('abrir una actuación le pone el origen y no lo pregunta', function (): void {
    $objetivo = Objetivo::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/objetivos/{$objetivo->id}/actuaciones", [
            'titulo' => 'Contratar la herramienta de parcheo',
            'prioridad' => 'alta',
            // Se manda a propósito, y tiene que ignorarse: el origen lo pone el
            // dominio. Preguntarlo invita a cambiarlo.
            'origen' => OrigenTarea::Propia->value,
        ])
        ->assertRedirect();

    $tarea = Tarea::query()->where('titulo', 'Contratar la herramienta de parcheo')->firstOrFail();

    expect($tarea->origen)->toBe(OrigenTarea::Objetivo)
        ->and($tarea->estado)->toBe(EstadoTarea::Pendiente)
        ->and($objetivo->tareas()->count())->toBe(1);
});

it('el origen «objetivo» se ofrece porque su módulo existe', function (): void {
    /*
     * `disponibles()` decide qué orígenes tienen módulo detrás, no cuáles se
     * pueden teclear: `hallazgo`, `incidente` y `revisión por la dirección` están
     * fuera porque no hay nada a lo que apuntar. Éste entra desde hoy, así que
     * una tarea puede nacer suelta con este origen y vincularse después.
     */
    expect(OrigenTarea::Objetivo->disponible())->toBeTrue();

    $this->actingAs($this->usuario)
        ->post('/tareas', [
            'titulo' => 'Una tarea suelta',
            'prioridad' => 'media',
            'origen' => OrigenTarea::Objetivo->value,
        ])
        ->assertRedirect();

    // El origen es legítimo —la tarea puede haber nacido de un objetivo que se
    // vincula después—, así que lo que se comprueba es que el `CHECK` de la base
    // lo admite y no que la ruta lo rechace.
    expect(Tarea::query()->where('titulo', 'Una tarea suelta')->firstOrFail()->origen)
        ->toBe(OrigenTarea::Objetivo);
});

it('vincular una actuación no mueve ninguna de las cifras del plan de acción', function (): void {
    $resumen = app(ResumenPlanDeAccion::class);

    $cifras = static fn (): array => collect([...$resumen->alertas(), ...$resumen->pendientesDeCompletar()])
        ->mapWithKeys(fn (IndicadorPanel $uno): array => [$uno->clave => $uno->valor])
        ->all();

    $tarea = Tarea::factory()->create();
    $objetivo = Objetivo::factory()->create();

    $antes = $cifras();

    app(VincularActuacion::class)->vincular($objetivo, $tarea, $this->usuario);

    // Ni una sola: todas cuentan filas de `tareas`, y vincular no crea ninguna.
    expect($cifras())->toBe($antes)
        ->and(Tarea::query()->count())->toBe(1);
});

it('no ata la tarea a ninguna implantación: aquí no hay medida detrás', function (): void {
    $tarea = Tarea::factory()->create();
    $objetivo = Objetivo::factory()->create();

    app(VincularActuacion::class)->vincular($objetivo, $tarea, $this->usuario);

    /*
     * La diferencia con `VincularAccionCorrectiva`, escrita como aserción: un
     * objetivo no cuelga de ningún requisito, así que forzar un vínculo contra
     * una implantación arbitraria sería inventarse trazabilidad — y de paso
     * metería su coste en el presupuesto del plan de adecuación, que presupuesta
     * medidas del Anexo II.
     */
    expect(DB::table('implantacion_tarea')->where('tarea_id', $tarea->id)->count())->toBe(0)
        ->and(Implantacion::query()->count())->toBe(0);
});

it('desvincular suelta el vínculo y deja la tarea en el plan de acción', function (): void {
    $objetivo = Objetivo::factory()->create();
    $tarea = Tarea::factory()->create();

    $vincular = app(VincularActuacion::class);
    $vincular->vincular($objetivo, $tarea, $this->usuario);
    $vincular->desvincular($objetivo, $tarea);

    expect($objetivo->tareas()->count())->toBe(0)
        ->and(Tarea::query()->whereKey($tarea->id)->exists())->toBeTrue();
});

it('la misma actuación puede empujar dos objetivos', function (): void {
    $tarea = Tarea::factory()->create();
    $uno = Objetivo::factory()->create();
    $otro = Objetivo::factory()->create();

    $vincular = app(VincularActuacion::class);
    $vincular->vincular($uno, $tarea, $this->usuario);
    $vincular->vincular($otro, $tarea, $this->usuario);

    expect(DB::table('objetivo_tarea')->where('tarea_id', $tarea->id)->count())->toBe(2)
        ->and(Tarea::query()->count())->toBe(1);
});
