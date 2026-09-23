<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEnlace;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las pruebas de un plan de continuidad: § 4.11 y `op.cont.3`.
 *
 * **`plan` es un enlace y no texto**, porque es exactamente lo que llevaría a
 * alguien a comprobar qué dice el plan de continuidad que esta prueba
 * contrasta.
 *
 * **`resultado` es `null` mientras la prueba sigue `planificada`.** La celda
 * lo pinta como «—», sin gastar ningún tono: no hay resultado que colorear
 * todavía.
 *
 * @extends Recurso<PruebaContinuidad>
 */
final class PruebaContinuidadRecurso extends Recurso
{
    public function clave(): string
    {
        return 'continuidad_pruebas';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Prueba',
            plural: 'Pruebas de continuidad',
            descripcion: 'Las comprobaciones de que un plan de continuidad no es sólo papel: qué se probó, cuándo y con qué resultado. § 4.11, op.cont.3.',
            vacio: 'No hay ninguna prueba registrada. Un plan sin pruebas encima es una promesa sin contrastar.',
        );
    }

    /** @return Builder<PruebaContinuidad> */
    public function consulta(): Builder
    {
        return PruebaContinuidad::query()->with(['plan', 'responsable']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('8rem'),

            Columna::texto('titulo', 'Prueba')->ordenable(),

            Columna::enlace('plan', 'Plan')
                ->ancho('12rem')
                ->formato(fn (PruebaContinuidad $fila): ?ValorEnlace => $fila->plan === null ? null : new ValorEnlace(
                    $fila->plan->codigo,
                    "/documentos/{$fila->documento_id}",
                )),

            Columna::badge('tipo', 'Tipo')
                ->ordenable()
                ->ancho('10rem')
                ->formato(fn (PruebaContinuidad $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->tipo->value,
                    $fila->tipo->etiqueta(),
                    $fila->tipo->tono(),
                    $fila->tipo->icono(),
                )),

            Columna::fecha('prevista', 'Prevista')
                ->ordenable('fecha_prevista')
                ->formato(fn (PruebaContinuidad $fila): string => $fila->fecha_prevista->toDateString()),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->ancho('9rem')
                ->formato(fn (PruebaContinuidad $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            Columna::badge('resultado', 'Resultado')
                ->ancho('12rem')
                ->formato(fn (PruebaContinuidad $fila): ?ValorEtiquetado => $fila->resultado === null ? null : new ValorEtiquetado(
                    $fila->resultado->value,
                    $fila->resultado->etiqueta(),
                    $fila->resultado->tono(),
                    $fila->resultado->icono(),
                )),

            Columna::texto('responsable', 'Responsable')
                ->oculta()
                ->formato(fn (PruebaContinuidad $fila): ?string => $fila->responsable?->name),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoPrueba $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoPrueba::cases(),
            )),

            Filtro::multiSelect('resultado', 'Resultado', array_map(
                static fn (ResultadoPrueba $resultado): Opcion => new Opcion($resultado->value, $resultado->etiqueta()),
                ResultadoPrueba::cases(),
            )),

            /*
             * Por scope y con la condición de `PruebaContinuidad::scopeVencidas()`,
             * para que la cifra del panel y este filtro nunca digan cosas
             * distintas de la misma fila.
             */
            Filtro::porScope('vencidas', 'Vencidas', 'vencidas')->enColumna('prevista'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/continuidad/pruebas/{id}'),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Planificar prueba', '/continuidad/pruebas/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ContinuidadGestionar->value),
        ];
    }

    /**
     * La clave de la columna, `prevista`, no `fecha_prevista`: mismo fallo que
     * ya evita el docblock de `BiaServicioRecurso::ordenPorDefecto()`. Y
     * ascendente, sin el signo `-`: lo primero es lo que toca planificar o
     * probar antes, no lo último que se ha probado.
     */
    public function ordenPorDefecto(): string
    {
        return 'prevista';
    }
}
