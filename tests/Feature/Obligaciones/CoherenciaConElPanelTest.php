<?php

declare(strict_types=1);

use App\Domain\Aviso\CalendarioVencimientos;
use App\Domain\Aviso\FiltrosVencimiento;
use App\Domain\Aviso\Fuente;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\RegistroObligaciones;
use App\Domain\Persona\Models\Persona;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| El calendario y el panel cuentan lo mismo
|--------------------------------------------------------------------------
|
| «Un panel que dice 12 donde la tabla enseña 9 deja de mirarse, y a partir de
| ahí nadie se fía de ninguna cifra.» Con siete fuentes derivadas de seis módulos
| distintos, la forma de que eso pase es que cada una use su propia condición.
|
| Aquí se fija que no: el calendario consulta **los mismos scopes** que alimentan
| el panel y los filtros de cada tabla.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

/** Lo que el calendario pinta en una ventana ancha, por fuente. */
function chipsDe(Fuente $fuente, int $anios = 5): array
{
    $vencimientos = app(CalendarioVencimientos::class)->entre(
        Carbon::today()->subYears($anios),
        Carbon::today()->addYears($anios),
        FiltrosVencimiento::ninguno(),
    );

    return array_values(array_filter(
        $vencimientos,
        static fn ($vencimiento): bool => $vencimiento->fuente === $fuente,
    ));
}

it('los compromisos vencidos del calendario son los que cuenta el panel', function (): void {
    Compromiso::factory()->count(2)->vencido()->create();
    Compromiso::factory()->venceEn(40)->create();

    $rojo = collect(app(RegistroObligaciones::class)->alertas())
        ->firstWhere('clave', 'vencidas');

    $vencidosEnElCalendario = count(array_filter(
        chipsDe(Fuente::Obligacion),
        static fn ($chip): bool => $chip->dias < 0,
    ));

    expect($rojo?->valor)->toBe(2)
        ->and($vencidosEnElCalendario)->toBe(2);
});

/**
 * **El calendario es un subconjunto del panel y nunca al revés.**
 *
 * Quien nunca ha recibido formación sale en `sinFormacionReciente()` —la cifra
 * del panel— y **no** en el calendario, porque no hay fecha que pintar y
 * `fecha_alta + 12` sería inventarle un plazo. Lo que este test impide es lo
 * contrario: que alguien aparezca en el calendario sin estar en el panel.
 */
it('toda persona con formación caducada en el calendario está en el panel', function (): void {
    Persona::factory()->count(3)->create();

    $enElPanel = Persona::query()->sinFormacionReciente()->pluck('id')->all();

    foreach (chipsDe(Fuente::Formacion) as $chip) {
        if ($chip->dias >= 0) {
            continue;
        }

        expect($enElPanel)->toContain($chip->id);
    }

    expect(Persona::query()->formacionCaducada()->count())
        ->toBeLessThanOrEqual(count($enElPanel));
});

it('los chips de indicador coinciden con los del periodo sin medir', function (): void {
    Indicador::factory()->count(3)->create();

    expect(count(chipsDe(Fuente::Indicador)))
        ->toBe(Indicador::query()->periodoSinMedir()->count());
});
