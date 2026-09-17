<?php

declare(strict_types=1);

use App\Domain\NoConformidad\AbrirAccionCorrectiva;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\NoConformidad\RegistroNoConformidades;
use App\Http\Resources\Panel\Indicador;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Los indicadores del registro de no conformidades
|--------------------------------------------------------------------------
|
| Cada indicador cuenta con el mismo scope que usa su filtro de la tabla, y la
| clave del indicador es la clave del filtro. Eso es lo que hace que pulsar la
| cifra enseñe exactamente esa cifra, y esta prueba es la que se entera si
| alguien los separa.
|
| El que más importa es «Sin verificar»: una no conformidad cerrada y no
| verificada se lee como resuelta y no lo está, que es justo lo que el auditor
| comprueba.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->registro = app(RegistroNoConformidades::class);

    $this->valor = function (string $clave): int {
        $indicador = collect([...$this->registro->alertas(), ...$this->registro->pendientes()])
            ->first(fn (Indicador $uno): bool => $uno->clave === $clave);

        expect($indicador)->not->toBeNull("No existe el indicador `{$clave}`.");

        return $indicador->valor;
    };
});

it('cuenta como abiertas sólo las que siguen sin cerrar', function (): void {
    NoConformidad::factory()->create();
    NoConformidad::factory()->enEstado(EstadoNoConformidad::EnTratamiento)->create();
    NoConformidad::factory()->enEstado(EstadoNoConformidad::Cerrada)->create();
    // Anulada no está pendiente: está cerrada, con su motivo en el histórico.
    NoConformidad::factory()->enEstado(EstadoNoConformidad::Anulada)->create();

    expect(($this->valor)('abiertas'))->toBe(2);
});

/*
 * La distinción entera entre `Cerrada` y `Verificada` vive o muere aquí: si
 * «cerrada» contara como resuelta, la cláusula 10.2 e) se quedaría sin hacer en
 * el único sitio donde se notaría.
 */
it('cuenta sin verificar las tratadas y no comprobadas, y sólo ésas', function (): void {
    NoConformidad::factory()->enEstado(EstadoNoConformidad::Cerrada)->create();
    NoConformidad::factory()->enEstado(EstadoNoConformidad::Verificada)->create();
    NoConformidad::factory()->create();

    expect(($this->valor)('pendientes_de_verificar'))->toBe(1);
});

it('cuenta vencidas las abiertas que se pasaron de fecha, y no las cerradas', function (): void {
    NoConformidad::factory()->vencida()->create();
    // Cerrada y fuera de fecha: ya no está pendiente, así que no vence.
    NoConformidad::factory()->vencida()->enEstado(EstadoNoConformidad::Cerrada)->create();
    // Abierta y sin fecha: nadie ha dicho para cuándo, que no es lo mismo que
    // haberse pasado.
    NoConformidad::factory()->create();

    expect(($this->valor)('vencidas'))->toBe(1);
});

it('cuenta sin acción correctiva las que no tienen ninguna tarea viva detrás', function (): void {
    $sinNada = NoConformidad::factory()->create();
    $conAccion = NoConformidad::factory()->create();

    expect(($this->valor)('sin_accion'))->toBe(2);

    app(AbrirAccionCorrectiva::class)($conAccion, [
        'titulo' => 'Hacer lo que haya que hacer',
        'prioridad' => 'media',
    ], $this->usuario);

    expect(($this->valor)('sin_accion'))->toBe(1)
        ->and($sinNada->fresh()?->tareas()->count())->toBe(0);
});

it('cuenta sin causa raíz también las que la tienen en blanco', function (): void {
    NoConformidad::factory()->create(['analisis_causa_raiz' => null]);
    // Un espacio rellena la columna y no dice nada: cuenta igual.
    NoConformidad::factory()->create(['analisis_causa_raiz' => '   ']);
    NoConformidad::factory()->create(['analisis_causa_raiz' => 'El procedimiento no dice quién convoca.']);

    expect(($this->valor)('sin_causa_raiz'))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Cada cifra tiene que llevar a su lista
|--------------------------------------------------------------------------
*/

it('el filtro de cada indicador devuelve exactamente su cifra', function (): void {
    NoConformidad::factory()->vencida()->create(['analisis_causa_raiz' => 'Escrita.']);
    NoConformidad::factory()->enEstado(EstadoNoConformidad::Cerrada)->create();
    NoConformidad::factory()->enEstado(EstadoNoConformidad::EnTratamiento)->create();
    NoConformidad::factory()->create(['fecha_prevista' => Carbon::today()->addMonth()]);

    foreach ([...$this->registro->alertas(), ...$this->registro->pendientes()] as $indicador) {
        $this->actingAs($this->usuario)
            ->get('/no-conformidades?'.$indicador->filtro)
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has(
                'filas',
                $indicador->valor,
                // Si esto falla, el registro dice una cifra y la tabla enseña
                // otra: a partir de ahí nadie se fía del número.
            ), "El filtro de `{$indicador->clave}` no coincide con su indicador.");
    }
});
