<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Contexto\Enums\MateriaCuestion;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Organizacion\ContextoOrganizacion;
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
 * Las cuestiones internas y externas: la cláusula 4.1 en forma de tabla.
 *
 * La matriz de `/contexto` es la cara del módulo y esto es la trastienda: a los
 * treinta o cuarenta apuntes hacen falta filtros, orden y una columna que diga qué
 * se ha atado y qué no. Es el mismo reparto que tienen `/tareas` y
 * `/tareas/tablero`, sólo que aquí la vista de lectura es la que lleva la ruta
 * corta.
 *
 * **La tabla enseña también las retiradas**, a diferencia de la matriz. Es lo
 * mismo que hace el inventario con los activos dados de baja: sacarlas de aquí
 * dejaría sin sitio la pregunta «¿qué había en el DAFO del año pasado que ya no
 * está?», que es literalmente lo que pide la cláusula 9.3.
 *
 * Los recuentos de riesgos y tareas llegan **por subconsulta y no por join**, por
 * el mismo motivo de siempre: un join contra las pivotes multiplicaría las filas
 * —una amenaza con tres riesgos saldría tres veces— y la paginación contaría mal.
 * Van en SQL crudo y no pasan por el scope de Eloquent: ahí quien filtra es RLS,
 * que es justo el caso para el que existe la tercera capa.
 *
 * @extends Recurso<CuestionContexto>
 */
final class CuestionRecurso extends Recurso
{
    public function clave(): string
    {
        return 'cuestiones-contexto';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Cuestión',
            plural: 'Cuestiones del contexto',
            descripcion: 'Lo que la organización tiene a favor y en contra, dentro y fuera. Es la cláusula 4.1 de ISO 27001, y de aquí salen los riesgos.',
            vacio: 'No hay ninguna cuestión registrada. El DAFO se empieza apuntando la primera.',
        );
    }

    /** @return Builder<CuestionContexto> */
    public function consulta(): Builder
    {
        return CuestionContexto::query()
            ->select('cuestiones_contexto.*')
            ->selectRaw('(select count(*) from cuestion_riesgo cr where cr.cuestion_contexto_id = cuestiones_contexto.id) as riesgos_total')
            ->selectRaw(<<<'SQL'
                (select count(*) from cuestion_tarea ct
                    join tareas t on t.id = ct.tarea_id
                    where ct.cuestion_contexto_id = cuestiones_contexto.id
                      and t.estado not in ('hecha', 'descartada')) as tareas_abiertas
            SQL)
            ->with('responsable');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('7rem'),

            Columna::texto('titulo', 'Cuestión')->ordenable(),

            Columna::badge('tipo', 'Tipo')
                ->ordenable()
                ->ancho('9rem')
                ->formato(fn (CuestionContexto $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->tipo->value,
                    $fila->tipo->etiqueta(),
                    $fila->tipo->tono(),
                    $fila->tipo->icono(),
                )),

            /*
             * Derivada del tipo y sin ordenación: no hay columna que ordenar. Se
             * pinta porque es la mitad del DAFO que el color no dice —el tono
             * codifica el signo— y porque filtrar «todo lo de fuera» es una
             * pregunta legítima que si no habría que hacer marcando dos tipos.
             */
            Columna::texto('ambito', 'Ámbito')
                ->ancho('7rem')
                ->ayuda('De la organización o de su entorno. Se deriva del tipo.')
                ->formato(fn (CuestionContexto $fila): string => $fila->ambito()->etiqueta()),

            Columna::texto('materia', 'Materia')
                ->ordenable()
                ->formato(fn (CuestionContexto $fila): string => $fila->materia->etiqueta()),

            Columna::texto('riesgos', 'Riesgos')
                ->alinear(Alineacion::Derecha)
                ->ancho('6rem')
                ->ayuda('Riesgos que declaran venir de esta cuestión.')
                ->formato(fn (CuestionContexto $fila): string => (string) (int) $fila->getAttribute('riesgos_total')),

            Columna::texto('tareas', 'En marcha')
                ->alinear(Alineacion::Derecha)
                ->ancho('7rem')
                ->ayuda('Tareas abiertas vinculadas a esta cuestión.')
                ->formato(fn (CuestionContexto $fila): string => (string) (int) $fila->getAttribute('tareas_abiertas')),

            /*
             * «Situación» y no «Estado»: una cuestión no tiene máquina de estados,
             * tiene un alta y puede que una baja. Llamarla estado invitaría a
             * buscar transiciones que no existen.
             */
            Columna::badge('situacion', 'Situación')
                ->ancho('8rem')
                ->formato(fn (CuestionContexto $fila): ValorEtiquetado => $fila->estaVigente()
                    ? new ValorEtiquetado('vigente', 'Vigente', 'implantado', 'CircleCheck')
                    : new ValorEtiquetado('retirada', 'Retirada', 'no_aplica', 'Archive')),

            Columna::texto('responsable', 'Responsable')
                ->oculta()
                ->formato(fn (CuestionContexto $fila): ?string => $fila->responsable?->name),

            Columna::booleano('es_climatica', 'Clima')
                ->oculta()
                ->ayuda('Cuestión relacionada con el cambio climático (enmienda 1:2024).'),
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
            ])->placeholder('Buscar por código, título o descripción…'),

            Filtro::multiSelect('tipo', 'Tipo', array_map(
                static fn (TipoCuestion $tipo): Opcion => new Opcion($tipo->value, $tipo->etiqueta()),
                TipoCuestion::enOrdenDeMatriz(),
            )),

            Filtro::multiSelect('materia', 'Materia', array_map(
                static fn (MateriaCuestion $materia): Opcion => new Opcion($materia->value, $materia->etiqueta()),
                MateriaCuestion::cases(),
            )),

            /*
             * Acotado a la organización a mano: `User` no lleva
             * `PerteneceAOrganizacion`, así que aquí no hay scope global ni RLS que
             * tapen el cruce y sin este `where` el desplegable lista a los usuarios
             * de todos los clientes.
             */
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),

            /*
             * Por scope, nunca con la condición escrita otra vez: son las mismas que
             * cuenta `RegistroContexto`, y la clave de cada filtro es la clave de su
             * indicador. Con la condición duplicada, el panel dirá 12 y la tabla
             * enseñará 9.
             */
            Filtro::porScope('vigentes', 'Sólo vigentes', 'vigentes')->enColumna('situacion'),
            Filtro::porScope('sin_riesgo', 'Sin riesgo vinculado', 'sinRiesgo')->enColumna('riesgos'),
            Filtro::porScope('sin_trabajo', 'Sin nada en marcha', 'sinTrabajo')->enColumna('tareas'),
            Filtro::porScope('climaticas', 'Del cambio climático', 'climaticas')->enColumna('es_climatica'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/contexto/cuestiones/{id}'),
            Accion::eliminar(
                '/contexto/cuestiones/{id}',
                '¿Eliminar la cuestión? Se pierde su rastro y los riesgos y tareas que colgaban de ella se quedan sin origen. '
                .'Si lo que ha pasado es que dejó de ser pertinente, retírala con su motivo: así el análisis siguiente puede explicar por qué ya no está.',
            )->permiso(Permiso::ContextoGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva cuestión', '/contexto/cuestiones/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ContextoGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }
}
