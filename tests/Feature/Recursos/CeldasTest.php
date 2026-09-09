<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\ConsultaRecurso;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\ValorEnlace;
use App\Http\Resources\Definicion\ValorProgreso;
use App\Http\Resources\Recurso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Celdas de enlace y de progreso
|--------------------------------------------------------------------------
|
| `TipoColumna::Enlace` y `TipoColumna::Progreso` existían en el enum desde el
| principio, pero sin una forma que transportara el destino o el denominador:
| la celda recibía una cadena o un número suelto y `CeldaValor` la dejaba caer
| al texto plano. `ValorEnlace` y `ValorProgreso` cierran ese hueco.
|
| Todavía no hay ningún módulo que declare estas columnas, así que se prueban
| contra un recurso definido aquí. Cuando el inventario de activos las use, el
| contrato ya está fijado.
|
*/

/** @extends Recurso<Sistema> */
final class RecursoDeCeldas extends Recurso
{
    public function clave(): string
    {
        return 'celdas';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(singular: 'Celda', plural: 'Celdas');
    }

    /** @return Builder<Sistema> */
    public function consulta(): Builder
    {
        return Sistema::query();
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::enlace('ficha', 'Ficha')->formato(
                static fn (Sistema $sistema): ValorEnlace => new ValorEnlace(
                    etiqueta: $sistema->codigo,
                    url: "/sistemas/{$sistema->id}/editar",
                ),
            ),
            Columna::enlace('boe', 'BOE')->formato(
                static fn (Sistema $sistema): ValorEnlace => new ValorEnlace(
                    etiqueta: 'RD 311/2022',
                    url: 'https://www.boe.es/eli/es/rd/2022/05/03/311',
                    externo: true,
                ),
            ),
            Columna::progreso('avance', 'Avance')->formato(
                static fn (Sistema $sistema): ValorProgreso => new ValorProgreso(
                    porcentaje: 62,
                    hechas: 31,
                    de: 50,
                ),
            ),
        ];
    }
}

function primeraFilaDeCeldas(): array
{
    $organizacion = comoOrganizacion();

    Sistema::factory()
        ->de($organizacion)
        ->conMarco(Marco::factory()->create())
        ->create(['codigo' => 'SIS-01']);

    $resultado = (new ConsultaRecurso(new RecursoDeCeldas))->ejecutar(Request::create('/'));

    return $resultado['filas'][0];
}

it('serializa una celda de enlace interno con su etiqueta y su destino', function (): void {
    $fila = primeraFilaDeCeldas();

    expect($fila['ficha'])->toBeInstanceOf(ValorEnlace::class)
        ->and($fila['ficha']->etiqueta)->toBe('SIS-01')
        ->and($fila['ficha']->url)->toStartWith('/sistemas/')
        ->and($fila['ficha']->externo)->toBeFalse();
});

it('marca como externo el enlace que sale de la aplicación', function (): void {
    $fila = primeraFilaDeCeldas();

    expect($fila['boe']->externo)->toBeTrue()
        ->and($fila['boe']->url)->toStartWith('https://');
});

it('lleva el numerador y el denominador junto al porcentaje', function (): void {
    $fila = primeraFilaDeCeldas();

    // Un «62 %» suelto no es un dato que un auditor pueda contrastar: hace
    // falta saber sobre cuántos requisitos se calcula.
    expect($fila['avance'])->toBeInstanceOf(ValorProgreso::class)
        ->and($fila['avance']->porcentaje)->toBe(62)
        ->and($fila['avance']->hechas)->toBe(31)
        ->and($fila['avance']->de)->toBe(50);
});

it('admite un progreso sin denominador', function (): void {
    $valor = new ValorProgreso(porcentaje: 40);

    expect($valor->hechas)->toBeNull()
        ->and($valor->de)->toBeNull();
});
