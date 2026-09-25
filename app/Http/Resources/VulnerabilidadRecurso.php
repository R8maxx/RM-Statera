<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\OrigenVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\Severidad;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\Alineacion;
use App\Http\Resources\Enums\MetodoAccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * El registro de vulnerabilidades: invariante 8, A.8.8 y `op.exp.4`.
 *
 * **El rojo es del plazo y de nada más**: una crítica en plazo se pinta con el
 * énfasis de su severidad, no de alarma. Los activos llegan por subconsulta,
 * por lo mismo que en proveedores.
 *
 * @extends Recurso<Vulnerabilidad>
 */
final class VulnerabilidadRecurso extends Recurso
{
    public function clave(): string
    {
        return 'vulnerabilidades';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Vulnerabilidad',
            plural: 'Vulnerabilidades',
            descripcion: 'Los fallos técnicos detectados, dónde están, cuánto pesan y hasta cuándo hay para arreglarlos. Es lo que piden A.8.8 de ISO y op.exp.4 del ENS.',
            vacio: 'No hay vulnerabilidades registradas. Si no se ha mirado nunca, eso no significa que no las haya.',
        );
    }

    /** @return Builder<Vulnerabilidad> */
    public function consulta(): Builder
    {
        return Vulnerabilidad::query()
            ->select('vulnerabilidades.*')
            ->selectRaw('(select count(*) from vulnerabilidad_activo va where va.vulnerabilidad_id = vulnerabilidades.id) as activos_afectados')
            ->with('responsable:id,name');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('9rem'),

            Columna::texto('titulo', 'Vulnerabilidad')->ordenable(),

            Columna::texto('cve', 'CVE')->ancho('10rem'),

            Columna::badge('severidad', 'Severidad')
                ->ancho('8rem')
                ->ayuda('Con puntuación CVSS se deriva de ella; sin puntuación, se declara.')
                ->formato(fn (Vulnerabilidad $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->severidad->value,
                    $fila->cvss_puntuacion === null
                        ? $fila->severidad->etiqueta()
                        : "{$fila->severidad->etiqueta()} · {$fila->cvss_puntuacion}",
                    $fila->severidad->tono(),
                )),

            Columna::badge('estado', 'Estado')
                ->ancho('10rem')
                ->formato(fn (Vulnerabilidad $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            Columna::badge('fecha_limite', 'Plazo')
                ->ordenable()
                ->ancho('10rem')
                ->ayuda('Hasta cuándo hay para arreglarla. En rojo, si se pasó sin arreglo.')
                ->formato(fn (Vulnerabilidad $fila): ?ValorEtiquetado => $fila->fecha_limite === null || ! $fila->estado->correPlazo()
                    ? null
                    : new ValorEtiquetado(
                        $fila->fecha_limite->toDateString(),
                        $fila->fecha_limite->format('d/m/Y'),
                        $fila->fueraDePlazo() ? 'caducada' : 'planificado',
                        $fila->fueraDePlazo() ? 'TriangleAlert' : 'CalendarClock',
                    )),

            Columna::texto('activos_afectados', 'Activos')
                ->alinear(Alineacion::Derecha)
                ->ancho('6rem')
                ->formato(fn (Vulnerabilidad $fila): string => (string) (int) $fila->getAttribute('activos_afectados')),

            Columna::texto('origen', 'Origen')
                ->oculta()
                ->formato(fn (Vulnerabilidad $fila): string => $fila->origen->etiqueta()),

            Columna::fecha('fecha_deteccion', 'Detectada')->ordenable()->oculta(),

            Columna::texto('responsable', 'Responsable')
                ->formato(fn (Vulnerabilidad $fila): ?string => $fila->responsable?->name),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'titulo' => 'titulo',
                'cve' => 'cve',
                'descripcion' => 'titulo',
            ])->placeholder('Buscar por código, título o CVE…'),

            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoVulnerabilidad $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoVulnerabilidad::cases(),
            )),

            Filtro::multiSelect('severidad', 'Severidad', array_map(
                static fn (Severidad $severidad): Opcion => new Opcion($severidad->value, $severidad->etiqueta()),
                Severidad::cases(),
            )),

            Filtro::multiSelect('origen', 'Origen', array_map(
                static fn (OrigenVulnerabilidad $origen): Opcion => new Opcion($origen->value, $origen->etiqueta()),
                OrigenVulnerabilidad::cases(),
            )),

            // Acotado a la organización a mano: `User` no lleva el scope.
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),

            // Por scope y con la clave del indicador del panel que cuenta lo mismo.
            Filtro::porScope('fuera_de_plazo', 'Fuera de plazo', 'fueraDePlazo')->enColumna('fecha_limite'),
            Filtro::porScope('criticas_abiertas', 'Críticas abiertas', 'criticasAbiertas'),
            Filtro::porScope('sin_verificar', 'Mitigadas sin verificar', 'sinVerificar'),
            Filtro::porScope('aceptadas', 'Aceptadas', 'aceptadas'),
            Filtro::porScope('vivas', 'Sólo las que siguen vivas', 'vivas'),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha_deteccion';
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/vulnerabilidades/{id}'),
            Accion::editar('/vulnerabilidades/{id}/editar')->permiso(Permiso::VulnerabilidadesGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Registrar vulnerabilidad', '/vulnerabilidades/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::VulnerabilidadesGestionar->value),
        ];
    }
}
