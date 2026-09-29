<?php

declare(strict_types=1);

use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Persona\Enums\JustificacionAusencia;
use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Asistencia;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\RegistrarAsistencia;
use App\Domain\Persona\SeudonimizarPersona;
use App\Http\Requests\Concerns\SeleccionVacia;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La formación y la concienciación: mp.per.3 y mp.per.4
|--------------------------------------------------------------------------
|
| **Convocar y asistir son dos cosas distintas**, y es lo que este fichero
| sostiene: quien no está en la lista no fue convocado, y quien está con
| `asistio = false` fue convocado y no fue. Sin esa diferencia, «formación
| impartida al 100 % de los convocados» saldría siempre.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->accion = AccionFormativa::factory()->create();
    $this->registrar = app(RegistrarAsistencia::class);
});

it('guarda la convocatoria entera de una vez', function (): void {
    $fue = Persona::factory()->create();
    $falto = Persona::factory()->create();

    ($this->registrar)($this->accion, [$fue->id => true, $falto->id => false]);

    expect(Asistencia::query()->count())->toBe(2)
        ->and(Asistencia::query()->where('asistio', true)->count())->toBe(1);
});

/** Idempotente: la pivote lleva índice único sobre (sesión, persona). */
it('volver a guardar la misma convocatoria no duplica filas', function (): void {
    $persona = Persona::factory()->create();

    ($this->registrar)($this->accion, [$persona->id => false]);
    ($this->registrar)($this->accion, [$persona->id => true]);

    expect(Asistencia::query()->count())->toBe(1)
        ->and(Asistencia::query()->sole()->asistio)->toBeTrue();
});

/**
 * Quien sale de la lista **deja de estar convocado**, que no es lo mismo que
 * haber faltado: se borra la fila en vez de marcarla a `false`.
 */
it('quien sale de la lista deja de estar convocado', function (): void {
    $uno = Persona::factory()->create();
    $dos = Persona::factory()->create();

    ($this->registrar)($this->accion, [$uno->id => true, $dos->id => true]);
    ($this->registrar)($this->accion, [$uno->id => true]);

    expect(Asistencia::query()->count())->toBe(1)
        ->and(Asistencia::query()->sole()->persona_id)->toBe($uno->id);
});

it('lista la plantilla activa en la convocatoria de una sesión', function (): void {
    $activa = Persona::factory()->create();
    Persona::factory()->deBaja()->create();

    $this->actingAs($this->usuario)
        ->get("/formacion/{$this->accion->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('formacion/Ficha')
            ->has('personas', 1)
            ->where('personas.0.id', $activa->id));
});

/**
 * Una persona dada de baja **que ya estaba convocada sigue apareciendo**:
 * asistió de verdad, y borrarla de la pantalla reescribiría el registro.
 */
it('conserva en la convocatoria a quien se fue después de asistir', function (): void {
    $baja = Persona::factory()->deBaja()->create();

    ($this->registrar)($this->accion, [$baja->id => true]);

    $this->actingAs($this->usuario)
        ->get("/formacion/{$this->accion->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('personas', 1)
            ->where('personas.0.activa', false)
            ->where('personas.0.asistio', true));
});

it('registra la asistencia por la interfaz', function (): void {
    $persona = Persona::factory()->create();

    $this->actingAs($this->usuario)
        ->put("/formacion/{$this->accion->id}/asistencia", [
            'convocadas' => [['persona_id' => $persona->id, 'asistio' => true]],
        ])
        ->assertRedirect();

    expect(Asistencia::query()->sole()->asistio)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| El indicador de personal formado
|--------------------------------------------------------------------------
|
| El IND-03 del seeder era **manual** con un comentario que decía «mientras el
| § 4.8 no exista». Con el módulo dentro es calculado, y lo que se prueba aquí
| es que el numerador sale de **restar** —no de una segunda consulta con la
| condición contraria, que sería la misma regla escrita dos veces—.
|
*/

it('cuenta como formada a quien asistió dentro de los doce meses', function (): void {
    $formada = Persona::factory()->create();
    Persona::factory()->create();

    ($this->registrar)($this->accion, [$formada->id => true]);

    $medida = CalculoIndicador::PersonalFormado->medir();

    expect($medida->numerador)->toEqual(1.0)
        ->and($medida->denominador)->toEqual(2.0);
});

it('no cuenta una sesión de hace más de doce meses', function (): void {
    $persona = Persona::factory()->create();
    $vieja = AccionFormativa::factory()->caducada()->create();

    ($this->registrar)($vieja, [$persona->id => true]);

    expect(Persona::query()->sinFormacionReciente()->count())->toBe(1);
});

/** Convocado y no ir no es haberse formado. */
it('no cuenta como formada a quien fue convocada y no asistió', function (): void {
    $persona = Persona::factory()->create();

    ($this->registrar)($this->accion, [$persona->id => false]);

    expect(Persona::query()->sinFormacionReciente()->count())->toBe(1);
});

/**
 * Las personas dadas de baja **no entran en el denominador**, igual que el
 * cumplimiento se cuenta sobre lo exigible: pedirle formación al año a quien se
 * fue en marzo pone un techo que la organización no puede alcanzar.
 */
it('no pide formación a quien ya no está en plantilla', function (): void {
    Persona::factory()->deBaja()->create();

    expect(Persona::query()->sinFormacionReciente()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| La hoja de firmas: la prueba de la medida
|--------------------------------------------------------------------------
|
| `evidencia_id` existía en la tabla y en el `FormRequest` —con el nombre «hoja
| de firmas»— y **no había forma de rellenarlo desde ninguna pantalla**, así que
| `mp.per.3` y `mp.per.4` quedaban declaradas y sin probar.
*/

it('ofrece las evidencias del repositorio al registrar una sesión', function (): void {
    Evidencia::factory()->create(['titulo' => 'Lista de asistentes firmada']);

    $this->actingAs($this->usuario)
        ->get('/formacion/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('formacion/Formulario')
            ->has('evidencias', 1)
            ->where('evidencias.0.etiqueta', 'Lista de asistentes firmada')
            ->etc());
});

it('adjunta la hoja de firmas a una sesión, y deja quitarla', function (): void {
    $evidencia = Evidencia::factory()->create();

    $datos = [
        'codigo' => 'FOR-2026-09',
        'titulo' => 'Concienciación anual',
        'tipo' => TipoAccionFormativa::Concienciacion->value,
        'fecha' => now()->toDateString(),
    ];

    $this->actingAs($this->usuario)
        ->put("/formacion/{$this->accion->id}", [...$datos, 'evidencia_id' => (string) $evidencia->id])
        ->assertSessionHasNoErrors();

    expect($this->accion->fresh()?->evidencia_id)->toBe($evidencia->id);

    // Y el centinela del desplegable la suelta, en vez de fallar la validación.
    $this->actingAs($this->usuario)
        ->put("/formacion/{$this->accion->id}", [...$datos, 'evidencia_id' => SeleccionVacia::VALOR])
        ->assertSessionHasNoErrors();

    expect($this->accion->fresh()?->evidencia_id)->toBeNull();
});

it('adjunta el documento firmado a un acuerdo de confidencialidad', function (): void {
    $persona = Persona::factory()->create();
    $evidencia = Evidencia::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/personas/{$persona->id}/acuerdos", [
            'fecha_firma' => now()->toDateString(),
            'evidencia_id' => (string) $evidencia->id,
        ])
        ->assertSessionHasNoErrors();

    expect($persona->acuerdos()->sole()->evidencia_id)->toBe($evidencia->id);
});

/*
|--------------------------------------------------------------------------
| Por qué faltó quien estaba convocado
|--------------------------------------------------------------------------
|
| La pregunta siguiente a «¿quién faltó?» es «¿tenía motivo?». Nula es «sin
| indicar», que no es ni justificada ni injustificada: nadie lo ha dicho todavía.
|
*/

it('guarda la justificación de quien faltó, con el motivo cifrado', function (): void {
    $persona = Persona::factory()->create();

    $this->actingAs($this->usuario)
        ->put("/formacion/{$this->accion->id}/asistencia", [
            'convocadas' => [[
                'persona_id' => $persona->id,
                'asistio' => false,
                'ausencia' => 'justificada',
                'motivo' => 'Baja médica',
            ]],
        ])
        ->assertSessionHasNoErrors();

    $asistencia = Asistencia::query()->sole();

    expect($asistencia->ausencia)->toBe(JustificacionAusencia::Justificada)
        ->and($asistencia->motivo_ausencia)->toBe('Baja médica')
        ->and(DB::table('asistencias')->value('motivo_ausencia'))->not->toContain('Baja médica');
});

it('no admite una ausencia justificada sin motivo', function (): void {
    $persona = Persona::factory()->create();

    $this->actingAs($this->usuario)
        ->put("/formacion/{$this->accion->id}/asistencia", [
            'convocadas' => [['persona_id' => $persona->id, 'asistio' => false, 'ausencia' => 'justificada']],
        ])
        ->assertSessionHasErrors('convocadas.0.motivo');

    expect(Asistencia::query()->count())->toBe(0);
});

it('apunta la ausencia sin justificar y descarta el motivo que venga con ella', function (): void {
    $persona = Persona::factory()->create();

    ($this->registrar)(
        $this->accion,
        [$persona->id => false],
        [$persona->id => ['ausencia' => JustificacionAusencia::Injustificada, 'motivo' => 'Se le olvidó']],
    );

    $asistencia = Asistencia::query()->sole();

    expect($asistencia->ausencia)->toBe(JustificacionAusencia::Injustificada)
        ->and($asistencia->motivo_ausencia)->toBeNull();
});

/** Quien asistió no faltó: la justificación se descarta, y la base la rechazaría. */
it('quien asistió no guarda justificación', function (): void {
    $persona = Persona::factory()->create();

    ($this->registrar)(
        $this->accion,
        [$persona->id => true],
        [$persona->id => ['ausencia' => JustificacionAusencia::Justificada, 'motivo' => 'Baja médica']],
    );

    expect(Asistencia::query()->sole()->ausencia)->toBeNull();

    expect(fn () => DB::table('asistencias')->update(['ausencia' => 'justificada']))
        ->toThrow(QueryException::class);
});

it('al corregir a asistió se borra la justificación que tenía', function (): void {
    $persona = Persona::factory()->create();

    ($this->registrar)(
        $this->accion,
        [$persona->id => false],
        [$persona->id => ['ausencia' => JustificacionAusencia::Justificada, 'motivo' => 'Vacaciones']],
    );
    ($this->registrar)($this->accion, [$persona->id => true]);

    $asistencia = Asistencia::query()->sole();

    expect($asistencia->ausencia)->toBeNull()
        ->and($asistencia->motivo_ausencia)->toBeNull();
});

it('la supresión de una persona vacía el motivo y conserva que estaba justificada', function (): void {
    $persona = Persona::factory()->deBaja(Carbon::today()->subMonth())->create();

    ($this->registrar)(
        $this->accion,
        [$persona->id => false],
        [$persona->id => ['ausencia' => JustificacionAusencia::Justificada, 'motivo' => 'Baja médica']],
    );

    app(SeudonimizarPersona::class)($persona);

    $asistencia = Asistencia::query()->sole();

    expect($asistencia->ausencia)->toBe(JustificacionAusencia::Justificada)
        ->and($asistencia->motivo_ausencia)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| La ficha de la sesión
|--------------------------------------------------------------------------
*/

/**
 * La vigencia de cada persona llega **sin contar esta sesión**: es lo que deja
 * contestar «si falta, ¿queda al descubierto?».
 */
it('manda la vigencia previa de cada persona sin contar esta sesión', function (): void {
    $persona = Persona::factory()->create();
    $anterior = AccionFormativa::factory()->create(['fecha' => Carbon::today()->subMonths(3)]);

    ($this->registrar)($anterior, [$persona->id => true]);
    ($this->registrar)($this->accion, [$persona->id => false]);

    $this->actingAs($this->usuario)
        ->get("/formacion/{$this->accion->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('personas.0.asistio', false)
            ->where('personas.0.renovacion_previa', $anterior->vigenteHasta()->toDateString())
            ->where('personas.0.ultima_sesion', $anterior->codigo)
            ->where('accion.vigenteHasta', $this->accion->vigenteHasta()->toDateString())
            ->has('justificaciones', count(JustificacionAusencia::cases())));
});

it('manda la vigencia previa vacía a quien sólo tiene esta sesión', function (): void {
    $persona = Persona::factory()->create();

    ($this->registrar)($this->accion, [$persona->id => true]);

    $this->actingAs($this->usuario)
        ->get("/formacion/{$this->accion->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('personas.0.renovacion_previa', null)
            ->where('personas.0.ultima_sesion', null));
});

it('programa la siguiente con lo de ésta y la fecha en que vence', function (): void {
    $this->actingAs($this->usuario)
        ->get("/formacion/crear?desde={$this->accion->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('formacion/Formulario')
            ->where('sugerencia.titulo', $this->accion->titulo)
            ->where('sugerencia.tipo', $this->accion->tipo->value)
            ->where('sugerencia.fecha', $this->accion->vigenteHasta()->toDateString()));
});
