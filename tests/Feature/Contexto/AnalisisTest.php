<?php

declare(strict_types=1);

use App\Domain\Contexto\AnalisisEnCurso;
use App\Domain\Contexto\AprobarAnalisis;
use App\Domain\Contexto\Enums\EstadoAnalisis;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Excepciones\AnalisisNoAprobable;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\RegistrarCuestion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El ciclo de una revisión del contexto, y lo que la base impone sobre él.
 *
 * Lo que se clava aquí es lo que ninguna pantalla puede saltarse: **los dos
 * únicos parciales** —un borrador y un aprobado como mucho—, **los cuatro `CHECK`**
 * que acoplan estado, número, firma, clima e instantánea, y **el cuarto trigger de
 * inmutabilidad del producto** con su única puerta.
 *
 * El del clima es el que más vale: la enmienda 1:2024 obliga a *determinar si* el
 * cambio climático es pertinente, y sin este acoplamiento «no lo hemos mirado» y
 * «lo hemos mirado y no aplica» serían la misma fila.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->registrar = fn (string $codigo = 'CTX-01'): CuestionContexto => app(RegistrarCuestion::class)([
        'codigo' => $codigo,
        'tipo' => TipoCuestion::Debilidad->value,
        'materia' => 'organizativo',
        'titulo' => 'Equipo de sistemas pequeño',
    ], $this->usuario);

    $this->conClima = function (AnalisisContexto $analisis): AnalisisContexto {
        $analisis->update([
            'clima_pertinente' => true,
            'clima_justificacion' => 'Las olas de calor afectan a la disponibilidad del servicio.',
        ]);

        return $analisis->refresh();
    };
});

it('el borrador se estrena solo al registrar la primera cuestión', function (): void {
    expect(AnalisisContexto::query()->count())->toBe(0);

    $cuestion = ($this->registrar)();

    expect(AnalisisContexto::query()->count())->toBe(1)
        ->and($cuestion->analisis_alta_id)->toBe(app(AnalisisEnCurso::class)->borrador()?->id)
        ->and(app(AnalisisEnCurso::class)->borrador()?->estado)->toBe(EstadoAnalisis::Borrador);
});

it('aprobar numera, firma y congela la instantánea', function (): void {
    ($this->registrar)();

    $borrador = ($this->conClima)(app(AnalisisEnCurso::class)->borradorObligatorio($this->usuario));
    $aprobado = app(AprobarAnalisis::class)($borrador, $this->usuario);

    expect($aprobado->numero)->toBe(1)
        ->and($aprobado->estado)->toBe(EstadoAnalisis::Aprobado)
        ->and($aprobado->aprobado_por_id)->toBe($this->usuario->id)
        ->and($aprobado->aprobado_en)->not->toBeNull()
        ->and($aprobado->instantanea)->toBeArray()
        ->and($aprobado->instantanea['dafo'][TipoCuestion::Debilidad->value] ?? [])->toHaveCount(1);
});

/*
 * El motivo entero de que este módulo lleve análisis versionados: la instantánea
 * es lo que hace que el documento de marzo siga diciendo lo de marzo. Sin ella,
 * editar una cuestión reescribiría el pasado — que es el mismo razonamiento que
 * ya está escrito para `riesgo_valoraciones.salvaguardas`.
 */
it('editar una cuestión después no cambia lo que dice el análisis aprobado', function (): void {
    $cuestion = ($this->registrar)();

    $borrador = ($this->conClima)(app(AnalisisEnCurso::class)->borradorObligatorio($this->usuario));
    $aprobado = app(AprobarAnalisis::class)($borrador, $this->usuario);

    $cuestion->update(['titulo' => 'Otra cosa completamente distinta']);

    $congelada = $aprobado->fresh()?->instantanea['dafo'][TipoCuestion::Debilidad->value][0] ?? [];

    expect($congelada['titulo'] ?? null)->toBe('Equipo de sistemas pequeño');
});

it('aprobar el siguiente jubila al anterior', function (): void {
    ($this->registrar)();
    $primero = app(AprobarAnalisis::class)(
        ($this->conClima)(app(AnalisisEnCurso::class)->borradorObligatorio($this->usuario)),
        $this->usuario,
    );

    ($this->registrar)('CTX-02');
    $segundo = app(AprobarAnalisis::class)(
        ($this->conClima)(app(AnalisisEnCurso::class)->borradorObligatorio($this->usuario)),
        $this->usuario,
    );

    expect($primero->fresh()?->estado)->toBe(EstadoAnalisis::Obsoleto)
        ->and($segundo->numero)->toBe(2)
        ->and(app(AnalisisEnCurso::class)->vigente()?->id)->toBe($segundo->id);
});

it('no se aprueba sin contestar a la pregunta del cambio climático', function (): void {
    ($this->registrar)();

    $borrador = app(AnalisisEnCurso::class)->borradorObligatorio($this->usuario);

    expect(fn () => app(AprobarAnalisis::class)($borrador, $this->usuario))
        ->toThrow(AnalisisNoAprobable::class);
});

it('no se aprueba un análisis sin ninguna cuestión', function (): void {
    $borrador = ($this->conClima)(app(AnalisisEnCurso::class)->borradorObligatorio($this->usuario));

    expect(fn () => app(AprobarAnalisis::class)($borrador, $this->usuario))
        ->toThrow(AnalisisNoAprobable::class);
});

// --- Lo que impone la base, un caso por `CHECK` -----------------------------

it('la base no admite dos borradores a la vez', function (): void {
    AnalisisContexto::factory()->create();

    expect(fn () => AnalisisContexto::factory()->create())->toThrow(QueryException::class);
});

it('la base no admite dos análisis aprobados a la vez', function (): void {
    AnalisisContexto::factory()->aprobado(1)->create();

    expect(fn () => AnalisisContexto::factory()->aprobado(2)->create())
        ->toThrow(QueryException::class);
});

it('la base rechaza un análisis aprobado sin número', function (): void {
    expect(fn () => AnalisisContexto::factory()->aprobado()->create(['numero' => null]))
        ->toThrow(QueryException::class);
});

it('la base rechaza un análisis aprobado sin firma', function (): void {
    expect(fn () => AnalisisContexto::factory()->aprobado()->create([
        'aprobado_por_id' => null,
        'aprobado_en' => null,
    ]))->toThrow(QueryException::class);
});

it('la base rechaza un análisis aprobado sin declarar el cambio climático', function (): void {
    expect(fn () => AnalisisContexto::factory()->aprobado()->create([
        'clima_pertinente' => null,
        'clima_justificacion' => null,
    ]))->toThrow(QueryException::class);
});

it('la base rechaza un análisis aprobado sin instantánea', function (): void {
    expect(fn () => AnalisisContexto::factory()->aprobado()->create(['instantanea' => null]))
        ->toThrow(QueryException::class);
});

// --- El trigger de inmutabilidad --------------------------------------------

it('un análisis aprobado no se puede modificar', function (): void {
    $analisis = AnalisisContexto::factory()->aprobado()->create();

    expect(fn () => $analisis->update(['nota' => 'Otra cosa']))->toThrow(QueryException::class);
});

/*
 * La única puerta, y hace falta sí o sí: sin ella un contexto aprobado no podría
 * revisarse nunca, que es lo contrario de lo que pide la cláusula 9.3. Es la
 * hermana de la de `riesgo_valoraciones` con `vigente` y la de
 * `documento_versiones` con `estado`.
 */
it('un análisis aprobado sí puede pasar a obsoleto', function (): void {
    $analisis = AnalisisContexto::factory()->aprobado()->create();

    $analisis->update(['estado' => EstadoAnalisis::Obsoleto]);

    expect($analisis->fresh()?->estado)->toBe(EstadoAnalisis::Obsoleto);
});

it('un análisis obsoleto no vuelve a ser el vigente', function (): void {
    $analisis = AnalisisContexto::factory()->obsoleto()->create();

    expect(fn () => $analisis->update(['estado' => EstadoAnalisis::Aprobado]))
        ->toThrow(QueryException::class);
});

/*
 * El borrador es la ventana editable entera, y eso también hay que fijarlo: un
 * trigger que blindara de más dejaría el módulo sin sitio donde trabajar.
 */
it('el borrador se retoca cuantas veces haga falta', function (): void {
    $borrador = AnalisisContexto::factory()->create();

    $borrador->update(['nota' => 'Primera pasada', 'fecha_analisis' => Carbon::today()->subDay()]);
    $borrador->update(['nota' => 'Segunda pasada']);

    expect($borrador->fresh()?->nota)->toBe('Segunda pasada');
});

it('la organización de al lado no ve el análisis de nadie', function (): void {
    AnalisisContexto::factory()->aprobado()->create();

    comoOrganizacion();

    expect(AnalisisContexto::query()->count())->toBe(0);

    // Y RLS lo confirma por debajo del scope de Eloquent, que es la capa que un
    // `withoutGlobalScopes()` se saltaría.
    expect(DB::table('analisis_contexto')->count())->toBe(0);
});
