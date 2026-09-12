<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Plazo;
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
 * El plan de acción.
 *
 * La columna que de verdad se mira es «Plazo»: una fecha sola obliga a
 * compararla con hoy fila a fila, y una tabla de tareas se recorre buscando
 * justamente lo que se ha pasado. Mismo criterio que la vigencia de una
 * evidencia.
 *
 * **El rojo se gasta aquí y en ningún otro sitio de esta tabla.** Una tarea
 * vencida es de las pocas cosas del dominio que van mal de verdad; si además los
 * estados llevaran rojo, el plazo dejaría de saltar a la vista.
 *
 * @extends Recurso<Tarea>
 */
final class TareaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'tareas';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Tarea',
            plural: 'Tareas',
            descripcion: 'Lo que hay que hacer, quién lo hace y para cuándo. Cada tarea puede hacer avanzar requisitos de varios marcos a la vez.',
            vacio: 'No hay ninguna tarea. La primera se crea desde aquí o desde la ficha de un requisito pendiente.',
        );
    }

    /** @return Builder<Tarea> */
    public function consulta(): Builder
    {
        return Tarea::query()
            ->with('responsable')
            ->withCount([
                'implantaciones as requisitos_count',
                'subtareas as pasos_count',
                'subtareas as pasos_hechos_count' => fn (Builder $consulta) => $consulta->whereNotNull('hecha_en'),
            ]);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('titulo', 'Título')->ordenable()->anclada(),
            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (Tarea $tarea): ValorEtiquetado => new ValorEtiquetado(
                    $tarea->estado->value,
                    $tarea->estado->etiqueta(),
                    $tarea->estado->tono(),
                )),
            Columna::badge('plazo', 'Plazo')
                ->ordenable('fecha_limite')
                ->ayuda('Una tarea sin fecha límite no es que no corra prisa: es que nadie ha dicho para cuándo.')
                ->formato(function (Tarea $tarea): ValorEtiquetado {
                    $plazo = Plazo::de($tarea);

                    return new ValorEtiquetado($plazo->fecha, $plazo->etiqueta, $plazo->tono);
                }),
            Columna::badge('prioridad', 'Prioridad')
                ->ordenable()
                ->formato(fn (Tarea $tarea): ValorEtiquetado => new ValorEtiquetado(
                    $tarea->prioridad->value,
                    $tarea->prioridad->etiqueta(),
                    $tarea->prioridad->tono(),
                )),
            Columna::texto('responsable', 'Responsable')
                ->ayuda('Una tarea sin responsable no la hace nadie: es la primera columna que se mira cuando algo lleva meses abierto.')
                ->formato(fn (Tarea $tarea): ?string => $tarea->responsable?->name),
            Columna::numero('requisitos', 'Requisitos')
                ->ayuda('Cuántos requisitos hace avanzar, de cualquier marco.')
                ->formato(fn (Tarea $tarea): int => (int) $tarea->getAttribute('requisitos_count')),
            Columna::badge('origen', 'Origen')
                ->ordenable()
                ->oculta()
                ->formato(fn (Tarea $tarea): ValorEtiquetado => new ValorEtiquetado(
                    $tarea->origen->value,
                    $tarea->origen->etiqueta(),
                    'marco',
                )),
            // Oculta por defecto: es un dato de la ficha, no algo que se recorra
            // en una lista. Y a cero no dice nada, así que se pinta vacía.
            Columna::texto('pasos', 'Pasos')
                ->oculta()
                ->ayuda('Los pasos hechos de su lista de comprobación. No son tareas: no cuentan en el panel ni en los avisos.')
                ->formato(fn (Tarea $tarea): ?string => (int) $tarea->getAttribute('pasos_count') === 0
                    ? null
                    : $tarea->getAttribute('pasos_hechos_count').'/'.$tarea->getAttribute('pasos_count')),
            Columna::fecha('fecha_limite', 'Fecha límite')->ordenable()->oculta(),
            Columna::fecha('fecha_cierre', 'Cerrada')->ordenable()->oculta(),
            Columna::numero('coste_estimado', 'Coste estimado')
                ->oculta()
                ->formato(fn (Tarea $tarea): ?string => $tarea->coste_estimado === null
                    ? null
                    : number_format((float) $tarea->coste_estimado, 2, ',', '.').' €'),
            Columna::fechaHora('created_at', 'Alta')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'titulo' => 'titulo',
                'descripcion' => 'titulo',
                'notas' => 'titulo',
            ])->placeholder('Buscar por título, descripción o notas…'),
            Filtro::texto('titulo', 'Título'),
            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoTarea $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoTarea::cases(),
            )),
            Filtro::multiSelect('prioridad', 'Prioridad', array_map(
                static fn (PrioridadTarea $prioridad): Opcion => new Opcion($prioridad->value, $prioridad->etiqueta()),
                PrioridadTarea::cases(),
            )),
            Filtro::multiSelect('origen', 'Origen', array_map(
                static fn (OrigenTarea $origen): Opcion => new Opcion($origen->value, $origen->etiqueta()),
                OrigenTarea::cases(),
            )),
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),
            Filtro::rangoFechas('fecha_limite', 'Fecha límite')->enColumna('plazo'),

            /*
             * Por scope, no con la condición escrita otra vez aquí: es la misma
             * que cuenta el aviso diario y la misma que contarán los indicadores.
             * Con la condición duplicada, el día que cambie una el correo dirá 12
             * y la tabla enseñará 9.
             */
            Filtro::porScope('abiertas', 'Sólo abiertas', 'abiertas'),
            Filtro::porScope('vencidas', 'Vencidas', 'vencidas')->enColumna('plazo'),
            Filtro::porScope('por_vencer', 'Vence en 30 días', 'porVencer')->enColumna('plazo'),
            Filtro::porScope('sin_plazo', 'Sin plazo', 'sinPlazo')->enColumna('plazo'),
            Filtro::porScope('bloqueadas', 'Bloqueadas', 'bloqueadas')->enColumna('estado'),
            Filtro::porScope('sin_responsable', 'Sin responsable', 'sinResponsable')->enColumna('responsable'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/tareas/{id}'),
            Accion::eliminar(
                '/tareas/{id}',
                '¿Eliminar la tarea? Se pierde su histórico. Si lo que se quiere es dejar constancia de que no se hará, descártala con su motivo.',
            )->permiso(Permiso::TareasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva tarea', '/tareas/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::TareasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesMasivas(): array
    {
        return [
            (new Accion('cambiar_estado', 'Cambiar estado', '/tareas/estado', MetodoAccion::Post))
                ->icono('CircleDot')
                ->permiso(Permiso::TareasGestionar->value),
        ];
    }

    /**
     * Lo que más corre y lo que antes vence, primero.
     *
     * Y no por fecha de alta: una lista de tareas ordenada por cuándo se apuntó
     * es una lista en la que lo urgente queda al final en cuanto hay veinte.
     */
    public function ordenPorDefecto(): string
    {
        return 'fecha_limite';
    }

    /**
     * A qué estados puede pasar cada fila, para que la acción masiva no tenga
     * que volver a preguntarle al servidor.
     *
     * @return array<string, mixed>
     */
    public function extrasDeFila(Model $modelo): array
    {
        // La firma tiene que aceptar cualquier modelo para no romper la clase
        // base; aquí sólo llegan tareas.
        if (! $modelo instanceof Tarea) {
            return [];
        }

        return [
            'estado_actual' => $modelo->estado->value,
            'transiciones' => array_map(
                static fn (EstadoTarea $estado): string => $estado->value,
                $modelo->estado->transicionesPermitidas(),
            ),
        ];
    }
}
