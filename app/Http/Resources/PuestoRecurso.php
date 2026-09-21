<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Persona\Models\Puesto;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\Alineacion;
use Illuminate\Database\Eloquent\Builder;

/**
 * El catálogo de puestos: § 4.8 y la caracterización de `mp.per.1`.
 *
 * Los ocupantes y el nombre del superior llegan **por subconsulta y no por join**,
 * por lo mismo que en personas: un join contra `asignaciones_puesto` sacaría el
 * puesto una vez por cada persona que lo ha ocupado y la paginación contaría mal.
 *
 * **Este recurso no gasta rojo.** Un puesto sin caracterizar es la distancia que
 * queda —`mp.per.1` está en `no_aplica` en categoría básica— y una vacante es una
 * decisión, no un incumplimiento. Pintarlos de alarma enseña a no registrarlos,
 * que es el quinto principio del producto.
 *
 * @extends Recurso<Puesto>
 */
final class PuestoRecurso extends Recurso
{
    public function clave(): string
    {
        return 'puestos';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Puesto',
            plural: 'Puestos',
            descripcion: 'Qué puestos hay, de quién depende cada uno y qué competencia pide. Es la caracterización que nombra mp.per.1 y de donde sale el organigrama.',
            vacio: 'No hay puestos registrados. Sin ellos la plantilla no tiene estructura y el organigrama está vacío.',
        );
    }

    /** @return Builder<Puesto> */
    public function consulta(): Builder
    {
        return Puesto::query()
            ->select('puestos.*')
            ->selectRaw(<<<'SQL'
                (select count(*) from asignaciones_puesto ap
                    where ap.puesto_id = puestos.id and ap.hasta is null) as ocupantes
            SQL)
            ->selectRaw(<<<'SQL'
                (select jefe.titulo from puestos jefe where jefe.id = puestos.reporta_a_id) as reporta_a
            SQL);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('7rem'),

            Columna::texto('titulo', 'Puesto')->ordenable(),

            // Alias de subconsulta: ordenable sí —PostgreSQL resuelve el `ORDER
            // BY` contra la columna de salida—, buscable no.
            Columna::texto('reporta_a', 'Reporta a')->ordenable(),

            Columna::texto('ocupantes', 'Ocupantes')
                ->alinear(Alineacion::Derecha)
                ->ancho('7rem')
                ->ayuda('Personas que lo ocupan hoy. Un puesto puede tener varias.')
                ->formato(fn (Puesto $fila): string => (string) (int) $fila->getAttribute('ocupantes')),

            Columna::badge('caracterizado', 'Caracterizado')
                ->ancho('10rem')
                ->ayuda('Si dice qué competencia pide el puesto, que es lo que nombra mp.per.1.')
                ->formato(fn (Puesto $fila): ValorEtiquetado => $fila->estaCaracterizado()
                    ? new ValorEtiquetado('si', 'Sí', 'implantado', 'CheckCircle2')
                    : new ValorEtiquetado('no', 'Sin caracterizar', 'no_iniciado', 'CircleDashed')),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'titulo' => 'titulo',
            ])->placeholder('Buscar por código o puesto…'),

            /*
             * Por scope, no con la condición escrita otra vez aquí: es el mismo
             * que cuenta el resumen del módulo. Con la condición duplicada, el
             * día que cambie una el panel dirá 12 y la tabla enseñará 9.
             */
            Filtro::porScope('sin_caracterizar', 'Sin caracterizar', 'sinCaracterizar')
                ->enColumna('caracterizado'),

            Filtro::porScope('vacantes', 'Vacantes', 'vacantes')
                ->enColumna('ocupantes'),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/puestos/{id}'),
            Accion::editar('/puestos/{id}/editar')->permiso(Permiso::PersonasGestionar->value),
            Accion::eliminar(
                '/puestos/{id}',
                '¿Eliminar este puesto? Si alguien lo ocupa o lo ocupó, la base lo impide: primero hay que reasignar a esas personas.',
            )->permiso(Permiso::PersonasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('organigrama', 'Organigrama', '/puestos/organigrama'))
                ->icono('Network')
                ->secundaria(),

            (new Accion('crear', 'Nuevo puesto', '/puestos/crear'))
                ->icono('Plus')
                ->permiso(Permiso::PersonasGestionar->value),
        ];
    }
}
