<?php

declare(strict_types=1);

use App\Domain\Cambio\CambiarEstadoCambio;
use App\Domain\Cambio\Enums\AmbitoCambio;
use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Models\CambioSgsi;
use App\Domain\Cambio\RegistroCambios;
use App\Domain\Panel\AlertasDelPanel;
use App\Http\Resources\Panel\Indicador;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El registro de cambios del SGSI por la interfaz (cláusula 6.3)
|--------------------------------------------------------------------------
|
| Lo que se fija aquí: un cambio **se propone sin plazo** —la fecha se exige al
| firmar, no al apuntarlo—, el código es correlativo por año y por organización,
| y el único rojo del registro es el plazo de un cambio ya aprobado.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('lista los cambios con sus alertas y sus pendientes', function (): void {
    CambioSgsi::factory()->count(2)->create();

    $this->actingAs($this->usuario)
        ->get('/cambios-sgsi')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('cambios-sgsi/Index')
            ->where('total', 2)
            ->has('alertas', 1)
            ->has('pendientes', 4)
            ->has('filas', 2));
});

it('propone el código siguiente del año', function (): void {
    $anio = Carbon::today()->year;

    CambioSgsi::factory()->create(['codigo' => "CS-{$anio}-01"]);
    CambioSgsi::factory()->create(['codigo' => "CS-{$anio}-04"]);

    $this->actingAs($this->usuario)
        ->get('/cambios-sgsi/crear?origen=revision_direccion')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('cambios-sgsi/Formulario')
            ->where('sugerencia.codigo', "CS-{$anio}-05")
            // Desde el acta de la revisión se trae el origen, y sólo el origen.
            ->where('sugerencia.origen', 'revision_direccion'));
});

it('propone un cambio sin fecha prevista y lo deja propuesto, con su transición de alta', function (): void {
    $this->actingAs($this->usuario)
        ->post('/cambios-sgsi', [
            'codigo' => 'CS-2026-01',
            'titulo' => 'Ampliar el alcance a la sede nueva',
            'ambito' => AmbitoCambio::Alcance->value,
            'origen' => 'propio',
            'fecha_propuesta' => '2026-09-01',
            // Se manda y se ignora: el estado se mueve por su ruta.
            'estado' => 'aprobado',
        ])
        ->assertRedirect();

    $cambio = CambioSgsi::query()->where('codigo', 'CS-2026-01')->sole();

    expect($cambio->estado)->toBe(EstadoCambio::Propuesto)
        ->and($cambio->fecha_prevista)->toBeNull()
        ->and($cambio->transiciones()->count())->toBe(1);
});

it('el código es único dentro de la organización y no del mundo', function (): void {
    CambioSgsi::factory()->create(['codigo' => 'CS-2026-01']);

    $this->actingAs($this->usuario)
        ->post('/cambios-sgsi', [
            'codigo' => 'CS-2026-01',
            'titulo' => 'Repetido',
            'ambito' => AmbitoCambio::Proceso->value,
            'origen' => 'propio',
            'fecha_propuesta' => '2026-09-01',
        ])
        ->assertSessionHasErrors('codigo');

    $otra = comoOrganizacion();
    $usuarioDeLaOtra = usuarioCon(organizacion: $otra);

    $this->actingAs($usuarioDeLaOtra)
        ->post('/cambios-sgsi', [
            'codigo' => 'CS-2026-01',
            'titulo' => 'El primero de la otra organización',
            'ambito' => AmbitoCambio::Proceso->value,
            'origen' => 'propio',
            'fecha_propuesta' => '2026-09-01',
        ])
        ->assertSessionHasNoErrors();
});

it('la edición de un cambio aprobado no le deja quitar el plazo', function (): void {
    $cambio = CambioSgsi::factory()->enEstado(EstadoCambio::Aprobado, $this->usuario)->create();

    $this->actingAs($this->usuario)
        ->put("/cambios-sgsi/{$cambio->id}", [
            'codigo' => $cambio->codigo,
            'titulo' => $cambio->titulo,
            'ambito' => $cambio->ambito->value,
            'origen' => $cambio->origen->value,
            'fecha_propuesta' => $cambio->fecha_propuesta->toDateString(),
            'fecha_prevista' => null,
        ])
        ->assertSessionHasErrors('fecha_prevista');
});

it('cada indicador del registro cuenta lo mismo que su filtro', function (): void {
    CambioSgsi::factory()->create();
    CambioSgsi::factory()->enEstado(EstadoCambio::Aprobado, $this->usuario, Carbon::today()->subDays(3))->create();
    CambioSgsi::factory()->enEstado(EstadoCambio::Implantado, $this->usuario)->create();
    CambioSgsi::factory()->enEstado(EstadoCambio::Revisado, $this->usuario)->create();

    $registro = app(RegistroCambios::class);

    foreach ([...$registro->alertas(), ...$registro->pendientes()] as $indicador) {
        /** @var Indicador $indicador */
        $this->actingAs($this->usuario)
            ->get("/cambios-sgsi?{$indicador->filtro}")
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', $indicador->valor));
    }

    expect(collect($registro->alertas())->firstWhere('clave', 'fuera_de_plazo')?->valor)->toBe(1);
});

it('el único rojo es el plazo firmado: un propuesto con la fecha pasada va en gris', function (): void {
    $propuesto = CambioSgsi::factory()->create(['fecha_prevista' => Carbon::today()->subDays(3)]);
    $aprobado = CambioSgsi::factory()->enEstado(EstadoCambio::Aprobado, $this->usuario, Carbon::today()->subDays(3))->create();

    $this->actingAs($this->usuario)
        ->get("/cambios-sgsi/{$propuesto->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('cambio.plazoTono', 'no_iniciado')
            ->where('cambio.plazoEtiqueta', 'Fecha pasada'));

    $this->actingAs($this->usuario)
        ->get("/cambios-sgsi/{$aprobado->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('cambio.plazoTono', 'caducada'));

    foreach (EstadoCambio::cases() as $estado) {
        expect($estado->tono())->not->toBe('caducada', "El estado `{$estado->value}` gasta el rojo: el rojo es del plazo.");
    }
});

it('el cambio fuera de plazo sube al panel', function (): void {
    CambioSgsi::factory()->enEstado(EstadoCambio::Aprobado, $this->usuario, Carbon::today()->subDays(3))->create();

    $alertas = collect(app(AlertasDelPanel::class)($this->usuario));

    expect($alertas->firstWhere('base', '/cambios-sgsi')?->valor)->toBe(1)
        ->and(AlertasDelPanel::vistaDe('/cambios-sgsi'))->toBe('ciclo');
});

it('implantarlo deja de contar como fuera de plazo', function (): void {
    $cambio = CambioSgsi::factory()->enEstado(EstadoCambio::Aprobado, $this->usuario, Carbon::today()->subDays(3))->create();

    app(CambiarEstadoCambio::class)($cambio, EstadoCambio::Implantado, $this->usuario);

    expect(CambioSgsi::query()->fueraDePlazo()->count())->toBe(0)
        ->and(CambioSgsi::query()->sinRevisar()->count())->toBe(1);
});

/*
 * La base tiene la última palabra, también con un importador o un SQL a mano.
 */
it('la base rechaza un cambio aprobado sin firma o sin plazo, y un revisado sin revisión', function (string $columnas): void {
    $cambio = CambioSgsi::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::update("update cambios_sgsi set {$columnas} where id = ?", [$cambio->id])))
        ->toThrow(QueryException::class);
})->with([
    'aprobado sin firma' => "estado = 'aprobado', fecha_prevista = current_date",
    'aprobado sin plazo' => "estado = 'aprobado', aprobado_en = now(), aprobado_por_id = (select id from users limit 1)",
    'implantado sin fecha' => "estado = 'implantado', fecha_prevista = current_date, aprobado_en = now(), aprobado_por_id = (select id from users limit 1)",
    'revisado sin revisión' => "estado = 'revisado', fecha_prevista = current_date, aprobado_en = now(), aprobado_por_id = (select id from users limit 1), fecha_implantacion = current_date, fecha_cierre = current_date",
]);
