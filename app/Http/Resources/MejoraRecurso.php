<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\Mejora\Models\Mejora;
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

/**
 * El registro de oportunidades de mejora: la cláusula 10.1.
 *
 * Las cifras del trabajo llegan **por subconsulta y no por join**, por el mismo
 * motivo que en no conformidades y en objetivos: un join contra la pivote
 * multiplicaría las filas y la paginación contaría mal.
 *
 * **Y esta tabla no tiene ni una celda en rojo**, que es lo único que la separa a
 * simple vista del registro de al lado. Una mejora que no se ha hecho no incumple
 * nada, y una que se pasó de la fecha prevista tampoco: nadie se comprometió a
 * ella —eso es un objetivo de la 6.2, que sí lleva su rojo—. Se señala en gris
 * para que no se olvide, y ya.
 *
 * @extends Recurso<Mejora>
 */
final class MejoraRecurso extends Recurso
{
    public function clave(): string
    {
        return 'mejoras';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Oportunidad de mejora',
            plural: 'Oportunidades de mejora',
            descripcion: 'Lo que se puede hacer mejor sin que nada incumpla. La cláusula 10.1 pide mejorar de forma continua, y esto es donde se apunta.',
            vacio: 'No hay oportunidades de mejora registradas. Se abren desde un hallazgo de auditoría, desde un indicador que se queda corto o a mano.',
        );
    }

    /** @return Builder<Mejora> */
    public function consulta(): Builder
    {
        $actuaciones = fn (string $condicion): string => <<<SQL
            (select count(*) from mejora_tarea mt
                join tareas t on t.id = mt.tarea_id
                where mt.mejora_id = mejoras.id {$condicion})
        SQL;

        return Mejora::query()
            ->select('mejoras.*')
            ->selectRaw($actuaciones('').' as actuaciones_total')
            ->selectRaw($actuaciones("and t.estado not in ('hecha', 'descartada')").' as actuaciones_abiertas')
            ->with(['responsable', 'hallazgo.auditoria']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('8rem'),

            Columna::texto('titulo', 'Mejora')->ordenable(),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (Mejora $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            /*
             * «Prevista» y no «Plazo», y el tono nunca es rojo: aquí no hay plazo
             * que incumplir porque nadie se comprometió. `Plazo::para()` se
             * reutiliza igual —es la misma regla de fechas— y lo que se hace es
             * **rebajar el rojo a gris** en el único caso que lo gastaría.
             */
            Columna::badge('prevista', 'Prevista')
                ->ordenable('fecha_prevista')
                ->ancho('9rem')
                ->formato(function (Mejora $fila): ValorEtiquetado {
                    $plazo = Plazo::para(
                        $fila->fecha_prevista,
                        $fila->estado->esCerrada(),
                        $fila->fecha_cierre,
                        $fila->sePasoDeFecha(),
                        $fila->estado === EstadoMejora::Descartada ? 'Descartada' : 'Implantada',
                    );

                    return new ValorEtiquetado(
                        $plazo->fecha ?? '',
                        $plazo->etiqueta,
                        $plazo->tono === 'caducada' ? 'no_iniciado' : $plazo->tono,
                        null,
                    );
                }),

            Columna::texto('actuaciones', 'Actuaciones')
                ->alinear(Alineacion::Derecha)
                ->ancho('8rem')
                ->ayuda('Actuaciones abiertas sobre el total vinculado.')
                ->formato(fn (Mejora $fila): string => sprintf(
                    '%d de %d',
                    (int) $fila->getAttribute('actuaciones_abiertas'),
                    (int) $fila->getAttribute('actuaciones_total'),
                )),

            Columna::badge('origen', 'Origen')
                ->ordenable()
                ->formato(fn (Mejora $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->origen->value,
                    $fila->origen->etiqueta(),
                    $fila->origen->tono(),
                    $fila->origen->icono(),
                )),

            Columna::texto('responsable', 'Responsable')
                ->formato(fn (Mejora $fila): ?string => $fila->responsable?->name),

            Columna::texto('auditoria', 'Auditoría')
                ->oculta()
                ->formato(fn (Mejora $fila): ?string => $fila->hallazgo?->auditoria?->codigo),

            Columna::fecha('fecha_deteccion', 'Apuntada')->ordenable()->oculta(),
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
                'beneficio_esperado' => 'titulo',
            ])->placeholder('Buscar por código, mejora o beneficio esperado…'),

            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoMejora $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoMejora::cases(),
            )),

            Filtro::multiSelect('origen', 'Origen', array_map(
                static fn (OrigenMejora $origen): Opcion => new Opcion($origen->value, $origen->etiqueta()),
                OrigenMejora::cases(),
            )),

            /*
             * Acotado a la organización a mano: `User` no lleva
             * `PerteneceAOrganizacion`, así que aquí no hay scope global ni RLS
             * que tapen el cruce.
             */
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),

            Filtro::rangoFechas('fecha_prevista', 'Fecha prevista')->enColumna('prevista'),

            /*
             * Por scope, no con la condición escrita otra vez aquí: son los mismos
             * que cuenta `RegistroMejoras`, y la clave de cada filtro es la clave
             * de su indicador.
             */
            Filtro::porScope('abiertas', 'Sólo abiertas', 'abiertas')->enColumna('estado'),
            Filtro::porScope('sin_empezar', 'Sin empezar', 'sinEmpezar')->enColumna('estado'),
            Filtro::porScope('sin_trabajo', 'Sin nada en marcha', 'sinTrabajo')->enColumna('actuaciones'),
            Filtro::porScope('pasadas_de_fecha', 'Fuera de la fecha prevista', 'pasadasDeFecha')->enColumna('prevista'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/mejoras/{id}'),
            Accion::eliminar(
                '/mejoras/{id}',
                '¿Eliminar la mejora? Se pierde su histórico y el hallazgo se queda sin tratamiento. '
                .'Si lo que se quiere es dejar constancia de que no se va a hacer, descártala con su motivo.',
            )->permiso(Permiso::MejorasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva mejora', '/mejoras/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::MejorasGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha_deteccion';
    }
}
