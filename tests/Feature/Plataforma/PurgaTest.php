<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Persona\Models\Persona;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\EstadoExportacion;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Plataforma\Models\ExportacionOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;

/*
|--------------------------------------------------------------------------
| El borrado definitivo de un cliente, sólo por consola (punto 57)
|--------------------------------------------------------------------------
|
| La aplicación no puede borrar una organización: la cascada atravesaría RLS y
| los REVOKE de la traza. Sólo la función `purgar_organizacion()`, y sólo
| pasados noventa días de baja.
|
*/

beforeEach(function (): void {
    foreach (['adjuntos', 'evidencias', 'documentos'] as $disco) {
        Storage::fake($disco);
    }

    $this->organizacion = comoOrganizacion();
    $this->organizacion->forceFill(['cif' => 'B12345674'])->save();
    Sistema::factory()->create();
    Persona::factory()->create();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    Storage::disk('evidencias')->put("{$this->organizacion->id}/2026/acta.pdf", 'acta');
    Storage::disk('adjuntos')->put("{$this->organizacion->id}/cv.pdf", 'cv');

    $this->otra = comoOrganizacion();
    Sistema::factory()->create(['nombre' => 'Sistema de la otra']);
    $this->deLaOtra = usuarioCon(Rol::ResponsableSeguridad);
    Storage::disk('evidencias')->put("{$this->otra->id}/2026/ajena.pdf", 'ajena');

    // Un administrador de la plataforma que además es miembro (punto 45).
    $this->miembro = User::factory()->plataforma()->create();
    $this->miembro->forceFill(['organizacion_id' => $this->organizacion->id])->save();
    sinOrganizacion();
});

function deBajaHace(Organizacion $organizacion, int $dias): void
{
    app(ContextoOrganizacion::class)->paraOrganizacion($organizacion, function () use ($organizacion, $dias): void {
        $organizacion->forceFill(['activa' => false, 'baja_en' => now()->subDays($dias), 'motivo_baja' => 'Fin del contrato'])->save();
    });
}

/** @return array<string, int> las tablas con filas de esa organización */
function filasDe(int $organizacionId): array
{
    $contexto = app(ContextoOrganizacion::class);
    $contexto->establecer($organizacionId);

    $tablas = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('column_name', 'organizacion_id')
        ->pluck('table_name');

    $filas = [];

    foreach ($tablas as $tabla) {
        $total = DB::table($tabla)->where('organizacion_id', $organizacionId)->count();

        if ($total > 0) {
            $filas[$tabla] = $total;
        }
    }

    $contexto->olvidar();

    return $filas;
}

function purgar(object $test, array $opciones = []): PendingCommand
{
    return $test->artisan('organizaciones:purgar', ['organizacion' => $test->organizacion->id, ...$opciones]);
}

it('se niega antes de noventa días de baja, y con la organización activa', function (): void {
    purgar($this, ['--sin-exportacion' => true])->assertFailed();

    deBajaHace($this->organizacion, 89);
    purgar($this, ['--sin-exportacion' => true])->assertFailed();

    expect(Organizacion::query()->whereKey($this->organizacion->id)->exists())->toBeTrue();
});

it('la simulación cuenta también antes del plazo, y dice desde cuándo se podrá', function (): void {
    deBajaHace($this->organizacion, 10);

    purgar($this, ['--dry-run' => true])
        ->expectsOutputToContain('Todavía no se puede purgar')
        ->expectsOutputToContain('Simulación')
        ->assertSuccessful();

    expect(Organizacion::query()->whereKey($this->organizacion->id)->exists())->toBeTrue();
});

it('en simulación cuenta y no toca nada', function (): void {
    deBajaHace($this->organizacion, 91);
    $antes = filasDe($this->organizacion->id);

    purgar($this, ['--dry-run' => true])
        ->expectsOutputToContain('Simulación')
        ->assertSuccessful();

    expect(filasDe($this->organizacion->id))->toBe($antes)
        ->and(Storage::disk('evidencias')->exists("{$this->organizacion->id}/2026/acta.pdf"))->toBeTrue();
});

it('exige una exportación posterior a la baja, o decirlo explícitamente', function (): void {
    deBajaHace($this->organizacion, 91);

    purgar($this)->assertFailed();

    // Una exportación de antes de la baja no vale.
    ExportacionOrganizacion::query()->create([
        'organizacion_afectada_id' => $this->organizacion->id,
        'solicitada_por' => $this->miembro->id,
        'estado' => EstadoExportacion::Lista->value,
        'solicitada_en' => now()->subDays(100),
        'generada_en' => now()->subDays(100),
        'caduca_en' => now()->subDays(93),
    ]);
    purgar($this)->assertFailed();

    ExportacionOrganizacion::query()->create([
        'organizacion_afectada_id' => $this->organizacion->id,
        'solicitada_por' => $this->miembro->id,
        'estado' => EstadoExportacion::Lista->value,
        'solicitada_en' => now()->subDays(2),
        'generada_en' => now()->subDays(2),
        'caduca_en' => now()->addDays(5),
    ]);
    purgar($this)
        ->expectsQuestion('Esto no se puede deshacer. Escribe «B12345674» para purgar', 'B12345674')
        ->assertSuccessful();
});

it('no purga si no se teclea el CIF', function (): void {
    deBajaHace($this->organizacion, 91);

    purgar($this, ['--sin-exportacion' => true])
        ->expectsQuestion('Esto no se puede deshacer. Escribe «B12345674» para purgar', 'sí')
        ->assertFailed();

    expect(Organizacion::query()->whereKey($this->organizacion->id)->exists())->toBeTrue()
        ->and(filasDe($this->organizacion->id))->not->toBe([]);
});

it('no deja ni una fila de la organización, y la otra sigue entera', function (): void {
    deBajaHace($this->organizacion, 91);
    $id = $this->organizacion->id;
    $laOtra = filasDe($this->otra->id);

    expect(filasDe($id))->toHaveKeys(['sistemas', 'personas', 'eventos_auditoria']);

    purgar($this, ['--sin-exportacion' => true])
        ->expectsQuestion('Esto no se puede deshacer. Escribe «B12345674» para purgar', 'B12345674')
        ->assertSuccessful();

    expect(filasDe($id))->toBe([])
        ->and(Organizacion::query()->whereKey($id)->exists())->toBeFalse()
        ->and(DB::table('roles')->where('organizacion_id', $id)->exists())->toBeFalse()
        ->and(DB::table('model_has_roles')->where('organizacion_id', $id)->exists())->toBeFalse()
        ->and(filasDe($this->otra->id))->toBe($laOtra)
        ->and(Storage::disk('evidencias')->exists("{$id}/2026/acta.pdf"))->toBeFalse()
        ->and(Storage::disk('adjuntos')->exists("{$id}/cv.pdf"))->toBeFalse()
        ->and(Storage::disk('evidencias')->exists("{$this->otra->id}/2026/ajena.pdf"))->toBeTrue();
});

it('suprime las cuentas del cliente y sólo desvincula al administrador miembro', function (): void {
    deBajaHace($this->organizacion, 91);

    purgar($this, ['--sin-exportacion' => true])
        ->expectsQuestion('Esto no se puede deshacer. Escribe «B12345674» para purgar', 'B12345674')
        ->assertSuccessful();

    expect(User::query()->whereKey($this->responsable->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($this->deLaOtra->id)->exists())->toBeTrue()
        ->and($this->miembro->fresh()?->organizacion_id)->toBeNull()
        ->and($this->miembro->fresh()?->esPlataforma())->toBeTrue();
});

it('deja la purga en la traza de la plataforma, con lo que fue', function (): void {
    deBajaHace($this->organizacion, 91);
    $nombre = $this->organizacion->nombre;

    purgar($this, ['--sin-exportacion' => true])
        ->expectsQuestion('Esto no se puede deshacer. Escribe «B12345674» para purgar', 'B12345674')
        ->assertSuccessful();

    $evento = EventoPlataforma::query()->where('accion', AccionPlataforma::OrganizacionPurgada->value)->sole();

    expect($evento->organizacion_afectada_id)->toBeNull()
        ->and($evento->detalle['nombre'] ?? null)->toBe($nombre)
        ->and($evento->detalle['cif'] ?? null)->toBe('B12345674')
        ->and($evento->detalle['cuentas_suprimidas'] ?? null)->toBe(1)
        ->and($evento->detalle['administradores_desvinculados'] ?? null)->toBe(1)
        ->and($evento->detalle['ficheros_borrados'] ?? null)->toBe(2);
});

it('la aplicación no puede borrar una organización por su cuenta', function (): void {
    deBajaHace($this->organizacion, 91);

    expect(fn () => DB::table('organizaciones')->where('id', $this->organizacion->id)->delete())
        ->toThrow(QueryException::class, 'permission denied');
});

it('la función también se niega antes del plazo, aunque la llame otro', function (): void {
    deBajaHace($this->organizacion, 30);

    expect(fn () => DB::selectOne('SELECT purgar_organizacion(?)', [$this->organizacion->id]))
        ->toThrow(QueryException::class);
});
