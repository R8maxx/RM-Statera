<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Riesgo\Enums\DecisionRiesgo;
use App\Domain\Riesgo\Models\Amenaza;
use App\Domain\Riesgo\Models\Riesgo;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use App\Http\Resources\Riesgo\NivelDeRiesgo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * El registro de riesgos.
 *
 * **Las dos cifras se enseñan juntas, y ése es el punto.** El intrínseco es lo que
 * vale el riesgo sin hacer nada y el residual es lo que queda después de tratarlo:
 * enseñar sólo el segundo esconde de qué se ha partido, y enseñar sólo el primero
 * hace parecer que no se ha hecho nada. Es el mismo criterio con el que la ficha de
 * un activo enseña la valoración propia y la efectiva sin sustituir una por otra.
 *
 * **El rojo se gasta en el nivel y en la reevaluación vencida.** `muy_alto` es, por
 * construcción, estar por encima del umbral crítico que puso la propia organización
 * —lo reparte `CalculoRiesgo` desde los umbrales, no por quintiles—, así que no hay
 * forma de que el badge diga una cosa y el indicador del panel otra. La decisión
 * **no** lleva rojo: aceptar un riesgo alto es una decisión de la dirección, no un
 * incumplimiento.
 *
 * @extends Recurso<Riesgo>
 */
final class RiesgoRecurso extends Recurso
{
    public function clave(): string
    {
        return 'riesgos';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Riesgo',
            plural: 'Riesgos',
            descripcion: 'Qué puede pasar, sobre qué activos, cuánto costaría y qué se ha decidido hacer. El impacto se deduce de la valoración efectiva de los activos, no de la escrita en cada ficha.',
            vacio: 'No hay ningún riesgo registrado. El primero se crea desde aquí, eligiendo una amenaza del catálogo de MAGERIT y los activos sobre los que pesa.',
        );
    }

    /**
     * @return Builder<Riesgo>
     */
    public function consulta(): Builder
    {
        return Riesgo::query()
            ->select('riesgos.*')
            /*
             * **Un `join` aquí es seguro, a diferencia del de `DocumentoRecurso`.**
             * Allí hubo que ir por subconsulta porque un documento tiene muchas
             * versiones y el join multiplicaba las filas; aquí el índice único
             * parcial `riesgo_valoraciones_vigente_unica` garantiza UNA vigente por
             * riesgo, así que no multiplica nada. Está escrito para que nadie lo
             * «arregle» a subconsulta creyendo que repite aquel fallo.
             *
             * `leftJoin` y no `join`: un riesgo registrado y todavía sin valorar
             * tiene que seguir apareciendo en la lista — es justo el que hay que
             * ver.
             */
            ->leftJoin('riesgo_valoraciones as vigente', function (JoinClause $union): void {
                $union->on('vigente.riesgo_id', '=', 'riesgos.id')->where('vigente.vigente', true);
            })
            ->addSelect([
                'vigente.riesgo_intrinseco as intrinseco',
                'vigente.riesgo_residual as residual',
                'vigente.decision as decision',
                'vigente.aceptada_en as aceptada_en',
            ])
            /*
             * El join sirve para ordenar y filtrar; el modelo cargado sirve para
             * pintar, porque el nivel se lee con la escala CONGELADA de cada
             * valoración y eso son datos que hay que deserializar. Una consulta más
             * por página, y a cambio el badge de la tabla dice lo mismo que el de la
             * ficha.
             */
            ->with(['amenaza', 'propietario', 'valoracionVigente'])
            ->withCount(['activos', 'salvaguardas']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada(),
            Columna::texto('titulo', 'Título')->ordenable(),
            Columna::texto('amenaza', 'Amenaza')
                ->ayuda('Del catálogo de MAGERIT, o escrita a mano cuando no está en él.')
                ->formato(fn (Riesgo $riesgo): string => $riesgo->nombreAmenaza()),

            Columna::badge('intrinseco', 'Intrínseco')
                ->ordenable('vigente.riesgo_intrinseco')
                ->ayuda('Lo que vale el riesgo antes de tener en cuenta ninguna salvaguarda.')
                ->formato(fn (Riesgo $riesgo): ?ValorEtiquetado => $this->nivel($riesgo, residual: false)),

            Columna::badge('residual', 'Residual')
                ->ordenable('vigente.riesgo_residual')
                ->ayuda('Lo que queda después de tratarlo. Lo declara quien valora y lo aprueba el propietario del riesgo: la herramienta no lo calcula.')
                ->formato(fn (Riesgo $riesgo): ?ValorEtiquetado => $this->nivel($riesgo, residual: true)),

            Columna::badge('decision', 'Decisión')
                ->ordenable('vigente.decision')
                ->formato(fn (Riesgo $riesgo): ?ValorEtiquetado => $riesgo->valoracionVigente === null
                    ? null
                    : new ValorEtiquetado(
                        $riesgo->valoracionVigente->decision->value,
                        $riesgo->valoracionVigente->decision->etiqueta(),
                        $riesgo->valoracionVigente->decision->tono(),
                        $riesgo->valoracionVigente->decision->icono(),
                    )),

            Columna::texto('propietario', 'Propietario')
                ->ayuda('Quien responde de la decisión, que no es quien hace el trabajo. ISO 27001 exige que sea él quien acepte el riesgo.')
                ->formato(fn (Riesgo $riesgo): ?string => $riesgo->propietario?->name),

            Columna::numero('activos', 'Activos')
                ->ayuda('Sobre cuántos activos pesa. Un mismo riesgo puede pesar sobre treinta.')
                ->formato(fn (Riesgo $riesgo): int => (int) $riesgo->getAttribute('activos_count')),

            Columna::numero('salvaguardas', 'Salvaguardas')
                ->ayuda('Cuántos controles implantados se apoyan contra él.')
                ->formato(fn (Riesgo $riesgo): int => (int) $riesgo->getAttribute('salvaguardas_count')),

            Columna::badge('revision', 'Reevaluación')
                ->ordenable('fecha_revision')
                ->ayuda('Un riesgo sin fecha de reevaluación no es que no corra prisa: es que nadie ha dicho cuándo toca volver a mirarlo.')
                ->formato(fn (Riesgo $riesgo): ValorEtiquetado => $this->revision($riesgo)),

            Columna::fecha('aceptada_en', 'Aceptado')->ordenable('vigente.aceptada_en')->oculta(),
            Columna::texto('vulnerabilidad', 'Vulnerabilidad')->oculta(),
            Columna::fechaHora('created_at', 'Alta')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'titulo' => 'titulo',
                'codigo' => 'codigo',
                'vulnerabilidad' => 'vulnerabilidad',
                'notas' => 'titulo',
            ])->placeholder('Buscar por código, título, vulnerabilidad o notas…'),

            Filtro::texto('titulo', 'Título'),

            /*
             * Por relación y no por join: un riesgo pesa sobre muchos activos, y
             * unir la pivote multiplicaría las filas —el riesgo sobre treinta
             * portátiles saldría treinta veces y la paginación contaría mal—. Es el
             * mismo motivo por el que se filtra por alcance en el inventario.
             */
            Filtro::porRelacion('activo', 'Activo', 'activos', 'activos.id', fn (): array => Activo::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Activo $activo): Opcion => new Opcion((string) $activo->id, $activo->nombre))
                ->all()),

            Filtro::select('amenaza_id', 'Amenaza', fn (): array => Amenaza::query()
                ->vigentes()
                ->orderBy('grupo')
                ->orderBy('codigo')
                ->get()
                ->map(fn (Amenaza $amenaza): Opcion => new Opcion((string) $amenaza->id, $amenaza->etiqueta()))
                ->all())->enColumna('amenaza'),

            Filtro::multiSelect('decision', 'Decisión', array_map(
                static fn (DecisionRiesgo $decision): Opcion => new Opcion($decision->value, $decision->etiqueta()),
                DecisionRiesgo::cases(),
            ))->campo('vigente.decision'),

            Filtro::select('propietario_id', 'Propietario', fn (): array => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('propietario'),

            Filtro::rangoFechas('fecha_revision', 'Reevaluación')->enColumna('revision'),

            /*
             * Por scope, nunca con la condición escrita otra vez: son los mismos
             * que cuenta `RegistroRiesgos`. Con la condición duplicada, el día que
             * cambie una el panel dirá 12 y la tabla enseñará 9, y a partir de ahí
             * nadie se fía del panel.
             */
            Filtro::porScope('sobre_umbral', 'Por encima del umbral', 'sobreUmbral')->enColumna('residual'),
            Filtro::porScope('sin_valorar', 'Sin valorar', 'sinValorar')->enColumna('intrinseco'),
            Filtro::porScope('sin_aceptar', 'Pendientes de firma', 'sinAceptar')->enColumna('decision'),
            Filtro::porScope('residual_sin_respaldo', 'Residual sin respaldo', 'residualSinRespaldo')->enColumna('residual'),
            Filtro::porScope('revision_vencida', 'Reevaluación vencida', 'revisionVencida')->enColumna('revision'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/riesgos/{id}'),
            Accion::eliminar(
                '/riesgos/{id}',
                '¿Eliminar el riesgo? Se pierde su histórico de valoraciones, incluidas las que alguien firmó.',
            )->permiso(Permiso::RiesgosGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nuevo riesgo', '/riesgos/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::RiesgosGestionar->value),
            (new Accion('metodologia', 'Metodología', '/riesgos/metodologia', MetodoAccion::Get))
                ->secundaria()
                ->icono('Scale')
                ->permiso(Permiso::RiesgosVer->value),
        ];
    }

    /**
     * Lo más expuesto primero.
     *
     * Y no por código ni por fecha de alta: un registro de riesgos ordenado por
     * cuándo se apuntó deja lo que hay que tratar al final en cuanto hay veinte.
     */
    public function ordenPorDefecto(): string
    {
        return '-vigente.riesgo_intrinseco';
    }

    /**
     * El nivel, leído con la escala CONGELADA de esa valoración.
     *
     * La regla vive en `NivelDeRiesgo` porque la ficha de un activo pinta el
     * mismo badge, y el mapa nivel → color no se copia.
     */
    private function nivel(Riesgo $riesgo, bool $residual): ?ValorEtiquetado
    {
        return NivelDeRiesgo::badge($riesgo->valoracionVigente, $residual);
    }

    /**
     * Cuándo toca volver a mirarlo.
     *
     * Vencida en rojo, igual que el plazo de una tarea y por lo mismo: es de las
     * pocas cosas del dominio que van mal de verdad. Y «sin fecha» se distingue de
     * «en plazo», porque no es que no corra prisa: es que nadie lo ha fechado.
     */
    private function revision(Riesgo $riesgo): ValorEtiquetado
    {
        if ($riesgo->fecha_revision === null) {
            return new ValorEtiquetado(null, 'Sin fecha', 'no_iniciado');
        }

        $fecha = $riesgo->fecha_revision->toDateString();

        return $riesgo->revisionVencida()
            ? new ValorEtiquetado($fecha, 'Vencida', 'caducada')
            : new ValorEtiquetado($fecha, 'En plazo', 'implantado');
    }
}
