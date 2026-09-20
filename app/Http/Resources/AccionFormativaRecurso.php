<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Persona\Models\AccionFormativa;
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
 * El registro de formación y concienciación: `mp.per.4` y `mp.per.3`.
 *
 * **Pantalla propia y no un bloque de la ficha de una persona**, por el mismo
 * motivo que la checklist de una auditoría: lo que se registra es la sesión con
 * sus veinte asistentes, no veinte veces la misma sesión. Desde la ficha de la
 * persona se ve su formación; desde aquí se convoca y se marca la asistencia en
 * bloque.
 *
 * **Ninguna columna gasta rojo.** Una sesión sin asistentes registrados está a
 * medias, no incumple: el rojo de este módulo es la salida sin cerrar, y vive en
 * el registro de personas.
 *
 * @extends Recurso<AccionFormativa>
 */
final class AccionFormativaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'formacion';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Acción formativa',
            plural: 'Formación y concienciación',
            descripcion: 'Las sesiones impartidas y quién asistió. El ENS las separa en dos medidas: concienciación es recordar lo que todos tienen que saber, y formación es enseñar a hacer algo a quien lo hace.',
            vacio: 'No hay sesiones registradas. Sin registro de asistencia, mp.per.3 y mp.per.4 están declaradas y no probadas.',
        );
    }

    /** @return Builder<AccionFormativa> */
    public function consulta(): Builder
    {
        $asistencia = fn (string $condicion): string => <<<SQL
            (select count(*) from asistencias a where a.accion_formativa_id = acciones_formativas.id {$condicion})
        SQL;

        return AccionFormativa::query()
            ->select('acciones_formativas.*')
            ->selectRaw($asistencia('').' as convocadas_total')
            ->selectRaw($asistencia('and a.asistio').' as asistentes_total')
            ->with('evidencia');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('8rem'),

            Columna::texto('titulo', 'Sesión')->ordenable(),

            Columna::badge('tipo', 'Tipo')
                ->ordenable()
                ->ancho('9rem')
                ->formato(fn (AccionFormativa $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->tipo->value,
                    $fila->tipo->etiqueta(),
                    $fila->tipo->tono(),
                    $fila->tipo->icono(),
                )),

            Columna::texto('medida', 'Medida')
                ->ancho('7rem')
                ->ayuda('La medida del Anexo II que cubre.')
                ->formato(fn (AccionFormativa $fila): string => $fila->tipo->medida()),

            Columna::fecha('fecha', 'Fecha')->ordenable(),

            /*
             * Con su denominador, como toda cifra del producto: «12» no dice nada
             * y «12 de 15 convocadas» sí — y la diferencia entre las dos es
             * exactamente lo que un auditor pregunta.
             */
            Columna::texto('asistencia', 'Asistencia')
                ->alinear(Alineacion::Derecha)
                ->ancho('9rem')
                ->ayuda('Quienes asistieron sobre quienes fueron convocados.')
                ->formato(fn (AccionFormativa $fila): string => sprintf(
                    '%d de %d',
                    (int) $fila->getAttribute('asistentes_total'),
                    (int) $fila->getAttribute('convocadas_total'),
                )),

            Columna::texto('evidencia', 'Hoja de firmas')
                ->oculta()
                ->formato(fn (AccionFormativa $fila): ?string => $fila->evidencia?->titulo),

            Columna::numero('duracion_horas', 'Horas')->alinear(Alineacion::Derecha)->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'titulo' => 'titulo',
                'contenido' => 'titulo',
            ])->placeholder('Buscar por código, título o contenido…'),

            Filtro::multiSelect('tipo', 'Tipo', array_map(
                static fn (TipoAccionFormativa $tipo): Opcion => new Opcion($tipo->value, $tipo->etiqueta()),
                TipoAccionFormativa::cases(),
            )),

            Filtro::rangoFechas('fecha', 'Fecha'),

            Filtro::porScope('sin_asistencia', 'Sin asistencia registrada', 'sinAsistencia')->enColumna('asistencia'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/formacion/{id}'),
            Accion::eliminar(
                '/formacion/{id}',
                '¿Eliminar la sesión? Se pierde el registro de quién asistió, que es la prueba de mp.per.3 y mp.per.4.',
            )->permiso(Permiso::PersonasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva sesión', '/formacion/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::PersonasGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha';
    }
}
