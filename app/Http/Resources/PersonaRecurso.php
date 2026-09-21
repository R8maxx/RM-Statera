<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Persona\Models\Persona;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\Alineacion;
use App\Http\Resources\Enums\MetodoAccion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * El registro de personas: § 4.8, cláusula 5.3 y `mp.per.*`.
 *
 * Las cifras de roles, formación y acuerdos llegan **por subconsulta y no por
 * join**, por el mismo motivo que en no conformidades, objetivos y mejoras: un
 * join contra tres tablas hijas multiplicaría las filas —una persona con dos roles
 * y tres formaciones saldría seis veces— y la paginación contaría mal. Van en SQL
 * crudo y no pasan por el scope de Eloquent: ahí quien filtra es RLS, que es justo
 * el caso para el que existe la tercera capa.
 *
 * **El único rojo de la tabla es la salida sin cerrar.** No estar formado es la
 * distancia que queda; irse dejando accesos vivos es el hallazgo.
 *
 * @extends Recurso<Persona>
 */
final class PersonaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'personas';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Persona',
            plural: 'Personas',
            descripcion: 'La plantilla y lo que la norma pide de ella: deberes firmados, concienciación, formación y los cinco roles del ENS con su incompatibilidad.',
            vacio: 'No hay personas registradas. Sin ellas no hay a quién designar los roles del ENS ni de quién registrar la formación.',
        );
    }

    /** @return Builder<Persona> */
    public function consulta(): Builder
    {
        return Persona::query()
            ->select('personas.*')
            ->selectRaw(<<<'SQL'
                (select count(*) from designaciones_rol dr
                    where dr.persona_id = personas.id and dr.hasta is null) as roles_vigentes
            SQL)
            ->selectRaw(<<<'SQL'
                (select max(af.fecha) from asistencias a
                    join acciones_formativas af on af.id = a.accion_formativa_id
                    where a.persona_id = personas.id and a.asistio) as ultima_formacion
            SQL)
            ->selectRaw(<<<'SQL'
                (select count(*) from acuerdos_confidencialidad ac
                    where ac.persona_id = personas.id
                      and (ac.vigente_hasta is null or ac.vigente_hasta >= current_date)) as acuerdos_vigentes
            SQL)
            ->selectRaw(<<<'SQL'
                (select count(*) from pasos_persona pp
                    where pp.persona_id = personas.id and pp.tipo = 'baja' and pp.hecho_en is null) as baja_pendiente
            SQL)
            /*
             * El puesto ya no es una columna de `personas`: es la asignación
             * vigente. Por subconsulta y no por join, como las cuatro de arriba —
             * con el join, una persona con tres asignaciones históricas saldría
             * tres veces y la paginación contaría mal.
             */
            ->selectRaw(<<<'SQL'
                (select pu.titulo from asignaciones_puesto ap
                    join puestos pu on pu.id = ap.puesto_id
                    where ap.persona_id = personas.id and ap.hasta is null
                    limit 1) as puesto
            SQL)
            ->with('usuario');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('7rem'),

            Columna::texto('nombre', 'Nombre')->ordenable(),

            /*
             * Ordenable sobre el alias de la subconsulta —PostgreSQL resuelve el
             * `ORDER BY` contra la columna de salida—, pero **no buscable**: un
             * alias del `SELECT` no es visible en el `WHERE`, y meterlo en la
             * búsqueda daría «column "puesto" does not exist». Para buscar por
             * puesto está `/puestos`.
             */
            Columna::texto('puesto', 'Puesto')->ordenable(),

            Columna::badge('estado', 'Estado')
                ->ordenable('fecha_baja')
                ->ancho('9rem')
                ->formato(function (Persona $fila): ValorEtiquetado {
                    if ($fila->estaActiva()) {
                        return new ValorEtiquetado('activa', 'En plantilla', 'implantado', 'UserCheck');
                    }

                    // El rojo de la tabla, y el único: irse dejando la checklist
                    // de salida a medias es un acceso que puede seguir vivo.
                    return (int) $fila->getAttribute('baja_pendiente') > 0
                        ? new ValorEtiquetado('baja_sin_cerrar', 'Salida sin cerrar', 'caducada', 'TriangleAlert')
                        : new ValorEtiquetado('baja', 'Dada de baja', 'no_aplica', 'Archive');
                }),

            Columna::texto('roles', 'Roles ENS')
                ->alinear(Alineacion::Derecha)
                ->ancho('7rem')
                ->ayuda('Designaciones vigentes en cualquier sistema.')
                ->formato(fn (Persona $fila): string => (string) (int) $fila->getAttribute('roles_vigentes')),

            /*
             * «Hace N meses» y no la fecha a secas: lo que se mira aquí no es
             * cuándo fue la sesión, es si sigue valiendo. Mismo criterio que la
             * columna «Plazo» de tareas, que traduce la fecha a una distancia.
             */
            Columna::badge('formacion', 'Formación')
                ->ancho('10rem')
                ->ayuda('Última asistencia registrada. Se considera al día durante doce meses.')
                ->formato(function (Persona $fila): ValorEtiquetado {
                    $ultima = $fila->getAttribute('ultima_formacion');

                    if ($ultima === null) {
                        return new ValorEtiquetado('', 'Sin registrar', 'no_iniciado', null);
                    }

                    $fecha = Carbon::parse((string) $ultima);
                    $alDia = $fecha->greaterThanOrEqualTo(
                        Carbon::today()->subMonths(Persona::MESES_DE_VIGENCIA_FORMATIVA),
                    );

                    return new ValorEtiquetado(
                        $fecha->toDateString(),
                        $fecha->format('d/m/Y'),
                        $alDia ? 'implantado' : 'en_progreso',
                        null,
                    );
                }),

            Columna::badge('acuerdo', 'Acuerdo')
                ->ancho('8rem')
                ->ayuda('Acuerdo de confidencialidad en vigor (mp.per.2).')
                ->formato(fn (Persona $fila): ValorEtiquetado => (int) $fila->getAttribute('acuerdos_vigentes') > 0
                    ? new ValorEtiquetado('si', 'Firmado', 'implantado', null)
                    : new ValorEtiquetado('no', 'Sin firmar', 'no_iniciado', null)),

            Columna::texto('cuenta', 'Cuenta')
                ->oculta()
                ->ayuda('La cuenta de Statera de esta persona, si tiene. La mayoría no tiene.')
                ->formato(fn (Persona $fila): ?string => $fila->usuario?->email),

            /*
             * **Oculta por defecto, y a propósito.** Es un dato personal en una
             * herramienta que está en el alcance de su propio SGSI: quien lo
             * necesita lo enseña, y no se pinta en una tabla que alguien puede
             * tener abierta en una pantalla compartida. Por lo mismo no entra en
             * la búsqueda libre — ahí está el resto de la ficha.
             */
            Columna::texto('nif', 'NIF')
                ->oculta()
                ->ancho('9rem')
                ->ayuda('Documento de identidad. Dato personal: va oculto salvo que se pida.'),

            Columna::fecha('fecha_alta', 'Alta')->ordenable()->oculta(),
            Columna::fecha('fecha_baja', 'Baja')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'nombre' => 'nombre',
                'email' => 'nombre',
            ])->placeholder('Buscar por código, nombre o correo…'),

            /*
             * Por scope, no con la condición escrita otra vez aquí: son los mismos
             * que cuenta `RegistroPersonas`, y la clave de cada filtro es la clave
             * de su indicador. Con la condición duplicada, el panel dirá 12 y la
             * tabla enseñará 9.
             */
            Filtro::porScope('activas', 'En plantilla', 'activas')->enColumna('estado'),
            Filtro::porScope('baja_sin_cerrar', 'Salidas sin cerrar', 'conBajaSinCerrar')->enColumna('estado'),
            Filtro::porScope('sin_formacion', 'Sin formación reciente', 'sinFormacionReciente')->enColumna('formacion'),
            Filtro::porScope('sin_acuerdo', 'Sin acuerdo vigente', 'sinAcuerdoVigente')->enColumna('acuerdo'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/personas/{id}'),
            Accion::eliminar(
                '/personas/{id}',
                '¿Eliminar a esta persona? Se pierde su formación, sus acuerdos y el histórico de sus '
                .'designaciones. Si lo que ha pasado es que se ha ido, dale de baja: eso sí deja constancia.',
            )->permiso(Permiso::PersonasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva persona', '/personas/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::PersonasGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'nombre';
    }
}
