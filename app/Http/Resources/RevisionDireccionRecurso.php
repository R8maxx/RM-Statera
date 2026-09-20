<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
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
 * Las revisiones por la dirección: § 4.15 y la cláusula 9.3.
 *
 * **Es la tabla más corta del producto**, y tiene que serlo: una organización
 * celebra una revisión al año, así que este registro tendrá cinco filas cuando
 * lleve un lustro certificada. Lo que importa de cada una no es filtrarla, es
 * poder abrirla y leer el acta.
 *
 * Por eso no hay búsqueda por texto: con cinco filas, un cuadro de búsqueda es
 * ruido. Lo que sí hay es el filtro de estado, que contesta la única pregunta
 * recurrente —«¿hay alguna sin firmar?»—.
 *
 * @extends Recurso<RevisionDireccion>
 */
final class RevisionDireccionRecurso extends Recurso
{
    public function clave(): string
    {
        return 'revision-direccion';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Revisión por la dirección',
            plural: 'Revisiones por la dirección',
            descripcion: 'La cláusula 9.3 pide que la dirección revise el SGSI con una cadencia planificada, y cierra la lista de lo que tiene que mirar: siete entradas obligatorias.',
            vacio: 'No hay revisiones registradas. La cláusula 9.3 pide al menos una, y es de lo primero que un auditor pide ver.',
        );
    }

    /** @return Builder<RevisionDireccion> */
    public function consulta(): Builder
    {
        $decisiones = fn (string $condicion): string => <<<SQL
            (select count(*) from revision_tarea rt
                join tareas t on t.id = rt.tarea_id
                where rt.revision_direccion_id = revisiones_direccion.id {$condicion})
        SQL;

        return RevisionDireccion::query()
            ->select('revisiones_direccion.*')
            ->selectRaw($decisiones('').' as decisiones_total')
            ->selectRaw($decisiones("and t.estado not in ('hecha', 'descartada')").' as decisiones_abiertas')
            ->with('aprobadaPor');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('9rem'),

            Columna::fecha('fecha', 'Celebrada')->ordenable(),

            Columna::texto('periodo', 'Periodo revisado')
                ->ayuda('De qué habla el acta. No se deduce de la fecha: una revisión del ejercicio pasado suele celebrarse en el siguiente.')
                ->formato(fn (RevisionDireccion $fila): string => $fila->periodo()),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (RevisionDireccion $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            /*
             * Con su denominador, como toda cifra del producto. Estas decisiones
             * son además la entrada a) de la revisión siguiente, así que «2 de 5
             * abiertas» es literalmente lo que se leerá el año que viene.
             */
            Columna::texto('decisiones', 'Decisiones')
                ->alinear(Alineacion::Derecha)
                ->ancho('9rem')
                ->ayuda('Decisiones abiertas sobre el total. Son la entrada «acciones de revisiones previas» del acta siguiente.')
                ->formato(fn (RevisionDireccion $fila): string => sprintf(
                    '%d de %d',
                    (int) $fila->getAttribute('decisiones_abiertas'),
                    (int) $fila->getAttribute('decisiones_total'),
                )),

            Columna::texto('aprobada_por', 'Firmada por')
                ->formato(fn (RevisionDireccion $fila): ?string => $fila->aprobadaPor?->name),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoRevision $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoRevision::cases(),
            )),

            // Los dos scopes del módulo, con la clave del filtro igual que la del
            // scope: es lo que impide que una cifra y su lista discrepen.
            Filtro::porScope('abiertas', 'Sin firmar', 'abiertas')->enColumna('estado'),
            Filtro::porScope('aprobadas', 'Firmadas', 'aprobadas')->enColumna('estado'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/revision-direccion/{id}'),
            Accion::eliminar(
                '/revision-direccion/{id}',
                '¿Eliminar la revisión? Si el acta está aprobada, se pierde la constancia de que la dirección '
                .'revisó el SGSI ese día, que es justo lo que la cláusula 9.3 pide poder enseñar.',
            )->permiso(Permiso::RevisionDireccionGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Convocar revisión', '/revision-direccion/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::RevisionDireccionGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha';
    }
}
