<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La ficha técnica del activo
|--------------------------------------------------------------------------
|
| Los campos que vinieron de la hoja de cálculo, y las tres restricciones que
| los sujetan. Los `CHECK` no son decoración: los enums viven en PHP y la
| aplicación no es la única que escribe en esta base.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('guarda la ficha técnica completa', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->create([
        'subtipo' => 'Portátil',
        'marca_modelo' => 'Genérico 14 pulgadas',
        'especificaciones' => 'i7 · 16 GB · 512 GB SSD',
        'sistema_operativo' => 'Ubuntu 24.04 LTS',
        'fin_soporte_so' => '2029-05-31',
        'identificador' => 'SN-SINTETICO-0001',
        'departamento' => 'Sistemas',
        'fin_garantia' => '2028-01-31',
        'observaciones' => 'Pendiente de ampliar memoria.',
    ]);

    $recargado = $activo->fresh();

    expect($recargado?->subtipo)->toBe('Portátil')
        ->and($recargado?->identificador)->toBe('SN-SINTETICO-0001')
        ->and($recargado?->fin_soporte_so?->toDateString())->toBe('2029-05-31')
        ->and($recargado?->fin_garantia?->toDateString())->toBe('2028-01-31');
});

it('permite repetir el identificador: un ARN y un nº de serie no comparten espacio', function (): void {
    // Deliberadamente sin restricción de unicidad. Una IP se reasigna, y dos
    // sistemas de numeración distintos pueden coincidir por casualidad.
    Activo::factory()->de($this->organizacion)->create(['identificador' => '10.0.0.1']);
    Activo::factory()->de($this->organizacion)->create(['identificador' => '10.0.0.1']);

    expect(Activo::query()->where('identificador', '10.0.0.1')->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Las restricciones de la base
|--------------------------------------------------------------------------
*/

it('la base rechaza un valor que el enum no contempla', function (string $columna, string $valor): void {
    $activo = Activo::factory()->de($this->organizacion)->create();

    expect(fn () => DB::table('activos')->where('id', $activo->id)->update([$columna => $valor]))
        ->toThrow(QueryException::class);
})->with([
    ['clasificacion', 'secretisimo'],
    ['cifrado', 'quiza'],
    ['copia_seguridad', 'a_ratos'],
    ['estado_ciclo_vida', 'en_el_limbo'],
]);

it('la base acepta los estados nuevos del ciclo de vida', function (EstadoCicloVida $estado): void {
    $activo = Activo::factory()->de($this->organizacion)->enEstado($estado)->create();

    expect($activo->fresh()?->estado_ciclo_vida)->toBe($estado);
})->with([
    EstadoCicloVida::EnStock,
    EstadoCicloVida::EnReparacion,
    EstadoCicloVida::Prestado,
]);

it('los estados intermedios cuentan como vigentes', function (): void {
    // Un portátil en el armario o en el taller sigue teniendo los datos dentro.
    expect(EstadoCicloVida::EnStock->estaVigente())->toBeTrue()
        ->and(EstadoCicloVida::EnReparacion->estaVigente())->toBeTrue()
        ->and(EstadoCicloVida::Prestado->estaVigente())->toBeTrue()
        ->and(EstadoCicloVida::Retirado->estaVigente())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Propietario y custodio
|--------------------------------------------------------------------------
*/

it('distingue al propietario del custodio', function (): void {
    $propietario = usuarioCon();
    $custodio = usuarioCon();

    $activo = Activo::factory()->de($this->organizacion)->create([
        'propietario_id' => $propietario->id,
        'custodio_id' => $custodio->id,
    ]);

    expect($activo->propietario?->id)->toBe($propietario->id)
        ->and($activo->custodio?->id)->toBe($custodio->id);
});

it('cambiar de custodio no toca el código del activo', function (): void {
    // Es la regla que hace que la etiqueta pegada en la carcasa siga valiendo
    // cuando el portátil cambia de manos.
    $activo = Activo::factory()->de($this->organizacion)->create(['codigo' => 'PC-0001']);
    $nuevo = usuarioCon();

    $activo->update(['custodio_id' => $nuevo->id]);

    expect($activo->fresh()?->codigo)->toBe('PC-0001');
});

/*
|--------------------------------------------------------------------------
| Lo que llega a la tabla y a la ficha
|--------------------------------------------------------------------------
*/

it('pinta el tipo con su tono propio y su icono', function (): void {
    // El icono no es decoración: nueve tipos no se separan sólo por color
    // (DESIGN.md §3), así que si deja de viajar la tabla pierde legibilidad.
    Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Datos)->create();

    $this->actingAs($this->usuario)
        ->get('/activos')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.tipo.tono', 'tipo:datos')
            ->where('filas.0.tipo.icono', 'Database')
        );
});

it('nunca usa un tono de estado para un tipo', function (): void {
    // Un badge de tipo en verde se leería como «implantado».
    $estados = ['implantado', 'planificado', 'en_progreso', 'no_iniciado', 'no_aplica', 'caducada'];

    foreach (TipoActivo::cases() as $tipo) {
        expect($estados)->not->toContain('tipo:'.$tipo->value);
        expect($tipo->icono())->not->toBeEmpty();
    }
});

it('lleva etiqueta sólo lo físico y en uso', function (): void {
    $servidor = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)->create();
    $servicio = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Servicios)->create();
    $retirado = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Hardware)->retirado()->create();

    expect($servidor->llevaEtiqueta())->toBeTrue()
        ->and($servicio->llevaEtiqueta())->toBeFalse()
        ->and($retirado->llevaEtiqueta())->toBeFalse();
});

it('avisa en la ficha del sistema fuera de soporte', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->create([
        'sistema_operativo' => 'Ubuntu 20.04 LTS',
        'fin_soporte_so' => Carbon::yesterday()->toDateString(),
    ]);

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('activo.cifrado', EstadoControl::NoAplica->value)
            ->where('activo.clasificacion', Clasificacion::NoAplica->value)
            ->has('avisoSoporte')
            ->where('avisoSoporte', fn (?string $aviso): bool => str_contains((string) $aviso, 'Ubuntu 20.04 LTS'))
        );
});

it('no avisa cuando no hay nada que avisar', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->create();

    $this->actingAs($this->usuario)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('avisoSoporte', null));
});

it('reserva la ficha técnica a lo que tiene modelo o versión', function (): void {
    // Software entra sin ser físico: el fin de soporte de la versión es lo que
    // vigila op.exp.4, y sin él el aviso de obsolescencia no salta nunca.
    expect(TipoActivo::Hardware->tieneFichaTecnica())->toBeTrue()
        ->and(TipoActivo::Soportes->tieneFichaTecnica())->toBeTrue()
        ->and(TipoActivo::Software->tieneFichaTecnica())->toBeTrue()
        ->and(TipoActivo::Servicios->tieneFichaTecnica())->toBeFalse()
        ->and(TipoActivo::Datos->tieneFichaTecnica())->toBeFalse()
        ->and(TipoActivo::Personal->tieneFichaTecnica())->toBeFalse();
});

it('le dice al formulario qué tipos llevan ficha técnica', function (): void {
    $this->actingAs($this->usuario)
        ->get('/activos/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('tipos', fn (Collection $tipos): bool => $tipos
                ->every(fn (array $tipo): bool => array_key_exists('fichaTecnica', $tipo))
            )
        );
});
