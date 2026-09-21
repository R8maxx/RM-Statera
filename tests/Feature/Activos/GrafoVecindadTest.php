<?php

declare(strict_types=1);

use App\Domain\Activo\GrafoActivos;
use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\RegistrarDependencia;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La vecindad de un activo: nodos y ARISTAS
|--------------------------------------------------------------------------
|
| `vecindadDe()` existe porque `dependenciasDe()` y `dependientesDe()` no
| sirven para dibujar: devuelven los nodos alcanzables con su **profundidad
| mínima**, que es lo que una lista sangrada necesita.
|
| Lo que a un diagrama le falta de eso son los **rombos**. Si dos servicios se
| apoyan en la misma base de datos, la lista de cada uno la enseña una vez y a
| un salto; el diagrama tiene que dibujar **los dos vínculos**, porque es de
| donde a esa base de datos le sube la valoración efectiva. Perder una arista
| es perder el dato por el que existe la pantalla, y eso es lo que este fichero
| clava.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->grafo = app(GrafoActivos::class);
    $this->registrar = app(RegistrarDependencia::class);

    $this->activo = fn (string $codigo): Activo => Activo::factory()
        ->de($this->organizacion)
        ->create(['codigo' => $codigo, 'nombre' => "Activo {$codigo}"]);
});

it('devuelve el activo, lo que necesita y lo que lo sostiene', function (): void {
    $servicio = ($this->activo)('SRV');
    $aplicacion = ($this->activo)('APP');
    $base = ($this->activo)('BBDD');

    $this->registrar->vincular($servicio, $aplicacion);
    $this->registrar->vincular($aplicacion, $base);

    $vecindad = $this->grafo->vecindadDe($aplicacion);

    expect($vecindad['nodos']->pluck('codigo')->sort()->values()->all())
        ->toBe(['APP', 'BBDD', 'SRV'])
        ->and($vecindad['sentidos'][$aplicacion->id])->toBe('centro')
        ->and($vecindad['sentidos'][$base->id])->toBe('abajo')
        ->and($vecindad['sentidos'][$servicio->id])->toBe('arriba');
});

/**
 * **El rombo, que es la razón de ser del método.**
 *
 * Dos servicios sobre la misma base de datos. La lista de dependencias de cada
 * uno enseña la base una vez; el diagrama tiene que enseñar los dos vínculos,
 * porque la valoración de la base sube por los dos.
 */
it('devuelve las dos aristas de un rombo y no una', function (): void {
    $uno = ($this->activo)('SRV-1');
    $otro = ($this->activo)('SRV-2');
    $base = ($this->activo)('BBDD');

    $this->registrar->vincular($uno, $base);
    $this->registrar->vincular($otro, $base);

    $vecindad = $this->grafo->vecindadDe($base);

    expect($vecindad['nodos'])->toHaveCount(3)
        ->and($vecindad['aristas'])->toHaveCount(2);

    $haciaLaBase = array_filter(
        $vecindad['aristas'],
        static fn (array $arista): bool => $arista['hacia'] === $base->id,
    );

    expect($haciaLaBase)->toHaveCount(2);
});

/**
 * El atajo es el otro caso que la profundidad mínima esconde: si el servicio
 * depende de la aplicación **y** directamente de la base, la lista pone la base
 * a un salto y se calla el vínculo largo. El diagrama dibuja los dos.
 */
it('devuelve también el atajo que la profundidad mínima esconde', function (): void {
    $servicio = ($this->activo)('SRV');
    $aplicacion = ($this->activo)('APP');
    $base = ($this->activo)('BBDD');

    $this->registrar->vincular($servicio, $aplicacion);
    $this->registrar->vincular($aplicacion, $base);
    $this->registrar->vincular($servicio, $base);

    $vecindad = $this->grafo->vecindadDe($servicio);

    expect($vecindad['aristas'])->toHaveCount(3);
});

it('no trae aristas hacia activos que no están en el lienzo', function (): void {
    $servicio = ($this->activo)('SRV');
    $base = ($this->activo)('BBDD');
    $ajeno = ($this->activo)('OTRO');

    $this->registrar->vincular($servicio, $base);
    // `OTRO` también se apoya en la base, así que aparece en su vecindad; pero
    // en la del SERVICIO no, y su vínculo tampoco puede colarse.
    $this->registrar->vincular($ajeno, $base);

    $vecindad = $this->grafo->vecindadDe($servicio);
    $ids = $vecindad['nodos']->pluck('id')->all();

    foreach ($vecindad['aristas'] as $arista) {
        expect($ids)->toContain($arista['desde'])
            ->and($ids)->toContain($arista['hacia']);
    }
});

it('gana «abajo» cuando un activo cae de los dos lados', function (): void {
    $servicio = ($this->activo)('SRV');
    $aplicacion = ($this->activo)('APP');
    $base = ($this->activo)('BBDD');

    // La base sostiene a la aplicación, y la aplicación al servicio; desde la
    // base, el servicio está «arriba» a dos saltos y nada está «abajo».
    $this->registrar->vincular($servicio, $aplicacion);
    $this->registrar->vincular($aplicacion, $base);

    $vecindad = $this->grafo->vecindadDe($base);

    expect($vecindad['sentidos'][$servicio->id])->toBe('arriba')
        ->and($vecindad['sentidos'][$aplicacion->id])->toBe('arriba');
});

it('un activo suelto es su propia vecindad y sin aristas', function (): void {
    $solo = ($this->activo)('SOLO');

    $vecindad = $this->grafo->vecindadDe($solo);

    expect($vecindad['nodos'])->toHaveCount(1)
        ->and($vecindad['aristas'])->toBeEmpty()
        ->and($vecindad['sentidos'][$solo->id])->toBe('centro');
});

/**
 * La consulta de aristas es SQL crudo y no pasa por el global scope, así que
 * lleva `organizacion_id` explícito. Sin él, un vínculo de otra organización
 * entre dos ids que casaran se colaría en el lienzo.
 */
it('no cruza la frontera de organización', function (): void {
    $servicio = ($this->activo)('SRV');
    $base = ($this->activo)('BBDD');
    $this->registrar->vincular($servicio, $base);

    $otra = comoOrganizacion();
    $ajeno = Activo::factory()->de($otra)->create(['codigo' => 'AJENO']);
    $suBase = Activo::factory()->de($otra)->create(['codigo' => 'AJENA-BBDD']);
    app(RegistrarDependencia::class)->vincular($ajeno, $suBase);

    comoOrganizacion($this->organizacion);
    $vecindad = $this->grafo->vecindadDe($servicio);

    expect($vecindad['nodos'])->toHaveCount(2)
        ->and($vecindad['aristas'])->toHaveCount(1);
});

/* --- La pantalla ---------------------------------------------------------- */

it('pinta el grafo con el nivel efectivo de cada nodo', function (): void {
    $servicio = Activo::factory()->de($this->organizacion)->create([
        'codigo' => 'SRV',
        'valor_d' => 'alto',
    ]);

    // La base se valora BAJO y hereda «alto» del servicio que sostiene: es
    // exactamente lo que el color de la caja existe para enseñar.
    $base = Activo::factory()->de($this->organizacion)->create([
        'codigo' => 'BBDD',
        'valor_d' => 'bajo',
    ]);

    $this->registrar->vincular($servicio, $base);

    $this->actingAs($this->usuario)
        ->get("/activos/{$servicio->id}/grafo")
        ->assertInertia(function (AssertableInertia $pagina) use ($base): void {
            $pagina->component('activos/Grafo')
                ->has('nodos', 2)
                ->has('aristas', 1);

            $nodos = collect($pagina->toArray()['props']['nodos']);
            $laBase = $nodos->firstWhere('id', $base->id);

            expect($laBase['nivel'])->toBe('alto')
                ->and($laBase['nivelTono'])->toBe('alta')
                ->and($laBase['sentido'])->toBe('abajo');
        });
});

it('no enseña el grafo de un activo de otra organización', function (): void {
    $otra = comoOrganizacion();
    $ajeno = Activo::factory()->de($otra)->create();

    comoOrganizacion($this->organizacion);

    // 404 y no 403: decir «existe pero no es tuyo» ya sería filtrar.
    $this->actingAs($this->usuario)
        ->get("/activos/{$ajeno->id}/grafo")
        ->assertNotFound();
});
