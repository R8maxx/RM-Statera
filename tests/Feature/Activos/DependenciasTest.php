<?php

declare(strict_types=1);

use App\Domain\Activo\Excepciones\DependenciaCiclicaException;
use App\Domain\Activo\GrafoActivos;
use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\RegistrarDependencia;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| El grafo de dependencias
|--------------------------------------------------------------------------
|
| Dos cosas se prueban aquí y las dos duelen en silencio si fallan: que el
| recorrido llegue hasta el final de la cadena —si se queda en el primer salto,
| la valoración no se propaga y nadie se entera— y que un ciclo no llegue a
| escribirse, porque una CTE recursiva contra un grafo con un ciclo no devuelve
| un resultado raro: no termina.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->grafo = app(GrafoActivos::class);
    $this->registrar = app(RegistrarDependencia::class);

    $this->activo = fn (string $codigo): Activo => Activo::factory()
        ->de($this->organizacion)
        ->create(['codigo' => $codigo, 'nombre' => "Activo {$codigo}"]);
});

it('recorre la cadena entera y no sólo el primer salto', function (): void {
    [$servicio, $aplicacion, $base] = [
        ($this->activo)('SRV'),
        ($this->activo)('APP'),
        ($this->activo)('BBDD'),
    ];

    $this->registrar->vincular($servicio, $aplicacion);
    $this->registrar->vincular($aplicacion, $base);

    $dependencias = $this->grafo->dependenciasDe($servicio);

    expect($dependencias->pluck('codigo')->all())->toBe(['APP', 'BBDD']);
    expect($dependencias->pluck('profundidad')->map(intval(...))->all())->toBe([1, 2]);
});

it('recorre la cadena hacia arriba para saber qué se cae con un activo', function (): void {
    [$servicio, $aplicacion, $base] = [
        ($this->activo)('SRV'),
        ($this->activo)('APP'),
        ($this->activo)('BBDD'),
    ];

    $this->registrar->vincular($servicio, $aplicacion);
    $this->registrar->vincular($aplicacion, $base);

    expect($this->grafo->dependientesDe($base)->pluck('codigo')->all())->toBe(['APP', 'SRV']);
    expect($this->grafo->dependientesDe($servicio))->toBeEmpty();
});

it('no repite un activo alcanzable por dos caminos y se queda con la distancia menor', function (): void {
    // Dos servicios distintos se apoyan en la misma base de datos, y uno de
    // ellos además directamente. Sin `DISTINCT ON`, la base saldría tres veces.
    [$uno, $otro, $intermedio, $base] = [
        ($this->activo)('SRV-1'),
        ($this->activo)('SRV-2'),
        ($this->activo)('APP'),
        ($this->activo)('BBDD'),
    ];

    $this->registrar->vincular($uno, $intermedio);
    $this->registrar->vincular($otro, $intermedio);
    $this->registrar->vincular($intermedio, $base);
    $this->registrar->vincular($uno, $base);

    $dependencias = $this->grafo->dependenciasDe($uno);

    expect($dependencias->pluck('codigo')->all())->toBe(['APP', 'BBDD']);
    expect((int) $dependencias->firstWhere('codigo', 'BBDD')?->getAttribute('profundidad'))->toBe(1);
});

it('rechaza que un activo dependa de sí mismo', function (): void {
    $activo = ($this->activo)('SRV');

    expect(fn () => $this->registrar->vincular($activo, $activo))
        ->toThrow(DependenciaCiclicaException::class);

    expect(DB::table('activo_dependencias')->count())->toBe(0);
});

it('rechaza el ciclo directo', function (): void {
    [$uno, $otro] = [($this->activo)('SRV'), ($this->activo)('APP')];

    $this->registrar->vincular($uno, $otro);

    expect(fn () => $this->registrar->vincular($otro, $uno))
        ->toThrow(DependenciaCiclicaException::class);

    expect(DB::table('activo_dependencias')->count())->toBe(1);
});

it('rechaza el ciclo indirecto de tres saltos', function (): void {
    [$uno, $dos, $tres] = [($this->activo)('A'), ($this->activo)('B'), ($this->activo)('C')];

    $this->registrar->vincular($uno, $dos);
    $this->registrar->vincular($dos, $tres);

    // C no puede depender de A: A ya llega hasta C, y cerrarlo dejaría la
    // propagación dando vueltas.
    expect(fn () => $this->registrar->vincular($tres, $uno))
        ->toThrow(DependenciaCiclicaException::class);

    expect(DB::table('activo_dependencias')->count())->toBe(2);
});

it('explica por dónde va la vuelta cuando rechaza un ciclo', function (): void {
    [$uno, $dos, $tres] = [($this->activo)('A'), ($this->activo)('B'), ($this->activo)('C')];

    $this->registrar->vincular($uno, $dos);
    $this->registrar->vincular($dos, $tres);

    expect(fn () => $this->registrar->vincular($tres, $uno))
        ->toThrow(DependenciaCiclicaException::class, 'a través de 2 vínculos');
});

it('vuelve a vincular sin fallar y actualiza la nota', function (): void {
    [$uno, $otro] = [($this->activo)('SRV'), ($this->activo)('APP')];

    $this->registrar->vincular($uno, $otro, 'Primera lectura.');
    $this->registrar->vincular($uno, $otro, 'La sede se sirve desde aquí.');

    expect(DB::table('activo_dependencias')->count())->toBe(1);
    expect($uno->dependeDe()->first()?->getRelationValue('pivot')?->getAttribute('nota'))
        ->toBe('La sede se sirve desde aquí.');
});

it('rellena organizacion_id en la pivote, que es lo que RLS exige', function (): void {
    [$uno, $otro] = [($this->activo)('SRV'), ($this->activo)('APP')];

    $this->registrar->vincular($uno, $otro);

    expect(DB::table('activo_dependencias')->value('organizacion_id'))
        ->toBe($this->organizacion->id);
});

it('no cruza la frontera de organización al recorrer el grafo', function (): void {
    [$uno, $otro] = [($this->activo)('SRV'), ($this->activo)('APP')];
    $this->registrar->vincular($uno, $otro);

    $ajena = Organizacion::factory()->create();
    comoOrganizacion($ajena);

    expect(app(GrafoActivos::class)->dependenciasDe($uno->id))->toBeEmpty();
});

it('se lleva el vínculo por delante al borrar el activo', function (): void {
    [$uno, $otro] = [($this->activo)('SRV'), ($this->activo)('APP')];
    $this->registrar->vincular($uno, $otro);

    $otro->delete();

    expect(DB::table('activo_dependencias')->count())->toBe(0);
});
