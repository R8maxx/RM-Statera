<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Auditoria\Enums\EstadoAuditoria;
use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\Alineacion;
use App\Http\Resources\Enums\MetodoAccion;
use Illuminate\Database\Eloquent\Builder;

/**
 * El registro de auditorías: § 4.12.
 *
 * Las cifras de la checklist llegan **por subconsulta y no por join**, por el
 * mismo motivo que las versiones de un documento: un join contra
 * `auditoria_puntos` multiplicaría las filas —una auditoría con 52 líneas saldría
 * 52 veces— y la paginación contaría mal. Esas subconsultas van en SQL crudo y no
 * pasan por el scope de Eloquent: ahí quien filtra es RLS, que es justo el caso
 * para el que existe la tercera capa.
 *
 * @extends Recurso<Auditoria>
 */
class AuditoriaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'auditorias';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Auditoría',
            plural: 'Auditorías',
            descripcion: 'Internas, externas y autoevaluación del ENS. Una auditoría cerrada ya no se puede cambiar.',
            vacio: 'No hay auditorías registradas. La cláusula 9.2 de ISO pide al menos una al año.',
        );
    }

    /** @return Builder<Auditoria> */
    public function consulta(): Builder
    {
        $puntos = fn (string $expresion): string => "(select {$expresion} from auditoria_puntos ap where ap.auditoria_id = auditorias.id)";
        $hallazgos = fn (string $expresion): string => "(select {$expresion} from hallazgos h where h.auditoria_id = auditorias.id)";

        return Auditoria::query()
            ->select('auditorias.*')
            ->selectRaw($puntos('count(*)').' as puntos_total')
            ->selectRaw($puntos("count(*) filter (where ap.resultado <> 'pendiente')").' as puntos_revisados')
            ->selectRaw($hallazgos('count(*)').' as hallazgos_total')
            ->selectRaw($hallazgos("count(*) filter (where h.tipo in ('nc_mayor', 'nc_menor'))").' as no_conformidades')
            ->with(['sistema', 'cerradaPor']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('9rem'),

            Columna::badge('tipo', 'Tipo')
                ->ordenable()
                ->formato(fn (Auditoria $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->tipo->value,
                    $fila->tipo->etiquetaCorta(),
                    $fila->tipo->tono(),
                    $fila->tipo->icono(),
                )),

            Columna::texto('sistema', 'Sistema')
                ->formato(fn (Auditoria $fila): ?string => $fila->sistema?->codigo),

            Columna::fecha('fecha', 'Fecha')->ordenable(),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (Auditoria $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            /*
             * Con su denominador, como toda cifra del producto: «12» no dice nada
             * y «12 de 52» sí. Es la razón entera por la que existe la checklist.
             */
            Columna::texto('avance', 'Revisadas')
                ->alinear(Alineacion::Derecha)
                ->ancho('7rem')
                ->formato(fn (Auditoria $fila): string => sprintf(
                    '%d de %d',
                    (int) $fila->getAttribute('puntos_revisados'),
                    (int) $fila->getAttribute('puntos_total'),
                )),

            Columna::numero('hallazgos', 'Hallazgos')
                ->ordenable('hallazgos_total')
                ->alinear(Alineacion::Derecha)
                ->ancho('6rem')
                ->formato(fn (Auditoria $fila): int => (int) $fila->getAttribute('hallazgos_total')),

            Columna::numero('no_conformidades', 'NC')
                ->ordenable('no_conformidades')
                ->alinear(Alineacion::Derecha)
                ->ancho('5rem')
                ->ayuda('Hallazgos de tipo no conformidad, mayor o menor.')
                ->formato(fn (Auditoria $fila): int => (int) $fila->getAttribute('no_conformidades')),

            Columna::texto('auditor', 'Auditor')->oculta(),
            Columna::texto('entidad_certificadora', 'Entidad')->oculta(),
            Columna::fecha('fecha_cierre', 'Cerrada')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'alcance' => 'codigo',
                'auditor' => 'auditor',
            ])->placeholder('Buscar por código, alcance o auditor…'),

            Filtro::multiSelect('tipo', 'Tipo', array_map(
                static fn (TipoAuditoria $tipo): Opcion => new Opcion($tipo->value, $tipo->etiqueta()),
                TipoAuditoria::cases(),
            )),

            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoAuditoria $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoAuditoria::cases(),
            )),

            Filtro::select('sistema_id', 'Sistema', fn (): array => Sistema::query()
                ->orderBy('codigo')
                ->get()
                ->map(fn (Sistema $sistema): Opcion => new Opcion(
                    (string) $sistema->id,
                    "{$sistema->codigo} — {$sistema->nombre}",
                ))
                ->all())->enColumna('sistema'),

            Filtro::rangoFechas('fecha', 'Fecha'),

            Filtro::porScope('abiertas', 'Sin cerrar', 'abiertas')->enColumna('estado'),
            Filtro::porScope('con_no_conformidades', 'Con no conformidades', 'conNoConformidades')
                ->enColumna('no_conformidades'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/auditorias/{id}'),
            Accion::eliminar('/auditorias/{id}', '¿Eliminar esta auditoría? Se llevará su checklist y sus hallazgos.')
                ->permiso(Permiso::AuditoriasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva auditoría', '/auditorias/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::AuditoriasGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha';
    }
}
