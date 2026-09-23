<?php

declare(strict_types=1);

use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Documento\Models\Documento;
use App\Domain\Obligacion\Excepciones\CumplimientoInvalido;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\RegistrarCumplimiento;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| El histórico de cumplimiento
|--------------------------------------------------------------------------
|
| Invariante 7: la pregunta del auditor no es «¿se hace?», es «¿desde cuándo?».
| Lo que este registro tiene que sostener es esa segunda, y de ahí la pieza que
| más se puede estropear: **`cubre_hasta` se congela** con la cadencia vigente
| cuando se registró.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('sin cumplimientos, la próxima fecha es computa_desde más la cadencia', function (): void {
    $compromiso = Compromiso::factory()->cada(12)->create(['computa_desde' => '2026-03-14']);

    expect($compromiso->proximaFecha()->toDateString())->toBe('2027-03-14');
});

it('registrar un cumplimiento mueve la próxima fecha una cadencia', function (): void {
    $compromiso = Compromiso::factory()->cada(24)->create(['computa_desde' => '2025-01-10']);

    app(RegistrarCumplimiento::class)($compromiso, Carbon::parse('2026-06-01'));

    expect($compromiso->fresh()?->proximaFecha()->toDateString())->toBe('2028-06-01');
});

/**
 * **El test que sostiene el módulo.** Subir la cadencia de anual a semestral en
 * marzo no puede repintar como fuera de plazo un cumplimiento de enero que en
 * enero estaba al día. Es la misma familia que
 * `documento_versiones.fecha_proxima_revision`, que se calcula al firmar.
 */
it('la cobertura se congela con la cadencia de entonces', function (): void {
    $compromiso = Compromiso::factory()->cada(24)->create(['computa_desde' => '2024-01-01']);

    $viejo = app(RegistrarCumplimiento::class)($compromiso, Carbon::parse('2025-01-01'));

    expect($viejo->cubre_hasta->toDateString())->toBe('2027-01-01');

    $compromiso->update(['periodicidad_meses' => 6]);

    // El de enero no se ha movido…
    expect($viejo->fresh()?->cubre_hasta->toDateString())->toBe('2027-01-01');

    // …y el siguiente sí usa la cadencia nueva.
    $nuevo = app(RegistrarCumplimiento::class)($compromiso->fresh(), Carbon::parse('2026-01-01'));

    expect($nuevo->cubre_hasta->toDateString())->toBe('2026-07-01');
});

it('no se registra un cumplimiento con fecha futura', function (): void {
    $compromiso = Compromiso::factory()->create();

    expect(fn () => app(RegistrarCumplimiento::class)($compromiso, Carbon::today()->addDay()))
        ->toThrow(CumplimientoInvalido::class);
});

it('la base rechaza una cobertura que no sea posterior al cumplimiento', function (): void {
    $compromiso = Compromiso::factory()->create();

    expect(fn () => $compromiso->cumplimientos()->create([
        'fecha' => '2026-05-01',
        'cubre_hasta' => '2026-05-01',
    ]))->toThrow(QueryException::class);
});

it('la base rechaza dos referencias a la vez', function (): void {
    $compromiso = Compromiso::factory()->create();
    $auditoria = Auditoria::factory()->create();
    $documento = Documento::factory()->politica()->create();

    expect(fn () => $compromiso->cumplimientos()->create([
        'fecha' => '2026-05-01',
        'cubre_hasta' => '2027-05-01',
        'auditoria_id' => $auditoria->id,
        'documento_id' => $documento->id,
    ]))->toThrow(QueryException::class);
});

/**
 * `nullOnDelete` y no `cascade`: borrar la auditoría no puede llevarse por
 * delante la prueba de que la obligación se cumplió. Mismo criterio que
 * `mejoras.hallazgo_id`.
 */
it('borrar la auditoría enlazada deja el cumplimiento en pie', function (): void {
    $compromiso = Compromiso::factory()->create();
    $auditoria = Auditoria::factory()->create();

    $cumplimiento = app(RegistrarCumplimiento::class)(
        $compromiso,
        Carbon::today()->subMonth(),
        ['auditoria_id' => $auditoria->id],
    );

    $auditoria->delete();

    expect($cumplimiento->fresh())->not->toBeNull()
        ->and($cumplimiento->fresh()?->auditoria_id)->toBeNull();
});

it('borrar el cumplimiento devuelve la próxima fecha a la que había', function (): void {
    $compromiso = Compromiso::factory()->cada(12)->create(['computa_desde' => '2026-01-01']);

    $cumplimiento = app(RegistrarCumplimiento::class)($compromiso, Carbon::parse('2026-06-01'));

    expect($compromiso->fresh()?->proximaFecha()->toDateString())->toBe('2027-06-01');

    $cumplimiento->delete();

    expect($compromiso->fresh()?->proximaFecha()->toDateString())->toBe('2027-01-01');
});

it('el cumplimiento de un compromiso no se resuelve desde otro', function (): void {
    $suyo = Compromiso::factory()->create();
    $ajeno = Compromiso::factory()->create();

    $cumplimiento = app(RegistrarCumplimiento::class)($ajeno, Carbon::today()->subMonth());

    // `scopeBindings()`: el cumplimiento de otro compromiso no se resuelve desde éste.
    $this->actingAs($this->usuario)
        ->delete("/obligaciones/{$suyo->id}/cumplimientos/{$cumplimiento->id}")
        ->assertNotFound();

    expect($cumplimiento->fresh())->not->toBeNull();
});
