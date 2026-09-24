<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Contexto\Enums\EstadoAnalisis;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\Alineacion;
use Illuminate\Database\Eloquent\Builder;

/**
 * El historial de revisiones del contexto: la entrada «cambios de contexto» de
 * la cláusula 9.3.
 *
 * Era una tarjeta por revisión, con las mismas cuatro cifras en cada una, y eso
 * es una tabla (DESIGN.md §9): lo que se pregunta es cuánto entró y salió en
 * cada revisión **comparado con las demás**, y en tarjetas había que leerlas de
 * una en una.
 *
 * Calcado de `RevisionDireccionRecurso`, que es su pareja: una fila al año, sin
 * búsqueda por texto —con cinco filas es ruido— y con el filtro de estado.
 *
 * Las altas y las bajas se cuentan de las filas —cada cuestión y cada parte
 * sabe qué análisis la dio de alta o la retiró—, no comparando instantáneas.
 *
 * @extends Recurso<AnalisisContexto>
 */
final class AnalisisContextoRecurso extends Recurso
{
    public function clave(): string
    {
        return 'contexto-analisis';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Revisión del contexto',
            plural: 'Revisiones del contexto',
            descripcion: 'Qué entró y qué salió en cada revisión. Es la entrada de «cambios de contexto» que pide la cláusula 9.3.',
            vacio: 'Todavía no hay ninguna revisión. La primera se abre sola al registrar la primera cuestión del DAFO.',
        );
    }

    /** @return Builder<AnalisisContexto> */
    public function consulta(): Builder
    {
        return AnalisisContexto::query()
            ->with('aprobadoPor')
            ->withCount(['cuestionesDadasDeAlta', 'cuestionesRetiradas', 'partesDadasDeAlta', 'partesRetiradas']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('numero', 'Revisión')
                ->ordenable()
                ->anclada()
                ->ancho('10rem')
                ->formato(fn (AnalisisContexto $fila): string => $fila->etiqueta()),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (AnalisisContexto $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            Columna::fecha('fecha_analisis', 'Analizado')->ordenable(),

            Columna::texto('altas', 'Altas')
                ->alinear(Alineacion::Derecha)
                ->ancho('6rem')
                ->ayuda('Cuestiones del DAFO y partes interesadas que dio de alta esta revisión.')
                ->formato(fn (AnalisisContexto $fila): string => (string) ((int) $fila->getAttribute('cuestiones_dadas_de_alta_count') + (int) $fila->getAttribute('partes_dadas_de_alta_count'))),

            Columna::texto('bajas', 'Bajas')
                ->alinear(Alineacion::Derecha)
                ->ancho('6rem')
                ->ayuda('Cuestiones y partes interesadas que retiró esta revisión.')
                ->formato(fn (AnalisisContexto $fila): string => (string) ((int) $fila->getAttribute('cuestiones_retiradas_count') + (int) $fila->getAttribute('partes_retiradas_count'))),

            /*
             * La pregunta del cambio climático (enmienda de 2024 a la 4.1). Sin
             * contestar no es «no»: es que nadie la ha contestado, y se dice.
             */
            Columna::texto('clima_pertinente', 'Cambio climático')
                ->formato(fn (AnalisisContexto $fila): string => match ($fila->clima_pertinente) {
                    true => 'Pertinente',
                    false => 'No pertinente',
                    null => 'Sin contestar',
                }),

            Columna::texto('aprobado_por', 'Aprobado por')
                ->formato(fn (AnalisisContexto $fila): ?string => $fila->aprobadoPor?->name),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoAnalisis $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoAnalisis::cases(),
            ))->enColumna('estado'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/contexto/analisis/{id}'),
        ];
    }

    /**
     * Del más reciente al más antiguo, con el borrador delante: en PostgreSQL un
     * orden descendente pone los `NULL` primero, y el borrador es el único sin
     * número.
     */
    public function ordenPorDefecto(): string
    {
        return '-numero';
    }
}
