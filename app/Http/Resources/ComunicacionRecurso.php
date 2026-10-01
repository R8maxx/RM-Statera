<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Comunicacion\Enums\SentidoComunicacion;
use App\Domain\Comunicacion\Enums\TipoRetroalimentacion;
use App\Domain\Comunicacion\Models\Comunicacion;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que se comunicó y lo que se recibió.
 *
 * **Las dos cosas en la misma tabla**, porque son la misma conversación con las
 * mismas partes interesadas. El sentido es la primera columna después del
 * código: es lo primero que se pregunta de una fila.
 *
 * **Ninguna celda en rojo.** Lo recibido no es una alarma —una queja apuntada es
 * una organización que escucha— y lo emitido ya pasó: el plazo es del plan.
 *
 * @extends Recurso<Comunicacion>
 */
final class ComunicacionRecurso extends Recurso
{
    public function clave(): string
    {
        return 'comunicaciones';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Comunicación',
            plural: 'Comunicaciones',
            descripcion: 'Lo que se comunicó y lo que se recibió de las partes interesadas. Lo recibido es la retroalimentación que revisa la dirección (9.3.2 e).',
            vacio: 'No hay comunicaciones registradas. Lo emitido se apunta desde el plan; lo recibido —una queja, una encuesta— desde aquí.',
        );
    }

    /** @return Builder<Comunicacion> */
    public function consulta(): Builder
    {
        return Comunicacion::query()->with(['prevista', 'parteInteresada', 'evidencia']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::fecha('fecha', 'Fecha')->ordenable()->anclada()->ancho('8rem'),

            Columna::badge('sentido', 'Sentido')
                ->ordenable()
                ->ancho('8rem')
                ->formato(fn (Comunicacion $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->sentido->value,
                    $fila->sentido->etiqueta(),
                    $fila->sentido->tono(),
                    $fila->sentido->icono(),
                )),

            Columna::texto('asunto', 'Asunto')->ordenable(),

            Columna::texto('tipo_recibida', 'Tipo')
                ->ordenable()
                ->formato(fn (Comunicacion $fila): ?string => $fila->tipo_recibida?->etiqueta()),

            Columna::texto('parte', 'Parte interesada')
                ->formato(fn (Comunicacion $fila): ?string => $fila->parteInteresada?->nombre),

            Columna::texto('prevista', 'Del plan')
                ->formato(fn (Comunicacion $fila): ?string => $fila->prevista?->codigo),

            Columna::texto('canal', 'Canal')
                ->oculta()
                ->formato(fn (Comunicacion $fila): string => $fila->canal->etiqueta()),

            Columna::texto('resumen', 'Resumen')->oculta(),
            Columna::texto('respuesta', 'Respuesta')->oculta(),

            Columna::texto('evidencia', 'Prueba')
                ->oculta()
                ->formato(fn (Comunicacion $fila): ?string => $fila->evidencia?->titulo),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'asunto' => 'asunto',
                'resumen' => 'resumen',
                'respuesta' => 'respuesta',
            ])->placeholder('Buscar por asunto, resumen o respuesta…'),

            Filtro::select('sentido', 'Sentido', array_map(
                static fn (SentidoComunicacion $sentido): Opcion => new Opcion($sentido->value, $sentido->etiqueta()),
                SentidoComunicacion::cases(),
            )),

            Filtro::multiSelect('tipo_recibida', 'Tipo', array_map(
                static fn (TipoRetroalimentacion $tipo): Opcion => new Opcion($tipo->value, $tipo->etiqueta()),
                TipoRetroalimentacion::cases(),
            )),

            Filtro::select('parte_interesada_id', 'Parte interesada', fn (): array => ParteInteresada::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (ParteInteresada $parte): Opcion => new Opcion((string) $parte->id, $parte->nombre))
                ->all())->enColumna('parte'),

            Filtro::multiSelect('canal', 'Canal', array_map(
                static fn (CanalComunicacion $canal): Opcion => new Opcion($canal->value, $canal->etiqueta()),
                CanalComunicacion::cases(),
            )),

            Filtro::rangoFechas('fecha', 'Fecha'),

            // La misma clave y el mismo scope que el indicador de la tira.
            Filtro::porScope('sin_respuesta', 'Recibidas sin respuesta', 'sinRespuesta')->enColumna('tipo_recibida'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::editar('/comunicaciones/{id}/editar')->permiso(Permiso::ComunicacionGestionar->value),
            Accion::eliminar(
                '/comunicaciones/{id}',
                '¿Eliminar la comunicación? Si cumplía una línea del plan, esa línea vuelve a contar desde la anterior.',
            )->permiso(Permiso::ComunicacionGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('recibida', 'Registrar lo recibido', '/comunicaciones/crear?sentido=recibida', MetodoAccion::Get))
                ->icono('LogIn')
                ->permiso(Permiso::ComunicacionGestionar->value),
            (new Accion('emitida', 'Registrar lo comunicado', '/comunicaciones/crear?sentido=emitida', MetodoAccion::Get))
                ->icono('LogOut')
                ->permiso(Permiso::ComunicacionGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-fecha';
    }
}
