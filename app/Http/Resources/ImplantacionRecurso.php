<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Catalogo\Enums\Exigencia;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Enums\NivelMadurez;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEscala;
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
            Columna::badge('exigencia', 'Exigencia')
                ->ordenable('exigencia_calculada')
                ->ayuda('Lo calcula el motor de categorización; no se escribe a mano.')
                ->formato(fn (Implantacion $fila): ?ValorEtiquetado => $fila->exigencia_calculada === null
                    ? null
                    : new ValorEtiquetado(
                        $fila->exigencia_calculada->valor,
                        $fila->exigencia_calculada->etiqueta(),
                        $fila->exigencia_calculada->tono(),
                    )),
            // La madurez es ordinal, no un estado: lo que se busca al recorrer
            // la columna es si L4 es más que L2, y un badge no dice eso.
            Columna::escala('nivel_madurez', 'Madurez')
                ->ordenable()
                ->oculta()
                ->formato(fn (Implantacion $fila): ?ValorEscala => $fila->nivel_madurez === null
                    ? null
                    : new ValorEscala(
                        $fila->nivel_madurez->valor(),
                        NivelMadurez::L5->valor(),
                        $fila->nivel_madurez->etiqueta(),
                        $fila->nivel_madurez->name,
                    )),
            Columna::texto('responsable', 'Responsable')
                ->formato(fn (Implantacion $fila): ?string => $fila->responsable?->name),
            Columna::fecha('fecha_objetivo', 'Fecha objetivo')->ordenable()->oculta(),
            Columna::badge('marco', 'Marco')
                ->oculta()
                ->formato(function (Implantacion $fila): ?ValorEtiquetado {
                    $codigo = $fila->requisito?->marco?->codigo;

                    return $codigo === null ? null : new ValorEtiquetado($codigo, $codigo, 'marco');
                }),
            Columna::texto('origen_exigencia', 'Origen')
                ->oculta()
                ->ayuda('Por qué se exige: la categoría, la modulación de una dimensión, un perfil o el propio catálogo.')
                ->formato(fn (Implantacion $fila): ?string => $fila->origen_exigencia?->etiqueta()),
            Columna::texto('justificacion', 'Justificación')->oculta(),
        ];
    }

    /**
     * La búsqueda cruza el código y el título del requisito, que es por donde
     * se busca de verdad en esta tabla: nadie recuerda el identificador de una
     * implantación, pero sí «copias de seguridad» o `mp.info.6`.
     *
     * @return list<Filtro>
     */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'requisitos.codigo' => 'codigo',
                'requisitos.titulo' => 'requisito',
            ])->placeholder('Buscar por código o requisito…'),
            // El código y el título del requisito viven en el catálogo, no
            // aquí; los filtros pueden apuntar a ellos porque `consulta()` ya
            // trae el join.
            Filtro::texto('codigo', 'Código')->campo('requisitos.codigo'),
            Filtro::texto('requisito', 'Requisito')->campo('requisitos.titulo'),
            Filtro::select('sistema_id', 'Sistema', fn (): array => Sistema::query()
                ->orderBy('codigo')
                ->get()
                ->map(fn (Sistema $sistema): Opcion => new Opcion(
                    (string) $sistema->id,
                    "{$sistema->codigo} — {$sistema->nombre}",
                ))
                ->all())->enColumna('sistema'),
            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoImplantacion $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoImplantacion::cases(),
            )),
            Filtro::booleano('aplica', 'Aplica'),
            Filtro::multiSelect('nivel_madurez', 'Madurez', array_map(
                static fn (NivelMadurez $nivel): Opcion => new Opcion($nivel->value, $nivel->etiqueta()),
                NivelMadurez::cases(),
            )),
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),
            Filtro::rangoFechas('fecha_objetivo', 'Fecha objetivo'),
            // La exigencia no es un conjunto cerrado —los refuerzos `Rn` los
            // trae el catálogo—, así que las opciones salen de lo que hay.
            Filtro::select('exigencia', 'Exigencia', fn (): array => Implantacion::query()
                ->select('exigencia_calculada')
                ->whereNotNull('exigencia_calculada')
                ->distinct()
                ->get()
                ->map(fn (Implantacion $fila): ?Exigencia => $fila->exigencia_calculada)
                ->filter()
                ->sortBy(fn (Exigencia $exigencia): int => $exigencia->peso())
                ->map(fn (Exigencia $exigencia): Opcion => new Opcion(
                    $exigencia->valor,
                    $exigencia->etiqueta(),
                ))
                ->values()
                ->all())->campo('exigencia_calculada'),
            Filtro::multiSelect('origen_exigencia', 'Origen', array_map(
                static fn (OrigenExigencia $origen): Opcion => new Opcion($origen->value, $origen->etiqueta()),
                OrigenExigencia::cases(),
            )),
            // Mismo nombre de parámetro que en Sistemas: `filter[marco_id]`
            // significa lo mismo en toda la aplicación.
            Filtro::select('marco_id', 'Marco', fn (): array => Marco::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Marco $marco): Opcion => new Opcion((string) $marco->id, $marco->nombre))
                ->all())->campo('requisitos.marco_id')->enColumna('marco'),
            Filtro::texto('justificacion', 'Justificación'),
        ];
    }

    /**
     * La ficha es la respuesta a las tres preguntas de una auditoría, así que
     * es la acción por defecto de la fila.
     *
     * @return list<Accion>
     */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/implantaciones/{id}'),
        ];
    }

    /** @return list<Accion> */
    public function accionesMasivas(): array
    {
        return [
            (new Accion('cambiar_estado', 'Cambiar estado', '/implantaciones/estado', MetodoAccion::Post))
                ->icono('CircleDot')
                ->permiso(Permiso::ImplantacionesGestionar->value),
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
