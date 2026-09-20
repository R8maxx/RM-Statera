<?php

declare(strict_types=1);

use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Domain\Objetivo\Avance;
use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Objetivo\RegistroObjetivos;
use App\Domain\Objetivo\VincularIndicador;
use App\Http\Resources\Panel\Indicador as IndicadorPanel;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Cómo se evalúan los resultados (6.2, planificación e)
|--------------------------------------------------------------------------
|
| Es lo que hace que el § 4.14 fuera antes que éste: el criterio con el que se
| juzga un objetivo **es** un indicador, con su periodicidad, su responsable y su
| serie. Aquí se comprueban las tres cosas que pueden torcerse: que el vínculo es
| N:M de verdad, que el avance se deriva con la misma regla que el badge de un
| indicador, y que «sin medir» no cuenta como «fuera de objetivo».
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->indicadorCon = function (float $valor, float $objetivo, SentidoIndicador $sentido = SentidoIndicador::MayorMejor): Indicador {
        $indicador = Indicador::factory()->conObjetivo($objetivo, $sentido)->create();
        Medicion::factory()->for($indicador)->con($valor, $objetivo)->create();

        return $indicador->fresh() ?? $indicador;
    };
});

it('un indicador evalúa varios objetivos y un objetivo necesita varios', function (): void {
    $vincular = app(VincularIndicador::class);

    $compartido = Indicador::factory()->create();
    $propio = Indicador::factory()->create();

    $uno = Objetivo::factory()->create();
    $otro = Objetivo::factory()->create();

    $vincular->vincular($uno, $compartido, $this->usuario);
    $vincular->vincular($uno, $propio, $this->usuario);
    $vincular->vincular($otro, $compartido, $this->usuario);

    expect($uno->indicadores()->count())->toBe(2)
        ->and($otro->indicadores()->count())->toBe(1)
        // El mismo indicador colgando de dos objetivos: es el lado N:M que el
        // § 4.14 dejó anunciado por escrito y el que una clave singular rompería.
        ->and(DB::table('indicador_objetivo')->where('indicador_id', $compartido->id)->count())->toBe(2);

    // Idempotente: vincular lo mismo dos veces no es un error de quien lo hace.
    $vincular->vincular($uno, $propio, $this->usuario);

    expect($uno->indicadores()->count())->toBe(2);
});

it('desvincular no retira el indicador ni borra su serie', function (): void {
    $indicador = ($this->indicadorCon)(90, 80);
    $objetivo = Objetivo::factory()->create();

    $vincular = app(VincularIndicador::class);
    $vincular->vincular($objetivo, $indicador, $this->usuario);
    $vincular->desvincular($objetivo, $indicador);

    expect($objetivo->indicadores()->count())->toBe(0)
        ->and($indicador->fresh()?->activo)->toBeTrue()
        ->and($indicador->mediciones()->count())->toBe(1);
});

it('el avance cuenta los que llegan sobre los medidos, no sobre el total', function (): void {
    $objetivo = Objetivo::factory()->create();
    $vincular = app(VincularIndicador::class);

    $vincular->vincular($objetivo, ($this->indicadorCon)(90, 80), $this->usuario);
    $vincular->vincular($objetivo, ($this->indicadorCon)(50, 80), $this->usuario);
    // Sin medición: es una pregunta abierta, no un incumplimiento.
    $vincular->vincular($objetivo, Indicador::factory()->create(), $this->usuario);

    $avance = $objetivo->fresh()?->load('indicadores.ultimaMedicion')->avance();

    expect($avance)->toBeInstanceOf(Avance::class)
        ->and($avance->total)->toBe(3)
        ->and($avance->medidos)->toBe(2)
        ->and($avance->enObjetivo)->toBe(1)
        ->and($avance->etiqueta())->toBe('1 de 2')
        ->and($avance->seQuedaCorto())->toBeTrue();
});

it('sin indicador y sin medir se nombran aparte, y ninguno gasta rojo', function (): void {
    $sinIndicador = Avance::de([]);

    expect($sinIndicador->etiqueta())->toBe('Sin indicador')
        ->and($sinIndicador->tono())->not->toBe('caducada');

    $objetivo = Objetivo::factory()->create();
    app(VincularIndicador::class)->vincular($objetivo, Indicador::factory()->create(), $this->usuario);

    $sinMedir = $objetivo->fresh()?->load('indicadores.ultimaMedicion')->avance();

    expect($sinMedir->etiqueta())->toBe('Sin medir')
        ->and($sinMedir->tono())->not->toBe('caducada');
});

/*
 * La contradicción se señala y no se corrige, como el residual sin respaldo de
 * un riesgo: la herramienta pone delante que el objetivo figura alcanzado con
 * indicadores por debajo, y no toca ni el estado ni la cifra.
 */
it('avisa de un objetivo alcanzado con indicadores que no llegan', function (): void {
    $objetivo = Objetivo::factory()
        ->enEstado(EstadoObjetivo::Alcanzado, $this->usuario->id)
        ->create();

    app(VincularIndicador::class)->vincular($objetivo, ($this->indicadorCon)(50, 80), $this->usuario);

    $this->actingAs($this->usuario)
        ->get("/objetivos/{$objetivo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('avance.contradice', true)
            ->where('objetivo.estado', EstadoObjetivo::Alcanzado->value));
});

it('el indicador «sin indicador» del registro cuenta lo mismo que su filtro', function (): void {
    $conIndicador = Objetivo::factory()->create();
    app(VincularIndicador::class)->vincular($conIndicador, Indicador::factory()->create(), $this->usuario);

    Objetivo::factory()->count(2)->create();
    // Uno retirado: cerrado, así que no está pendiente de nada.
    Objetivo::factory()->enEstado(EstadoObjetivo::Retirado)->create();

    $registro = app(RegistroObjetivos::class);

    $indicador = collect($registro->pendientes())
        ->first(fn (IndicadorPanel $uno): bool => $uno->clave === 'sin_indicador');

    expect($indicador?->valor)->toBe(2);

    // Y la lista que abre esa cifra enseña exactamente esa cifra.
    $this->actingAs($this->usuario)
        ->get('/objetivos?filter[sin_indicador]=1')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));
});

it('un indicador retirado no se ofrece para evaluar un objetivo', function (): void {
    Indicador::factory()->create(['codigo' => 'IND-VIVO']);
    Indicador::factory()->retirado()->create(['codigo' => 'IND-MUERTO']);

    $objetivo = Objetivo::factory()->create();

    $this->actingAs($this->usuario)
        ->get("/objetivos/{$objetivo->id}")
        ->assertInertia(function (AssertableInertia $pagina): void {
            $codigos = collect($pagina->toArray()['props']['indicadoresDisponibles'])
                ->pluck('etiqueta')
                ->implode(' ');

            expect($codigos)->toContain('IND-VIVO')
                ->and($codigos)->not->toContain('IND-MUERTO');
        });
});
