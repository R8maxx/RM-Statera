<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Incidente\Enums\ClasificacionIncidente;
use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Enums\PeligrosidadIncidente;
use App\Domain\Incidente\Models\Incidente;
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
 * El registro de incidentes: § 4.10 y `op.exp.7`.
 *
 * **La única celda en rojo de esta tabla es la de la notificación a la AEPD**, y
 * sólo cuando las 72 h del artículo 33.1 del RGPD han pasado sin notificar. Ni el
 * estado ni la peligrosidad lo gastan: un incidente crítico abierto no va mal, va
 * siendo atendido, y pintarlo de alarma dejaría el registro entero en rojo por
 * estar haciendo su trabajo. Mismo reparto que en tareas, donde el rojo es del
 * plazo y nunca del estado.
 *
 * Las cifras de activos y del tratamiento llegan **por subconsulta y no por
 * join**, por el mismo motivo que en el resto del producto: un join contra la
 * pivote multiplicaría las filas y la paginación contaría mal.
 *
 * @extends Recurso<Incidente>
 */
final class IncidenteRecurso extends Recurso
{
    public function clave(): string
    {
        return 'incidentes';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Incidente',
            plural: 'Incidentes',
            descripcion: 'Qué ha pasado, a qué afectó, qué se hizo y qué se aprendió. Es op.exp.7, exigible desde categoría básica.',
            vacio: 'No hay incidentes registrados. Un registro vacío no es lo mismo que no haber tenido ninguno: op.exp.7 pide que los que haya queden anotados.',
        );
    }

    /** @return Builder<Incidente> */
    public function consulta(): Builder
    {
        return Incidente::query()
            ->select('incidentes.*')
            ->selectRaw(<<<'SQL'
                (select count(*) from incidente_activo ia
                    where ia.incidente_id = incidentes.id) as activos_afectados
            SQL)
            ->selectRaw(<<<'SQL'
                (select nc.codigo from no_conformidades nc
                    where nc.incidente_id = incidentes.id limit 1) as no_conformidad
            SQL)
            ->with(['responsable', 'sistema']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('8rem'),

            Columna::texto('titulo', 'Incidente')->ordenable(),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->ancho('9rem')
                ->formato(fn (Incidente $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            Columna::badge('peligrosidad', 'Peligrosidad')
                ->ordenable()
                ->ancho('9rem')
                ->ayuda('La declara quien registra el incidente; no se deduce de las dimensiones afectadas.')
                ->formato(fn (Incidente $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->peligrosidad->value,
                    $fila->peligrosidad->etiqueta(),
                    $fila->peligrosidad->tono(),
                    $fila->peligrosidad->icono(),
                )),

            /*
             * **El único rojo de la tabla.** Y con tres estados y no dos: «no
             * procede» no es «notificado», y «en plazo» no es «fuera de plazo».
             * Colapsarlos pondría en rojo a quien lo está haciendo bien.
             */
            Columna::badge('aepd', 'AEPD')
                ->ancho('12rem')
                ->ayuda('72 h desde la detección, artículo 33.1 del RGPD. Sólo corre si hubo datos personales afectados.')
                ->formato(function (Incidente $fila): ValorEtiquetado {
                    $plazo = $fila->plazoAepd();

                    return new ValorEtiquetado(
                        $plazo->aplica ? 'aplica' : 'no_aplica',
                        $plazo->etiqueta,
                        $plazo->tono,
                        null,
                    );
                }),

            Columna::badge('clasificacion', 'Clasificación')
                ->ordenable()
                ->oculta()
                ->formato(fn (Incidente $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->clasificacion->value,
                    $fila->clasificacion->etiqueta(),
                    $fila->clasificacion->tono(),
                    $fila->clasificacion->icono(),
                )),

            /*
             * Las cinco dimensiones en una celda y en sus iniciales: cinco
             * columnas booleanas en la tabla serían cinco columnas de casi
             * siempre «no», y lo que se mira aquí es el conjunto.
             */
            Columna::texto('dimensiones', 'Dimensiones')
                ->ancho('9rem')
                ->ayuda('Las dimensiones del Anexo I afectadas: C, I, D, A y T.')
                ->formato(function (Incidente $fila): string {
                    $iniciales = array_map(
                        static fn (string $dimension): string => mb_substr($dimension, 0, 1),
                        $fila->dimensionesAfectadas(),
                    );

                    return $iniciales === [] ? '—' : implode(' · ', $iniciales);
                }),

            Columna::fecha('fecha_deteccion', 'Detectado')->ordenable(),

            Columna::texto('responsable', 'Responsable')
                ->formato(fn (Incidente $fila): ?string => $fila->responsable?->name),

            Columna::texto('sistema', 'Sistema')
                ->oculta()
                ->formato(fn (Incidente $fila): ?string => $fila->sistema?->codigo),

            Columna::texto('activos', 'Activos')
                ->alinear(Alineacion::Derecha)
                ->oculta()
                ->ancho('7rem')
                ->formato(fn (Incidente $fila): string => (string) (int) $fila->getAttribute('activos_afectados')),

            Columna::texto('no_conformidad', 'No conformidad')
                ->oculta()
                ->ayuda('No todo incidente abre una: sólo el que incumple algo.')
                ->formato(fn (Incidente $fila): ?string => $fila->getAttribute('no_conformidad')),
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
                'impacto' => 'titulo',
                'leccion_aprendida' => 'titulo',
            ])->placeholder('Buscar por código, título, impacto o lección aprendida…'),

            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoIncidente $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoIncidente::cases(),
            )),

            Filtro::multiSelect('peligrosidad', 'Peligrosidad', array_map(
                static fn (PeligrosidadIncidente $nivel): Opcion => new Opcion($nivel->value, $nivel->etiqueta()),
                PeligrosidadIncidente::cases(),
            )),

            Filtro::multiSelect('clasificacion', 'Clasificación', array_map(
                static fn (ClasificacionIncidente $clase): Opcion => new Opcion($clase->value, $clase->etiqueta()),
                ClasificacionIncidente::cases(),
            )),

            // Acotado a la organización a mano: `User` no lleva el scope.
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),

            Filtro::rangoFechas('fecha_deteccion', 'Detectado'),

            /*
             * Por scope, con la clave del indicador que cuenta lo mismo: es lo
             * que garantiza que pulsar la cifra del panel enseñe exactamente esa
             * cifra.
             */
            Filtro::porScope('abiertos', 'Sólo abiertos', 'abiertos')->enColumna('estado'),
            Filtro::porScope('fuera_de_plazo_aepd', 'Fuera de plazo con la AEPD', 'fueraDePlazoAepd')->enColumna('aepd'),
            Filtro::porScope('en_plazo_aepd', 'Pendiente de notificar a la AEPD', 'enPlazoAepd')->enColumna('aepd'),
            Filtro::porScope('sin_leccion', 'Resueltos sin lección aprendida', 'sinLeccion')->enColumna('estado'),
            Filtro::porScope('sin_tratar', 'Sin no conformidad detrás', 'sinTratar')->enColumna('no_conformidad'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/incidentes/{id}'),
            Accion::eliminar(
                '/incidentes/{id}',
                '¿Eliminar el incidente? Se pierde su histórico y, con él, cuánto se tardó en contenerlo. '
                .'op.exp.7 pide que quede registro: si resultó no ser un incidente, dilo en su lección aprendida y ciérralo.',
            )->permiso(Permiso::IncidentesGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Registrar incidente', '/incidentes/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::IncidentesGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha_deteccion';
    }
}
