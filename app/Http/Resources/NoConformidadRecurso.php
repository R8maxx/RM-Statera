<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
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
 * El registro de no conformidades: § 4.13.
 *
 * Las cifras de las acciones correctivas llegan **por subconsulta y no por
 * join**, por el mismo motivo que las versiones de un documento y los puntos de
 * una auditoría: un join contra la pivote multiplicaría las filas —una no
 * conformidad con tres acciones saldría tres veces— y la paginación contaría mal.
 * Esas subconsultas van en SQL crudo y no pasan por el scope de Eloquent: ahí
 * quien filtra es RLS, que es justo el caso para el que existe la tercera capa.
 *
 * **La columna que de verdad se mira es «Plazo»**, como en tareas: una fecha sola
 * obliga a compararla con hoy fila a fila, y este registro se recorre buscando
 * justo lo que se pasó. Y el rojo se gasta ahí y en la columna de verificación,
 * que son las dos cosas que van mal de verdad — los estados no lo gastan.
 *
 * @extends Recurso<NoConformidad>
 */
final class NoConformidadRecurso extends Recurso
{
    public function clave(): string
    {
        return 'no-conformidades';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'No conformidad',
            plural: 'No conformidades',
            descripcion: 'Qué falló, por qué, qué se hizo y si funcionó. La cláusula 10.2 pide las cuatro cosas, y la última es la que se olvida.',
            vacio: 'No hay no conformidades registradas. Se abren desde un hallazgo de auditoría o a mano.',
        );
    }

    /** @return Builder<NoConformidad> */
    public function consulta(): Builder
    {
        $acciones = fn (string $condicion): string => <<<SQL
            (select count(*) from no_conformidad_tarea nct
                join tareas t on t.id = nct.tarea_id
                where nct.no_conformidad_id = no_conformidades.id {$condicion})
        SQL;

        return NoConformidad::query()
            ->select('no_conformidades.*')
            ->selectRaw($acciones('').' as acciones_total')
            ->selectRaw($acciones("and t.estado not in ('hecha', 'descartada')").' as acciones_abiertas')
            ->with(['responsable', 'hallazgo.auditoria']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('8rem'),

            Columna::texto('descripcion', 'Descripción'),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (NoConformidad $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            /*
             * «Tratada» y no «Cerrada» para el estado cerrado: ahí todavía le
             * falta la verificación de eficacia, y llamarla cerrada en la columna
             * del plazo sería decir que está resuelta. Anulada se nombra aparte
             * porque no se trató nada.
             */
            Columna::badge('plazo', 'Plazo')
                ->ordenable('fecha_prevista')
                ->ancho('9rem')
                ->formato(function (NoConformidad $fila): ValorEtiquetado {
                    $plazo = Plazo::para(
                        $fila->fecha_prevista,
                        $fila->estado->esCerrada(),
                        $fila->fecha_cierre,
                        $fila->haVencido(),
                        $fila->estado === EstadoNoConformidad::Anulada ? 'Anulada' : 'Tratada',
                    );

                    return new ValorEtiquetado(
                        $plazo->fecha ?? '',
                        $plazo->etiqueta,
                        $plazo->tono,
                        null,
                    );
                }),

            /*
             * Con su denominador, como toda cifra del producto: «2» no dice nada
             * y «2 de 3» sí. Cero sobre cero es además lo que el indicador «Sin
             * acción correctiva» señala, y aquí se ve fila a fila.
             */
            Columna::texto('acciones', 'Acciones')
                ->alinear(Alineacion::Derecha)
                ->ancho('8rem')
                ->ayuda('Acciones correctivas abiertas sobre el total vinculado.')
                ->formato(fn (NoConformidad $fila): string => sprintf(
                    '%d de %d',
                    (int) $fila->getAttribute('acciones_abiertas'),
                    (int) $fila->getAttribute('acciones_total'),
                )),

            Columna::texto('origen', 'Origen')
                ->ordenable()
                ->formato(fn (NoConformidad $fila): string => $fila->origen->etiqueta()),

            Columna::texto('responsable', 'Responsable')
                ->formato(fn (NoConformidad $fila): ?string => $fila->responsable?->name),

            Columna::texto('auditoria', 'Auditoría')
                ->oculta()
                ->formato(fn (NoConformidad $fila): ?string => $fila->hallazgo?->auditoria?->codigo),

            Columna::fecha('fecha_deteccion', 'Detectada')->ordenable()->oculta(),
            Columna::fecha('fecha_verificacion', 'Verificada')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'descripcion' => 'descripcion',
                'analisis_causa_raiz' => 'descripcion',
            ])->placeholder('Buscar por código, descripción o causa raíz…'),

            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoNoConformidad $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoNoConformidad::cases(),
            )),

            Filtro::multiSelect('origen', 'Origen', array_map(
                static fn (OrigenNoConformidad $origen): Opcion => new Opcion($origen->value, $origen->etiqueta()),
                OrigenNoConformidad::cases(),
            )),

            /*
             * Acotado a la organización a mano: `User` no lleva
             * `PerteneceAOrganizacion` —la autenticación tiene que poder
             * encontrar a alguien antes de saber de qué organización es—, así que
             * aquí no hay scope global ni RLS que tapen el cruce. Sin este
             * `where`, el desplegable lista a los usuarios de todos los clientes.
             */
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),

            Filtro::rangoFechas('fecha_prevista', 'Fecha prevista')->enColumna('plazo'),

            /*
             * Por scope, no con la condición escrita otra vez aquí: son las mismas
             * que cuenta `RegistroNoConformidades`, y la clave de cada filtro es la
             * clave de su indicador. Con la condición duplicada, el panel dirá 12 y
             * la tabla enseñará 9.
             */
            Filtro::porScope('abiertas', 'Sólo abiertas', 'abiertas')->enColumna('estado'),
            Filtro::porScope('vencidas', 'Fuera de plazo', 'vencidas')->enColumna('plazo'),
            Filtro::porScope('pendientes_de_verificar', 'Sin verificar', 'pendientesDeVerificar')->enColumna('estado'),
            Filtro::porScope('sin_accion', 'Sin acción correctiva', 'sinAccion')->enColumna('acciones'),
            Filtro::porScope('sin_causa_raiz', 'Sin causa raíz', 'sinCausaRaiz')->enColumna('descripcion'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/no-conformidades/{id}'),
            Accion::eliminar(
                '/no-conformidades/{id}',
                '¿Eliminar la no conformidad? Se pierde su histórico y el hallazgo se queda sin tratamiento. '
                .'Si lo que se quiere es dejar constancia de que no era una no conformidad, anúlala con su motivo.',
            )->permiso(Permiso::NoConformidadesGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva no conformidad', '/no-conformidades/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::NoConformidadesGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha_deteccion';
    }
}
