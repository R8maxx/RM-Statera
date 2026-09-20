<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
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
 * Los objetivos de seguridad de la información: la cláusula 6.2.
 *
 * Las cifras de las actuaciones llegan **por subconsulta y no por join**, por el
 * mismo motivo que las acciones correctivas de una no conformidad y las versiones
 * de un documento: un join contra la pivote multiplicaría las filas —un objetivo
 * con tres tareas saldría tres veces— y la paginación contaría mal. Esas
 * subconsultas van en SQL crudo y no pasan por el scope de Eloquent: ahí quien
 * filtra es RLS, que es justo el caso para el que existe la tercera capa.
 *
 * **El avance NO llega por subconsulta**, y es la excepción que conviene explicar:
 * se lee de los indicadores vinculados con `Avance`, que aplica exactamente la
 * misma regla que el badge de la ficha de un indicador. Escribirla en SQL sería
 * tenerla por tercera vez —ya está en `SentidoIndicador::alcanza()` y en
 * `Indicador::scopeFueraDeObjetivo()`, con un test que fija que coinciden— y una
 * tercera copia no tendría quién la vigilase. El coste es dos consultas más por
 * página, no una por fila, porque los indicadores y su última medición vienen
 * cargados de antemano.
 *
 * **La columna que de verdad se mira es «Plazo»**, como en tareas y en no
 * conformidades, y es donde se gasta el único rojo del módulo: quedarse corto
 * respecto a la cifra no es rojo —eso es la distancia que queda—, pasarse de la
 * fecha a la que la organización se comprometió, sí.
 *
 * @extends Recurso<Objetivo>
 */
final class ObjetivoRecurso extends Recurso
{
    public function clave(): string
    {
        return 'objetivos';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Objetivo',
            plural: 'Objetivos de seguridad',
            descripcion: 'A qué se compromete la organización, para cuándo, con qué recursos y con qué cifra se comprueba.',
            vacio: 'No hay objetivos de seguridad registrados. La cláusula 6.2 pide al menos uno, y que sea medible.',
        );
    }

    /** @return Builder<Objetivo> */
    public function consulta(): Builder
    {
        $actuaciones = fn (string $condicion): string => <<<SQL
            (select count(*) from objetivo_tarea ot
                join tareas t on t.id = ot.tarea_id
                where ot.objetivo_id = objetivos_seguridad.id {$condicion})
        SQL;

        return Objetivo::query()
            ->select('objetivos_seguridad.*')
            ->selectRaw($actuaciones('').' as actuaciones_total')
            ->selectRaw($actuaciones("and t.estado not in ('hecha', 'descartada')").' as actuaciones_abiertas')
            ->with(['responsable', 'indicadores.ultimaMedicion']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('9rem'),

            Columna::texto('titulo', 'Objetivo')->ordenable(),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (Objetivo $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            /*
             * «Cerrado» y no «Alcanzado» para la etiqueta del plazo: cómo acabó lo
             * dice el estado, y repetirlo aquí haría que un objetivo no alcanzado
             * enseñara dos veces la misma mala noticia. Retirado se nombra aparte
             * porque no llegó a perseguirse hasta el final.
             */
            Columna::badge('plazo', 'Plazo')
                ->ordenable('fecha_objetivo')
                ->ancho('9rem')
                ->formato(function (Objetivo $fila): ValorEtiquetado {
                    $plazo = Plazo::para(
                        $fila->fecha_objetivo,
                        $fila->estado->esCerrado(),
                        $fila->fecha_cierre,
                        $fila->haVencido(),
                        $fila->estado === EstadoObjetivo::Retirado ? 'Retirado' : 'Cerrado',
                    );

                    return new ValorEtiquetado(
                        $plazo->fecha ?? '',
                        $plazo->etiqueta,
                        $plazo->tono,
                        null,
                    );
                }),

            /*
             * Cuántos de sus indicadores medidos llegan a su objetivo. Con su
             * denominador, como toda cifra del producto, y con los dos casos
             * vacíos nombrados aparte: «sin indicador» es la 6.2 e) sin hacer y
             * «sin medir» es la 9.1 sin hacer, y no son lo mismo.
             */
            Columna::badge('avance', 'Evaluación')
                ->ancho('9rem')
                ->ayuda('Indicadores vinculados que alcanzan su objetivo, sobre los que tienen medición.')
                ->formato(function (Objetivo $fila): ValorEtiquetado {
                    $avance = $fila->avance();

                    return new ValorEtiquetado($avance->etiqueta(), $avance->etiqueta(), $avance->tono(), null);
                }),

            Columna::texto('actuaciones', 'Actuaciones')
                ->alinear(Alineacion::Derecha)
                ->ancho('8rem')
                ->ayuda('Actuaciones abiertas sobre el total vinculado.')
                ->formato(fn (Objetivo $fila): string => sprintf(
                    '%d de %d',
                    (int) $fila->getAttribute('actuaciones_abiertas'),
                    (int) $fila->getAttribute('actuaciones_total'),
                )),

            Columna::texto('responsable', 'Responsable')
                ->formato(fn (Objetivo $fila): ?string => $fila->responsable?->name),

            Columna::fecha('fecha_cierre', 'Cerrado')->ordenable()->oculta(),
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
            ])->placeholder('Buscar por código, objetivo o descripción…'),

            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoObjetivo $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoObjetivo::cases(),
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

            Filtro::rangoFechas('fecha_objetivo', 'Fecha objetivo')->enColumna('plazo'),

            /*
             * Por scope, no con la condición escrita otra vez aquí: son los mismos
             * que cuenta `RegistroObjetivos`, y la clave de cada filtro es la
             * clave de su indicador. Con la condición duplicada, el panel dirá 3 y
             * la tabla enseñará 1.
             */
            Filtro::porScope('vivos', 'En curso', 'vivos')->enColumna('estado'),
            Filtro::porScope('vencidos', 'Fuera de plazo', 'vencidos')->enColumna('plazo'),
            Filtro::porScope('sin_indicador', 'Sin indicador', 'sinIndicador')->enColumna('avance'),
            Filtro::porScope('sin_actuacion', 'Sin actuación', 'sinActuacion')->enColumna('actuaciones'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/objetivos/{id}'),
            Accion::eliminar(
                '/objetivos/{id}',
                '¿Eliminar el objetivo? Se pierde su histórico y con él la constancia de a qué se comprometió la '
                .'organización. Si lo que se quiere es dejar de perseguirlo, retíralo con su motivo.',
            )->permiso(Permiso::ObjetivosGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nuevo objetivo', '/objetivos/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ObjetivosGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }
}
