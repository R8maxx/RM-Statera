<?php

declare(strict_types=1);

use App\Domain\Auditoria\Enums\AccionAuditada;
use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| La traza inmutable
|--------------------------------------------------------------------------
|
| Invariante 8: la herramienta entra en el alcance del propio SGSI, así que
| «quién cambió qué y cuándo» es un requisito, no telemetría. Y una traza que se
| puede reescribir no prueba nada, por eso la inmutabilidad la impone PostgreSQL
| y no un observador de Eloquent.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->marco = Marco::factory()->create();
});

it('deja constancia del alta con el usuario que la hizo', function (): void {
    $this->actingAs($this->usuario)
        ->post('/sistemas', [
            'codigo' => 'SIS-42',
            'nombre' => 'Plataforma',
            'marco_id' => $this->marco->id,
            'estado' => 'activo',
        ])
        ->assertRedirect('/sistemas');

    $evento = EventoAuditoria::query()->where('entidad', 'Sistema')->sole();

    expect($evento->accion)->toBe(AccionAuditada::Creado)
        ->and($evento->usuario_id)->toBe($this->usuario->id)
        ->and($evento->organizacion_id)->toBe($this->organizacion->id)
        ->and($evento->valor_anterior)->toBeNull()
        ->and($evento->valor_nuevo)->toHaveKey('codigo')
        ->and($evento->valor_nuevo['codigo'])->toBe('SIS-42');
});

it('el modelo se niega a modificar o borrar un evento', function (): void {
    Sistema::factory()->de($this->organizacion)->conMarco($this->marco)
        ->create(['estado' => EstadoSistema::Activo->value]);

    $evento = EventoAuditoria::query()->sole();

    // Segunda barrera: llega como excepción con nombre en vez de como un fallo
    // de privilegios que no dice nada de la causa.
    // Un valor distinto del que tiene: Eloquent no dispara `updating` cuando no
    // hay nada sucio, y el test se creería probado sin haber probado nada.
    expect(fn () => $evento->update(['accion' => AccionAuditada::Eliminado->value]))
        ->toThrow(RuntimeException::class, 'La traza de auditoría no se modifica');

    expect(fn () => $evento->delete())
        ->toThrow(RuntimeException::class, 'La traza de auditoría no se borra');
});

it('guarda sólo el diff en una modificación', function (): void {
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)
        ->create(['nombre' => 'Antes']);

    $sistema->update(['nombre' => 'Después']);

    $evento = EventoAuditoria::query()
        ->where('entidad', 'Sistema')
        ->where('accion', AccionAuditada::Actualizado->value)
        ->sole();

    // Sólo el campo tocado: un log que copia la fila entera cada vez deja de
    // poder consultarse en un año.
    expect(array_keys($evento->valor_nuevo ?? []))->toBe(['nombre'])
        ->and($evento->valor_anterior)->toBe(['nombre' => 'Antes'])
        ->and($evento->valor_nuevo)->toBe(['nombre' => 'Después']);
});

it('no escribe evento cuando no cambia nada auditable', function (): void {
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();

    EventoAuditoria::query()->where('accion', AccionAuditada::Actualizado->value)->count();

    $sistema->update(['nombre' => $sistema->nombre]);
    $sistema->touch();

    expect(EventoAuditoria::query()->where('accion', AccionAuditada::Actualizado->value)->count())->toBe(0);
});

it('deja constancia de la baja con la fotografía de lo que había', function (): void {
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)
        ->create(['codigo' => 'SIS-07']);

    $sistema->delete();

    $evento = EventoAuditoria::query()
        ->where('accion', AccionAuditada::Eliminado->value)
        ->sole();

    expect($evento->valor_nuevo)->toBeNull()
        ->and($evento->valor_anterior['codigo'])->toBe('SIS-07');
});

it('nunca guarda ni el identificador ni la organización ni las marcas de tiempo', function (): void {
    Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();

    $evento = EventoAuditoria::query()->sole();

    expect($evento->valor_nuevo)->not->toHaveKeys(['id', 'organizacion_id', 'created_at', 'updated_at']);
});

it('registra la valoración y la implantación, no sólo el sistema', function (): void {
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();

    $this->actingAs($this->usuario)->put("/sistemas/{$sistema->id}/valoracion", [
        'nivel_C' => 'bajo', 'nivel_I' => 'na', 'nivel_D' => 'na', 'nivel_A' => 'na', 'nivel_T' => 'na',
    ]);

    $entidades = EventoAuditoria::query()->pluck('entidad')->unique()->values();

    expect($entidades)->toContain('ValoracionDimension');
});

it('sin sesión iniciada el autor queda nulo, no inventado', function (): void {
    Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();

    // Es el caso del recálculo por comando: lo hizo el sistema, no una persona.
    expect(EventoAuditoria::query()->sole()->usuario_id)->toBeNull();
});

it('PostgreSQL rechaza modificar la traza, no sólo Eloquent', function (): void {
    Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();

    $id = EventoAuditoria::query()->sole()->id;

    // En crudo, esquivando el modelo: es exactamente lo que haría alguien que
    // quisiera maquillar el log, y es lo que la primera barrera tiene que parar.
    //
    // Cada intento va en su propio savepoint porque un error de PostgreSQL
    // aborta la transacción entera, y `RefreshDatabase` tiene una abierta: sin
    // el savepoint, el primer rechazo dejaría el resto del test sin base.
    $rechazado = function (Closure $intento): bool {
        DB::beginTransaction();

        try {
            $intento();
            DB::rollBack();

            return false;
        } catch (QueryException) {
            DB::rollBack();

            return true;
        }
    };

    expect($rechazado(fn () => DB::table('eventos_auditoria')->where('id', $id)->update(['accion' => 'eliminado'])))
        ->toBeTrue('PostgreSQL debería denegar el UPDATE sobre la traza.');

    expect($rechazado(fn () => DB::table('eventos_auditoria')->where('id', $id)->delete()))
        ->toBeTrue('PostgreSQL debería denegar el DELETE sobre la traza.');

    expect(EventoAuditoria::query()->count())->toBe(1);
});

it('la traza de una organización no se ve desde otra', function (): void {
    Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();

    $otra = Organizacion::factory()->create();
    comoOrganizacion($otra);

    expect(EventoAuditoria::query()->count())->toBe(0);

    comoOrganizacion($this->organizacion);

    expect(EventoAuditoria::query()->count())->toBe(1);
});

it('el cambio de estado de una implantación deja su evento además de su transición', function (): void {
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();

    $implantacion = Implantacion::factory()->create([
        'organizacion_id' => $this->organizacion->id,
        'sistema_id' => $sistema->id,
    ]);

    EventoAuditoria::query()->where('entidad', 'Implantacion')->count();

    $this->actingAs($this->usuario)->post("/implantaciones/{$implantacion->id}/estado", [
        'estado' => EstadoImplantacion::EnProgreso->value,
    ]);

    $evento = EventoAuditoria::query()
        ->where('entidad', 'Implantacion')
        ->where('accion', AccionAuditada::Actualizado->value)
        ->sole();

    expect($evento->valor_anterior)->toBe(['estado' => 'no_iniciado'])
        ->and($evento->valor_nuevo)->toBe(['estado' => 'en_progreso'])
        ->and($evento->usuario_id)->toBe($this->usuario->id);
});
