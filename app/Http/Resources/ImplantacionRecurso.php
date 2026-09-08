<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Enums\NivelMadurez;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * El centro del modelo, en modo tabla.
 *
 * Es la vista más ancha del producto — la Declaración de Aplicabilidad es una
 * consulta sobre esta tabla — así que la mitad de las columnas nacen ocultas y
 * se activan desde el selector.
 *
 * @extends Recurso<Implantacion>
 */
final class ImplantacionRecurso extends Recurso
{
    public function clave(): string
    {
        return 'implantaciones';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Implantación',
            plural: 'Implantaciones',
            descripcion: 'Qué aplica, cómo se cumple y desde cuándo. La Declaración de Aplicabilidad sale de aquí.',
            vacio: 'No hay implantaciones. Valora las dimensiones de un sistema y genera su conjunto exigible.',
        );
    }

    /** @return Builder<Implantacion> */
    public function consulta(): Builder
    {
        // El código y el orden del requisito se traen por join porque son la
        // ordenación natural de esta tabla y viven en el catálogo, no aquí.
        // `orden` es la secuencia del marco: ordenar por el código en texto
        // pondría `op.acc.10` antes que `op.acc.2`.
        return Implantacion::query()
            ->select('implantaciones.*')
            ->addSelect([
                'requisitos.codigo as codigo',
                'requisitos.orden as orden_requisito',
            ])
            ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
            ->with(['requisito.marco', 'sistema', 'responsable']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')
                ->ordenable('orden_requisito')
                ->anclada()
                ->ancho('8rem')
                ->formato(fn (Implantacion $fila): ?string => $fila->requisito?->codigo),
            Columna::texto('requisito', 'Requisito')
                ->formato(fn (Implantacion $fila): ?string => $fila->requisito?->titulo),
            Columna::texto('sistema', 'Sistema')
                ->formato(fn (Implantacion $fila): ?string => $fila->sistema?->codigo),
            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (Implantacion $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->value,
                )),
            Columna::booleano('aplica', 'Aplica')->ordenable(),
            Columna::texto('exigencia', 'Exigencia')
                ->ordenable('exigencia_calculada')
                ->ayuda('Lo calcula el motor de categorización; no se escribe a mano.')
                ->formato(fn (Implantacion $fila): ?string => $fila->exigencia_calculada?->valor),
            Columna::badge('nivel_madurez', 'Madurez')
                ->ordenable()
                ->oculta()
                ->formato(fn (Implantacion $fila): ?ValorEtiquetado => $fila->nivel_madurez === null
                    ? null
                    : new ValorEtiquetado(
                        $fila->nivel_madurez->value,
                        $fila->nivel_madurez->etiqueta(),
                        null,
                    )),
            Columna::texto('responsable', 'Responsable')
                ->formato(fn (Implantacion $fila): ?string => $fila->responsable?->name),
            Columna::fecha('fecha_objetivo', 'Fecha objetivo')->ordenable()->oculta(),
            Columna::texto('marco', 'Marco')
                ->oculta()
                ->formato(fn (Implantacion $fila): ?string => $fila->requisito?->marco?->codigo),
            Columna::texto('origen_exigencia', 'Origen')
                ->oculta()
                ->ayuda('Por qué se exige: la categoría, la modulación de una dimensión, un perfil o el propio catálogo.')
                ->formato(fn (Implantacion $fila): ?string => $fila->origen_exigencia?->value),
            Columna::texto('justificacion', 'Justificación')->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::select('sistema_id', 'Sistema', fn (): array => Sistema::query()
                ->orderBy('codigo')
                ->get()
                ->map(fn (Sistema $sistema): Opcion => new Opcion(
                    (string) $sistema->id,
                    "{$sistema->codigo} — {$sistema->nombre}",
                ))
                ->all()),
            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoImplantacion $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoImplantacion::cases(),
            )),
            Filtro::booleano('aplica', 'Sólo aplicables'),
            Filtro::multiSelect('nivel_madurez', 'Madurez', array_map(
                static fn (NivelMadurez $nivel): Opcion => new Opcion($nivel->value, $nivel->etiqueta()),
                NivelMadurez::cases(),
            )),
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all()),
            Filtro::rangoFechas('fecha_objetivo', 'Fecha objetivo'),
        ];
    }

    /** @return list<Accion> */
    public function accionesMasivas(): array
    {
        return [
            (new Accion('cambiar_estado', 'Cambiar estado', '/implantaciones/estado', MetodoAccion::Post))
                ->icono('CircleDot'),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }

    public function porPagina(): int
    {
        return 50;
    }

    /**
     * El estado actual viaja aparte de la celda: la acción masiva necesita saber
     * a qué estados puede pasar cada fila sin volver a preguntar al servidor.
     *
     * @return array<string, mixed>
     */
    public function extrasDeFila(Model $modelo): array
    {
        // La firma tiene que aceptar cualquier modelo para no romper la clase
        // base; aquí sólo llegan implantaciones.
        if (! $modelo instanceof Implantacion) {
            return [];
        }

        return [
            'estado_actual' => $modelo->estado->value,
            'transiciones' => array_map(
                static fn (EstadoImplantacion $estado): string => $estado->value,
                $modelo->estado->transicionesPermitidas(),
            ),
        ];
    }
}
