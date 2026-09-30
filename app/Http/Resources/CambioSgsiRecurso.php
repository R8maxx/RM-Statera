<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Cambio\Enums\AmbitoCambio;
use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Enums\OrigenCambio;
use App\Domain\Cambio\Models\CambioSgsi;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Tarea\Plazo;
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
use Illuminate\Support\Carbon;

/**
 * El registro de cambios del SGSI: la cláusula 6.3.
 *
 * Las cifras del trabajo llegan **por subconsulta y no por join**, como en
 * mejoras y objetivos: un join contra la pivote multiplicaría las filas.
 *
 * **El único rojo es el plazo de un cambio aprobado**: la columna «Plazo» sólo lo
 * gasta cuando hay firma detrás. Un propuesto con la fecha pasada no incumple
 * nada, porque nadie se ha comprometido todavía; se rebaja a gris.
 *
 * @extends Recurso<CambioSgsi>
 */
final class CambioSgsiRecurso extends Recurso
{
    public function clave(): string
    {
        return 'cambios_sgsi';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Cambio del SGSI',
            plural: 'Cambios del SGSI',
            descripcion: 'Los cambios del propio sistema de gestión: alcance, política, roles, procesos. La cláusula 6.3 pide que se hagan de forma planificada.',
            vacio: 'No hay cambios del SGSI registrados. Se apuntan cuando se decide cambiar el sistema de gestión, antes de hacerlo.',
        );
    }

    /** @return Builder<CambioSgsi> */
    public function consulta(): Builder
    {
        $actuaciones = fn (string $condicion): string => <<<SQL
            (select count(*) from cambio_sgsi_tarea ct
                join tareas t on t.id = ct.tarea_id
                where ct.cambio_sgsi_id = cambios_sgsi.id {$condicion})
        SQL;

        return CambioSgsi::query()
            ->select('cambios_sgsi.*')
            ->selectRaw($actuaciones('').' as actuaciones_total')
            ->selectRaw($actuaciones("and t.estado not in ('hecha', 'descartada')").' as actuaciones_abiertas')
            ->with(['responsable']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('8rem'),

            Columna::texto('titulo', 'Cambio')->ordenable(),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (CambioSgsi $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            Columna::texto('ambito', 'Ámbito')
                ->ordenable()
                ->formato(fn (CambioSgsi $fila): string => $fila->ambito->etiqueta()),

            Columna::badge('plazo', 'Plazo')
                ->ordenable('fecha_prevista')
                ->ancho('9rem')
                ->formato(function (CambioSgsi $fila): ValorEtiquetado {
                    $plazo = self::plazo($fila);

                    return new ValorEtiquetado($plazo['fecha'] ?? '', $plazo['etiqueta'], $plazo['tono'], null);
                }),

            Columna::texto('actuaciones', 'Actuaciones')
                ->alinear(Alineacion::Derecha)
                ->ancho('8rem')
                ->ayuda('Actuaciones abiertas sobre el total vinculado.')
                ->formato(fn (CambioSgsi $fila): string => sprintf(
                    '%d de %d',
                    (int) $fila->getAttribute('actuaciones_abiertas'),
                    (int) $fila->getAttribute('actuaciones_total'),
                )),

            Columna::texto('responsable', 'Responsable')
                ->formato(fn (CambioSgsi $fila): ?string => $fila->responsable?->name),

            Columna::texto('origen', 'Origen')
                ->ordenable()
                ->oculta()
                ->formato(fn (CambioSgsi $fila): string => $fila->origen->etiqueta()),

            Columna::fecha('fecha_propuesta', 'Propuesto')->ordenable()->oculta(),
            Columna::fecha('fecha_implantacion', 'Implantado')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'titulo' => 'titulo',
                'descripcion' => 'titulo',
                'proposito' => 'titulo',
            ])->placeholder('Buscar por código, cambio o propósito…'),

            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoCambio $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoCambio::cases(),
            )),

            Filtro::multiSelect('ambito', 'Ámbito', array_map(
                static fn (AmbitoCambio $ambito): Opcion => new Opcion($ambito->value, $ambito->etiqueta()),
                AmbitoCambio::cases(),
            )),

            Filtro::multiSelect('origen', 'Origen', array_map(
                static fn (OrigenCambio $origen): Opcion => new Opcion($origen->value, $origen->etiqueta()),
                OrigenCambio::cases(),
            )),

            // Acotado a mano: `User` no lleva `PerteneceAOrganizacion`.
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),

            Filtro::rangoFechas('fecha_prevista', 'Fecha prevista')->enColumna('plazo'),

            // Los mismos scopes que cuenta `RegistroCambios`, con la misma clave.
            Filtro::porScope('fuera_de_plazo', 'Fuera de plazo', 'fueraDePlazo')->enColumna('plazo'),
            Filtro::porScope('abiertos', 'Sólo abiertos', 'abiertos')->enColumna('estado'),
            Filtro::porScope('sin_aprobar', 'Esperando firma', 'sinAprobar')->enColumna('estado'),
            Filtro::porScope('sin_trabajo', 'Sin actuación', 'sinTrabajo')->enColumna('actuaciones'),
            Filtro::porScope('sin_revisar', 'Sin revisar', 'sinRevisar')->enColumna('estado'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/cambios-sgsi/{id}'),
            Accion::eliminar(
                '/cambios-sgsi/{id}',
                '¿Eliminar el cambio? Se pierde su histórico y su firma. '
                .'Si lo que se quiere es dejar constancia de que no se hará, descártalo con su motivo.',
            )->permiso(Permiso::CambiosSgsiGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nuevo cambio', '/cambios-sgsi/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::CambiosSgsiGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha_propuesta';
    }

    /**
     * El plazo de un cambio, con la misma regla de fechas que una tarea.
     *
     * Hecho no es cerrado: un cambio implantado y sin revisar ya no tiene plazo
     * que cumplir, así que para el plazo cuenta como terminado desde que se hizo.
     *
     * **El rojo sólo con firma detrás.** Un cambio propuesto con la fecha pasada
     * no incumple nada, porque nadie se ha comprometido todavía: se dice «fecha
     * pasada» y en gris, como en una mejora.
     *
     * @return array{fecha: ?string, etiqueta: string, tono: string}
     */
    public static function plazo(CambioSgsi $cambio): array
    {
        $terminado = $cambio->estado->estaHecho() || $cambio->estado === EstadoCambio::Descartado;
        $pasada = ! $terminado
            && $cambio->fecha_prevista !== null
            && $cambio->fecha_prevista->isBefore(Carbon::today());

        $plazo = Plazo::para(
            $cambio->fecha_prevista,
            $terminado,
            $cambio->fecha_implantacion ?? $cambio->fecha_cierre,
            $pasada,
            $cambio->estado === EstadoCambio::Descartado ? 'Descartado' : 'Implantado',
        );

        if ($pasada && ! $cambio->estaFueraDePlazo()) {
            return ['fecha' => $plazo->fecha, 'etiqueta' => 'Fecha pasada', 'tono' => 'no_iniciado'];
        }

        return ['fecha' => $plazo->fecha, 'etiqueta' => $plazo->etiqueta, 'tono' => $plazo->tono];
    }
}
