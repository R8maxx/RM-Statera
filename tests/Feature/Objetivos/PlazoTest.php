<?php

declare(strict_types=1);

use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Objetivo\PlazoObjetivo;
use App\Domain\Objetivo\VincularIndicador;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Para cuándo (6.2, planificación d)
|--------------------------------------------------------------------------
|
| El plazo de la ficha se cuenta en el servidor y desde la firma, no desde el
| alta: un borrador no compromete a nadie. Lo que puede torcerse es contar
| desde el día equivocado, seguir corriendo tras el cierre o dibujar una barra
| para algo que nadie ha aprobado.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    Carbon::setTestNow('2026-09-30 10:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('cuenta desde la firma hasta hoy mientras está aprobado', function (): void {
    $objetivo = Objetivo::factory()
        ->enEstado(EstadoObjetivo::Aprobado, $this->usuario->id, Carbon::parse('2026-09-23 15:10'))
        ->create(['fecha_objetivo' => '2027-01-23']);

    expect(app(PlazoObjetivo::class)->de($objetivo))->toBe([
        'dias' => 122,
        'detectada' => '2026-09-23',
        'limite' => '2027-01-23',
        'fin' => '2026-09-30',
        'finRotulo' => 'Hoy',
        'corre' => true,
        'transcurridos' => 7,
        'fuera' => 0,
    ]);
});

it('cuenta los días fuera de plazo de un aprobado que nadie cerró', function (): void {
    $objetivo = Objetivo::factory()
        ->enEstado(EstadoObjetivo::Aprobado, $this->usuario->id, Carbon::parse('2026-06-01'))
        ->create(['fecha_objetivo' => '2026-09-15']);

    $plazo = app(PlazoObjetivo::class)->de($objetivo);

    expect($plazo['corre'])->toBeTrue()
        ->and($plazo['fuera'])->toBe(15);
});

it('deja de correr el día del cierre', function (): void {
    $objetivo = Objetivo::factory()
        ->enEstado(EstadoObjetivo::Alcanzado, $this->usuario->id, Carbon::parse('2026-08-01'))
        ->create(['fecha_objetivo' => '2026-12-31', 'aprobado_en' => '2026-05-01 09:00']);

    $plazo = app(PlazoObjetivo::class)->de($objetivo);

    expect($plazo['corre'])->toBeFalse()
        ->and($plazo['fin'])->toBe('2026-08-01')
        ->and($plazo['finRotulo'])->toBe('Alcanzado')
        ->and($plazo['transcurridos'])->toBe(92)
        ->and($plazo['fuera'])->toBe(0);
});

it('no dibuja plazo para un borrador, aunque ya lleve fecha', function (): void {
    $objetivo = Objetivo::factory()->create(['fecha_objetivo' => '2027-01-23']);

    expect(app(PlazoObjetivo::class)->de($objetivo))->toBeNull();
});

it('la ficha manda el plazo y la serie de cada indicador', function (): void {
    $objetivo = Objetivo::factory()
        ->enEstado(EstadoObjetivo::Aprobado, $this->usuario->id, Carbon::parse('2026-09-23'))
        ->create();

    $indicador = Indicador::factory()->create();
    Medicion::factory()->for($indicador)->con(63, 60)->create();
    app(VincularIndicador::class)->vincular($objetivo, $indicador, $this->usuario);

    $this->actingAs($this->usuario)
        ->get("/objetivos/{$objetivo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('plazo.corre', true)
            ->where('plazo.detectada', '2026-09-23')
            ->has('indicadores.0.serie', 1)
            ->has('indicadores.0.fraccion')
            ->has('actuaciones'));
});
