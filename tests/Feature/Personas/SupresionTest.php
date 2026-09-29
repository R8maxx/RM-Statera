<?php

declare(strict_types=1);

use App\Domain\Adjunto\Models\Adjunto;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Copia\EspejoDeObjetos;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Persona\Excepciones\SeudonimizacionNoPermitida;
use App\Domain\Persona\Models\DesignacionRol;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\SeudonimizarPersona;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Models\EventoAuditoria;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Retención y supresión de los datos de personas (§ 6, punto 36)
|--------------------------------------------------------------------------
|
| Suprimir es seudonimizar: la fila se queda, sin nada que identifique a nadie,
| porque de ella cuelga histórico del SGSI. Y la supresión llega a la traza,
| que es lo que la separa de un cambio de nombre.
|
*/

beforeEach(function (): void {
    Storage::fake('adjuntos');
    Storage::fake('copias');

    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

function personaDeBaja(array $datos = [], ?Carbon $baja = null): Persona
{
    return Persona::factory()->create([
        'codigo' => 'PER-042',
        'nombre_pila' => 'Lucía',
        'apellido1' => 'Sintética',
        'nif' => '77777777D',
        'telefono' => '+34 600 777 777',
        'email' => 'lucia@ejemplo.test',
        'notas' => 'Pidió excedencia.',
        'fecha_alta' => Carbon::today()->subYears(5),
        'fecha_baja' => $baja ?? Carbon::today()->subMonth(),
        ...$datos,
    ]);
}

it('deja a la persona sin nada que la identifique y conserva la fila', function (): void {
    $persona = personaDeBaja();

    app(SeudonimizarPersona::class)($persona);
    $persona->refresh();

    expect($persona->nombre)->toBe('Persona PER-042')
        ->and($persona->apellido1)->toBeNull()
        ->and($persona->nif)->toBeNull()
        ->and($persona->nif_huella)->toBeNull()
        ->and($persona->telefono)->toBeNull()
        ->and($persona->email)->toBeNull()
        ->and($persona->notas)->toBeNull()
        ->and($persona->seudonimizada_en)->not->toBeNull()
        ->and($persona->codigo)->toBe('PER-042');
});

it('borra sus adjuntos, también del espejo de copias', function (): void {
    $persona = personaDeBaja();
    $adjunto = Adjunto::factory()->create();
    $persona->adjuntos()->attach($adjunto->id, ['organizacion_id' => $this->organizacion->id]);
    Storage::disk('adjuntos')->put($adjunto->ruta, 'DNI escaneado');
    Storage::disk('copias')->put(EspejoDeObjetos::rutaEnCopia('adjuntos', $adjunto->ruta), 'DNI escaneado');

    app(SeudonimizarPersona::class)($persona);

    expect(Adjunto::query()->count())->toBe(0);
    Storage::disk('adjuntos')->assertMissing($adjunto->ruta);
    Storage::disk('copias')->assertMissing(EspejoDeObjetos::rutaEnCopia('adjuntos', $adjunto->ruta));
});

/*
 * Lo que la distingue de un cambio de nombre. Los eventos siguen ahí —el
 * auditor sigue viendo que alguien dio de alta y editó una ficha—, pero sin lo
 * que ponía en ella, incluido el evento que deja la propia supresión.
 */
it('quita sus datos de la traza sin borrar los eventos', function (): void {
    $persona = personaDeBaja();
    $persona->update(['telefono' => '+34 600 000 999']);
    $eventosAntes = EventoAuditoria::query()->where('entidad', 'Persona')->count();

    app(SeudonimizarPersona::class)($persona);

    $eventos = EventoAuditoria::query()->where('entidad', 'Persona')->where('entidad_id', $persona->id)->get();
    $texto = $eventos->map(fn (EventoAuditoria $e): string => json_encode([$e->valor_anterior, $e->valor_nuevo]))->implode(' ');

    expect($eventos)->toHaveCount($eventosAntes + 1)
        ->and($texto)->not->toContain('Lucía')
        ->and($texto)->not->toContain('lucia@ejemplo.test')
        ->and($texto)->not->toContain('Pidió excedencia')
        ->and($texto)->not->toContain('nif');
});

it('no se aplica a quien sigue en plantilla', function (): void {
    $persona = Persona::factory()->create(['fecha_baja' => null]);

    app(SeudonimizarPersona::class)($persona);
})->throws(SeudonimizacionNoPermitida::class, 'sigue en plantilla');

it('no se aplica a quien tiene un nombramiento vigente', function (): void {
    $persona = personaDeBaja();
    DesignacionRol::factory()->create([
        'persona_id' => $persona->id,
        'sistema_id' => Sistema::factory()->create()->id,
    ]);

    app(SeudonimizarPersona::class)($persona);
})->throws(SeudonimizacionNoPermitida::class, 'nombramiento vigente');

// --- La puerta a la traza, y lo estrecha que es -----------------------------

it('la depuración no toca los eventos de otra organización', function (): void {
    $otra = comoOrganizacion();
    $ajena = personaDeBaja(['codigo' => 'PER-900']);
    $idAjena = $ajena->id;

    comoOrganizacion($this->organizacion);
    DB::select('select depurar_traza_de_persona(?, ?::bigint[])', [$idAjena, '{}']);

    comoOrganizacion($otra);
    $alta = EventoAuditoria::query()->where('entidad', 'Persona')->where('entidad_id', $idAjena)->sole();

    expect($alta->valor_nuevo)->toHaveKey('nombre_pila');
});

it('la depuración sólo quita claves de personas y adjuntos', function (): void {
    $sistema = Sistema::factory()->create(['nombre' => 'Plataforma']);

    DB::select('select depurar_traza_de_persona(?, ?::bigint[])', [$sistema->id, '{'.$sistema->id.'}']);

    $alta = EventoAuditoria::query()->where('entidad', 'Sistema')->where('entidad_id', $sistema->id)->sole();

    expect($alta->valor_nuevo)->toHaveKey('nombre');
});

// --- El plazo de la organización -------------------------------------------

it('suprime cada noche a quien pasó el plazo, y sólo a ése', function (): void {
    Organizacion::query()->whereKey($this->organizacion->id)->update(['retencion_personas_meses' => 12]);

    $vencida = personaDeBaja(['codigo' => 'PER-001'], Carbon::today()->subMonths(13));
    $reciente = personaDeBaja(['codigo' => 'PER-002', 'nif' => '88888888E'], Carbon::today()->subMonths(11));

    $this->artisan('personas:seudonimizar')->assertSuccessful();

    expect($vencida->refresh()->seudonimizada_en)->not->toBeNull()
        ->and($reciente->refresh()->seudonimizada_en)->toBeNull();
});

it('sin plazo declarado no suprime a nadie', function (): void {
    $persona = personaDeBaja([], Carbon::today()->subYears(4));

    $this->artisan('personas:seudonimizar')->assertSuccessful();

    expect($persona->refresh()->seudonimizada_en)->toBeNull();
});

it('en simulación cuenta y no suprime', function (): void {
    Organizacion::query()->whereKey($this->organizacion->id)->update(['retencion_personas_meses' => 12]);
    $persona = personaDeBaja([], Carbon::today()->subYears(2));

    $this->artisan('personas:seudonimizar', ['--dry-run' => true])
        ->expectsOutputToContain('1 por suprimir')
        ->assertSuccessful();

    expect($persona->refresh()->seudonimizada_en)->toBeNull();
});

// --- Por la interfaz ---------------------------------------------------------

it('suprime desde la ficha y lo dice', function (): void {
    $persona = personaDeBaja();

    $this->actingAs($this->usuario)
        ->post("/personas/{$persona->id}/seudonimizar")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($persona->refresh()->seudonimizada_en)->not->toBeNull();
});

it('explica por qué no suprime a quien sigue en plantilla', function (): void {
    $persona = Persona::factory()->create(['fecha_baja' => null]);

    $this->actingAs($this->usuario)
        ->post("/personas/{$persona->id}/seudonimizar")
        ->assertSessionHasErrors('supresion');
});

it('el auditor no puede suprimir', function (): void {
    $persona = personaDeBaja();

    $this->actingAs(usuarioCon(Rol::Auditor))
        ->post("/personas/{$persona->id}/seudonimizar")
        ->assertForbidden();
});

it('el plazo se guarda desde la ficha de la organización', function (): void {
    $this->actingAs($this->usuario)
        ->get('/organizacion')
        ->assertInertia(fn ($pagina) => $pagina->where('organizacion.retencion_personas_meses', null));
});
