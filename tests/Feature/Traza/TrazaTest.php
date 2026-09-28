<?php

declare(strict_types=1);

use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Tarea\GuardarSubtareas;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Domain\Usuario\Models\CuentaSistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

/*
 * El REVOKE de arriba no vale nada si quien se conecta es el dueño de la tabla:
 * un dueño puede devolverse cualquier privilegio y apagar cualquier trigger.
 * Hasta el punto 32 `statera_app` lo era de las 113, porque corría también las
 * migraciones. Éstos se ejecutan con la conexión de la aplicación y no con la
 * del migrador, que es justo lo que hay que probar.
 */
it('la aplicación no es dueña de ninguna tabla', function (): void {
    $propias = DB::table('pg_tables')
        ->where('schemaname', 'public')
        ->whereRaw('tableowner = current_user')
        ->pluck('tablename')
        ->all();

    expect($propias)->toBe([]);
});

it('la aplicación no puede devolverse privilegios ni apagar triggers', function (): void {
    $rechazado = function (string $sql): bool {
        DB::beginTransaction();

        try {
            DB::statement($sql);
            DB::rollBack();

            return false;
        } catch (QueryException) {
            DB::rollBack();

            return true;
        }
    };

    // Un `GRANT` sin opción de concesión no falla: PostgreSQL avisa de que no
    // concedió nada y sigue. Lo que se comprueba es el efecto.
    DB::statement('GRANT UPDATE, DELETE ON eventos_auditoria TO CURRENT_USER');
    $privilegio = fn (string $cual): bool => (bool) DB::selectOne(
        "select has_table_privilege(current_user, 'eventos_auditoria', ?) as tiene",
        [$cual],
    )->tiene;

    expect($privilegio('UPDATE'))->toBeFalse('statera_app pudo devolverse el UPDATE sobre la traza.')
        ->and($privilegio('DELETE'))->toBeFalse('statera_app pudo devolverse el DELETE sobre la traza.')
        ->and($rechazado('ALTER TABLE documento_versiones DISABLE TRIGGER ALL'))
        ->toBeTrue('statera_app pudo apagar el trigger de inmutabilidad de las versiones.')
        ->and($rechazado('TRUNCATE eventos_auditoria'))
        ->toBeTrue('statera_app pudo vaciar la traza.')
        ->and($rechazado('CREATE TABLE intrusa (id int)'))
        ->toBeTrue('statera_app pudo crear una tabla.');
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

    // Y la transición misma: el histórico dice «desde cuándo», la traza dice que
    // esa fila no se tocó después de escribirse.
    expect(EventoAuditoria::query()
        ->where('entidad', 'ImplantacionTransicion')
        ->where('accion', AccionAuditada::Creado->value)
        ->count())->toBe(1);
});

/*
 * Descubre en vez de enumerar: todo modelo del dominio cuya tabla tenga
 * `organizacion_id` deja traza. Hasta el punto 32 no la dejaban las once tablas
 * de transiciones ni siete de detalle, y ningún test lo notaba porque olvidar
 * el trait no rompe nada: la fila se escribe igual y el evento no.
 */
it('todo modelo con organizacion_id deja traza', function (): void {
    /*
     * Las excepciones, cada una con su motivo escrito en el modelo:
     *
     * - `EventoAuditoria` es la traza; auditarse a sí misma es un bucle.
     * - `AuditoriaPunto`: se actualiza en bloque al cerrar y la traza saldría a
     *   medias (`auditorias.md`). Lo firma el auditor, no cada casilla.
     * - `CuentaSistema`: el alcance se traza a mano sobre la cuenta, como
     *   fotografía entera de antes y después (`AlcanceDeCuenta`).
     */
    $excepciones = [EventoAuditoria::class, AuditoriaPunto::class, CuentaSistema::class];

    $modelos = modelosDelDominio();
    expect($modelos)->not->toBeEmpty('El glob de modelos no encontró nada: el patrón dejó de casar.');

    $sinTraza = collect($modelos)
        ->reject(fn (string $clase): bool => in_array($clase, $excepciones, true))
        ->filter(fn (string $clase): bool => Schema::hasColumn((new $clase)->getTable(), 'organizacion_id'))
        ->reject(fn (string $clase): bool => in_array(RegistraTraza::class, class_uses_recursive($clase), true))
        ->values()
        ->all();

    expect($sinTraza)->toBe([]);
});

it('quitar un paso de la lista deja su baja en la traza', function (): void {
    $tarea = Tarea::factory()->create();
    [$primera] = app(GuardarSubtareas::class)($tarea, [
        ['titulo' => 'Revisar', 'hecha' => false],
        ['titulo' => 'Firmar', 'hecha' => false],
    ]);

    // Se reenvía la lista sin el segundo: el borrado era en bloque y no disparaba
    // eventos, así que el paso desaparecía sin constar.
    app(GuardarSubtareas::class)($tarea, [
        ['id' => $primera->id, 'titulo' => 'Revisar', 'hecha' => false],
    ]);

    $baja = EventoAuditoria::query()
        ->where('entidad', 'Subtarea')
        ->where('accion', AccionAuditada::Eliminado->value)
        ->sole();

    expect($baja->valor_anterior['titulo'])->toBe('Firmar');
});
