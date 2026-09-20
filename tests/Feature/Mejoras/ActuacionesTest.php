<?php

declare(strict_types=1);

use App\Domain\Mejora\Models\Mejora;
use App\Domain\Mejora\VincularActuacionDeMejora;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\ResumenPlanDeAccion;
use App\Http\Resources\Panel\Indicador as IndicadorPanel;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Lo que se va a hacer con una mejora, y el aislamiento
|--------------------------------------------------------------------------
|
| Dos cosas que pueden torcerse. La primera es aritmética y es la de siempre:
| **trece sitios del producto cuentan tareas** y un vínculo nuevo no puede mover
| ninguno.
|
| La segunda es de vocabulario, y es la que define el módulo: el origen de una
| actuación de mejora es `OrigenTarea::Mejora` y **no** `NoConformidad`. El
| reparto por origen del plan de acción existe para distinguir lo reactivo de lo
| voluntario, y colapsarlos haría que un plan lleno de mejoras se leyera como una
| organización apagando fuegos.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('abrir una actuación le pone el origen «mejora» y no el de acción correctiva', function (): void {
    $mejora = Mejora::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/mejoras/{$mejora->id}/actuaciones", [
            'titulo' => 'Montar el inventario automático',
            'prioridad' => 'media',
            // Se manda a propósito y tiene que ignorarse: lo pone el dominio.
            'origen' => OrigenTarea::NoConformidad->value,
        ])
        ->assertRedirect();

    $tarea = Tarea::query()->where('titulo', 'Montar el inventario automático')->firstOrFail();

    expect($tarea->origen)->toBe(OrigenTarea::Mejora)
        ->and($mejora->tareas()->count())->toBe(1);
});

it('vincular una actuación no mueve ninguna de las cifras del plan de acción', function (): void {
    $resumen = app(ResumenPlanDeAccion::class);

    $cifras = static fn (): array => collect([...$resumen->alertas(), ...$resumen->pendientesDeCompletar()])
        ->mapWithKeys(fn (IndicadorPanel $uno): array => [$uno->clave => $uno->valor])
        ->all();

    $tarea = Tarea::factory()->create();
    $mejora = Mejora::factory()->create();

    $antes = $cifras();

    app(VincularActuacionDeMejora::class)->vincular($mejora, $tarea, $this->usuario);

    expect($cifras())->toBe($antes)
        ->and(Tarea::query()->count())->toBe(1);
});

it('no ata la tarea a ninguna implantación: una mejora no es una brecha', function (): void {
    $tarea = Tarea::factory()->create();
    $mejora = Mejora::factory()->create();

    app(VincularActuacionDeMejora::class)->vincular($mejora, $tarea, $this->usuario);

    /*
     * La diferencia con `VincularAccionCorrectiva`, escrita como aserción: el
     * plan de adecuación lista lo que falta por implantar, y una medida que ya
     * está puesta y que además se puede hacer mejor no está pendiente de nada.
     * Atar el vínculo metería su coste en un plan que presupuesta brechas.
     */
    expect(DB::table('implantacion_tarea')->where('tarea_id', $tarea->id)->count())->toBe(0);
});

it('desvincular suelta el vínculo y deja la tarea en el plan de acción', function (): void {
    $mejora = Mejora::factory()->create();
    $tarea = Tarea::factory()->create();

    $vincular = app(VincularActuacionDeMejora::class);
    $vincular->vincular($mejora, $tarea, $this->usuario);
    $vincular->desvincular($mejora, $tarea);

    expect($mejora->tareas()->count())->toBe(0)
        ->and(Tarea::query()->whereKey($tarea->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Aislamiento
|--------------------------------------------------------------------------
|
| Lo que una organización ha apuntado que puede hacer mejor es un mapa de sus
| propias carencias escrito por ella misma. Y hay un segundo eje que las tres
| capas no tapan: entre dos mejoras de la MISMA organización lo único que separa
| es `scopeBindings()`.
|
*/

it('no lista ni deja abrir la mejora de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();

    Mejora::factory()->create(['codigo' => 'OM-PROPIA']);

    $suya = app(ContextoOrganizacion::class)->paraOrganizacion(
        $ajena,
        fn (): Mejora => Mejora::factory()->create(['codigo' => 'OM-AJENA']),
    );

    app(ContextoOrganizacion::class)->establecer($this->organizacion);

    expect(Mejora::query()->pluck('codigo')->all())->toBe(['OM-PROPIA']);

    $this->actingAs($this->usuario)->get("/mejoras/{$suya->id}")->assertNotFound();
    $this->actingAs($this->usuario)->delete("/mejoras/{$suya->id}")->assertNotFound();
});

it('no desvincula desde una mejora la actuación de otra', function (): void {
    $tarea = Tarea::factory()->create();

    $mia = Mejora::factory()->create();
    $otra = Mejora::factory()->create();

    app(VincularActuacionDeMejora::class)->vincular($otra, $tarea, $this->usuario);

    $this->actingAs($this->usuario)
        ->delete("/mejoras/{$mia->id}/actuaciones/{$tarea->id}")
        ->assertNotFound();

    expect($otra->tareas()->count())->toBe(1);
});
