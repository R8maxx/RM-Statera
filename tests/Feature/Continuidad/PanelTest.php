<?php

declare(strict_types=1);

use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Panel\AlertasDelPanel;
use App\Http\Resources\Panel\Indicador;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| El rojo de continuidad en el panel
|--------------------------------------------------------------------------
|
| `RegistroContinuidad` es el que sostiene la tira roja del panel para el BIA
| y las pruebas: una prueba vencida y un BIA con la revisión vencida son
| incumplimientos de verdad, y `rto_incoherente` no lo es —es una
| contradicción que corregir, no un plazo ya incumplido—.
|
*/

beforeEach(function (): void {
    comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->alertas = app(AlertasDelPanel::class);
});

it('una prueba vencida sube al panel', function (): void {
    PruebaContinuidad::factory()->create([
        'estado' => EstadoPrueba::Planificada->value,
        'fecha_prevista' => Carbon::today()->subDay(),
    ]);

    $claves = array_map(
        static fn (Indicador $alerta): string => $alerta->clave,
        ($this->alertas)($this->usuario),
    );

    expect($claves)->toContain('vencidas');
});

it('una prueba planificada todavía en fecha no sube al panel', function (): void {
    PruebaContinuidad::factory()->create([
        'estado' => EstadoPrueba::Planificada->value,
        'fecha_prevista' => Carbon::today()->addDays(30),
    ]);

    expect(($this->alertas)($this->usuario))->toBe([]);
});

it('un BIA aprobado con la revisión vencida sube al panel', function (): void {
    BiaServicio::factory()
        ->enEstado(EstadoBia::Aprobado)
        ->create(['fecha_revision' => Carbon::today()->subDay()]);

    $claves = array_map(
        static fn (Indicador $alerta): string => $alerta->clave,
        ($this->alertas)($this->usuario),
    );

    expect($claves)->toContain('revision_vencida');
});

it('un BIA en borrador con la revisión vencida no sube al panel', function (): void {
    BiaServicio::factory()
        ->enEstado(EstadoBia::Borrador)
        ->create(['fecha_revision' => Carbon::today()->subDay()]);

    expect(($this->alertas)($this->usuario))->toBe([]);
});

it('sin continuidad.ver no sube nada de continuidad', function (): void {
    PruebaContinuidad::factory()->create([
        'estado' => EstadoPrueba::Planificada->value,
        'fecha_prevista' => Carbon::today()->subDay(),
    ]);
    BiaServicio::factory()
        ->enEstado(EstadoBia::Aprobado)
        ->create(['fecha_revision' => Carbon::today()->subDay()]);

    $this->usuario->roles->first()?->revokePermissionTo('continuidad.ver');

    $claves = array_map(
        static fn (Indicador $alerta): string => $alerta->clave,
        ($this->alertas)($this->usuario->fresh()),
    );

    expect($claves)->not->toContain('vencidas')
        ->and($claves)->not->toContain('revision_vencida');
});
