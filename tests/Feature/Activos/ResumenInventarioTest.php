<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\ResumenInventario;
use App\Http\Resources\Panel\IndicadorInventario;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Los indicadores de control del inventario
|--------------------------------------------------------------------------
|
| Aquí un fallo da por bueno un parque sin cifrar, que es exactamente la clase
| de fallo silencioso que encabeza la lista de prioridades de cobertura: nadie
| se entera hasta que lo dice un auditor.
|
| Cada indicador se prueba con su caso que SÍ cuenta y con el que NO, porque el
| error caro no es contar de menos, es contar de más: un panel que marca
| incumplimientos falsos se deja de mirar en dos semanas.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->resumen = app(ResumenInventario::class);

    $this->activo = fn (array $atributos = []): Activo => Activo::factory()
        ->de($this->organizacion)
        ->create($atributos);

    $this->valor = function (string $clave): int {
        $indicador = collect($this->resumen->indicadores())
            ->first(fn (IndicadorInventario $uno): bool => $uno->clave === $clave);

        expect($indicador)->not->toBeNull("No existe el indicador `{$clave}`.");

        return $indicador->valor;
    };
});

it('cuenta los nueve indicadores', function (): void {
    expect($this->resumen->indicadores())->toHaveCount(9);
});

it('cuenta sin cifrado sólo los que dicen que no', function (): void {
    ($this->activo)(['cifrado' => EstadoControl::No->value]);
    ($this->activo)(['cifrado' => EstadoControl::Si->value]);
    ($this->activo)(['cifrado' => EstadoControl::NoAplica->value]);

    expect(($this->valor)('sin_cifrado'))->toBe(1);
});

it('no cuenta «por confirmar» como incumplimiento', function (): void {
    // La distinción entera del enum vive o muere en esta prueba: si «por
    // confirmar» cayera del lado de «no», dieciocho dudas se convertirían en
    // dieciocho incumplimientos y alguien se pasaría una semana arreglando
    // copias que ya existían.
    ($this->activo)([
        'cifrado' => EstadoControl::PorConfirmar->value,
        'copia_seguridad' => EstadoControl::PorConfirmar->value,
    ]);

    expect(($this->valor)('sin_cifrado'))->toBe(0)
        ->and(($this->valor)('sin_copia'))->toBe(0)
        ->and(($this->valor)('por_confirmar'))->toBe(1);
});

it('cuenta una sola vez el activo con las dos cosas por confirmar', function (): void {
    ($this->activo)([
        'cifrado' => EstadoControl::PorConfirmar->value,
        'copia_seguridad' => EstadoControl::PorConfirmar->value,
    ]);

    expect(($this->valor)('por_confirmar'))->toBe(1);
});

it('cuenta sin propietario, sin identificador y sin ubicación', function (): void {
    ($this->activo)(['propietario_id' => null, 'identificador' => null, 'ubicacion' => null]);
    ($this->activo)([
        'propietario_id' => usuarioCon()->id,
        'identificador' => 'SN-0001',
        'ubicacion' => 'Sala técnica',
    ]);

    expect(($this->valor)('sin_propietario'))->toBe(1)
        ->and(($this->valor)('sin_identificador'))->toBe(1)
        ->and(($this->valor)('sin_ubicacion'))->toBe(1);
});

it('trata la cadena vacía como ausencia de dato', function (): void {
    // Un formulario que envía «» no deja `null` en la columna, y contarlo como
    // relleno esconde justo el campo que nadie completó.
    ($this->activo)(['identificador' => '', 'ubicacion' => '']);

    expect(($this->valor)('sin_identificador'))->toBe(1)
        ->and(($this->valor)('sin_ubicacion'))->toBe(1);
});

it('cuenta como sin revisar el que nunca se revisó', function (): void {
    ($this->activo)(['ultima_revision' => null]);
    ($this->activo)(['ultima_revision' => Carbon::today()->subMonths(13)->toDateString()]);
    ($this->activo)(['ultima_revision' => Carbon::today()->subMonths(2)->toDateString()]);

    expect(($this->valor)('sin_revisar'))->toBe(2);
});

it('cuenta la información restringida y no la confidencial', function (): void {
    ($this->activo)(['clasificacion' => Clasificacion::Restringido->value]);
    ($this->activo)(['clasificacion' => Clasificacion::Confidencial->value]);

    expect(($this->valor)('restringida'))->toBe(1);
});

it('cuenta soporte vencido por sistema operativo o por garantía', function (): void {
    ($this->activo)(['fin_soporte_so' => Carbon::today()->subDay()->toDateString()]);
    ($this->activo)(['fin_garantia' => Carbon::today()->subDay()->toDateString()]);
    ($this->activo)(['fin_soporte_so' => Carbon::today()->addYear()->toDateString()]);
    // Sin fecha no es obsoleto: no saber no es incumplir.
    ($this->activo)(['fin_soporte_so' => null, 'fin_garantia' => null]);

    expect(($this->valor)('sin_soporte'))->toBe(2);
});

it('no cuenta los activos retirados en ningún indicador', function (): void {
    // Un portátil dado de baja sin copia de seguridad no está pendiente de nada:
    // está cerrado. Lo que sí puede deber es el borrado seguro, y eso lo señala
    // su ficha.
    ($this->activo)([
        'estado_ciclo_vida' => EstadoCicloVida::DadoDeBaja->value,
        'cifrado' => EstadoControl::No->value,
        'copia_seguridad' => EstadoControl::No->value,
        'clasificacion' => Clasificacion::Restringido->value,
    ]);

    foreach ($this->resumen->indicadores() as $indicador) {
        expect($indicador->valor)->toBe(0, "El indicador `{$indicador->clave}` cuenta un activo de baja.");
    }

    expect($this->resumen->vigentes())->toBe(0);
});

it('cuenta los estados intermedios como vigentes', function (): void {
    // En stock, en reparación y prestado siguen teniendo los datos dentro y
    // siguen siendo responsabilidad de alguien.
    foreach ([EstadoCicloVida::EnStock, EstadoCicloVida::EnReparacion, EstadoCicloVida::Prestado] as $estado) {
        ($this->activo)(['estado_ciclo_vida' => $estado->value, 'cifrado' => EstadoControl::No->value]);
    }

    expect(($this->valor)('sin_cifrado'))->toBe(3)
        ->and($this->resumen->vigentes())->toBe(3);
});

it('no cuenta activos de otra organización', function (): void {
    $ajena = comoOrganizacion();
    Activo::factory()->de($ajena)->create(['cifrado' => EstadoControl::No->value]);
    comoOrganizacion($this->organizacion);

    expect(($this->valor)('sin_cifrado'))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Cada cifra tiene que llevar a su lista
|--------------------------------------------------------------------------
|
| Es lo que distingue esto de la hoja de cálculo de la que viene. Y es frágil:
| el indicador y el filtro comparten scope justamente para que no se separen, y
| esta prueba es la que se entera si alguien los separa.
|
*/

it('el filtro de cada indicador devuelve exactamente su cifra', function (): void {
    $usuario = usuarioCon();

    ($this->activo)(['cifrado' => EstadoControl::No->value, 'codigo' => 'A-1']);
    ($this->activo)(['copia_seguridad' => EstadoControl::PorConfirmar->value, 'codigo' => 'A-2']);
    ($this->activo)(['clasificacion' => Clasificacion::Restringido->value, 'codigo' => 'A-3']);
    ($this->activo)(['fin_garantia' => Carbon::today()->subDay()->toDateString(), 'codigo' => 'A-4']);
    ($this->activo)(['propietario_id' => $usuario->id, 'ubicacion' => 'X', 'identificador' => 'Y', 'codigo' => 'A-5']);

    foreach ($this->resumen->indicadores() as $indicador) {
        $this->actingAs($usuario)
            ->get('/activos?'.$indicador->filtro)
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has(
                'filas',
                $indicador->valor,
                // Si esto falla, el panel dice una cifra y la tabla enseña otra:
                // a partir de ahí nadie se fía del panel.
            ), "El filtro de `{$indicador->clave}` no coincide con su indicador.");
    }
});
