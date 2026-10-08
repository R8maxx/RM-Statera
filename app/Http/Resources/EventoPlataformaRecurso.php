<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * La traza de la plataforma, entera y filtrable (punto 50).
 *
 * Es lo que pregunta el auditor de **nuestro** SGSI: quién tuvo acceso
 * privilegiado y qué hizo con él (ISO A.8.2 y A.8.15, ENS op.acc y op.exp.8).
 * Hasta aquí sólo se veían los veinte eventos de cada cliente en su ficha.
 *
 * El detalle sale por `EventoPlataforma::detalleLegible()`, que no enseña nunca
 * una clave secreta, y en pantalla y en CSV es lo mismo.
 *
 * @extends Recurso<EventoPlataforma>
 */
final class EventoPlataformaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'plataforma-traza';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Evento',
            plural: 'Traza de la plataforma',
            descripcion: 'Todo lo que han hecho quienes administran Statera: entradas, altas, cambios de plan, soporte, bajas. No se puede borrar ni cambiar.',
            vacio: 'No hay eventos que coincidan.',
        );
    }

    /** @return Builder<EventoPlataforma> */
    public function consulta(): Builder
    {
        return EventoPlataforma::query()
            ->select('eventos_plataforma.*')
            ->selectRaw('(select users.name from users where users.id = eventos_plataforma.usuario_id) as autor')
            ->selectRaw('(select organizaciones.nombre from organizaciones where organizaciones.id = eventos_plataforma.organizacion_afectada_id) as organizacion');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::fechaHora('created_at', 'Cuándo')->ordenable()->anclada(),
            Columna::texto('accion', 'Qué')
                ->ancho('15rem')
                ->formato(fn (EventoPlataforma $fila): string => $fila->accion->etiqueta()),
            Columna::texto('autor', 'Quién')->ancho('14rem'),
            Columna::texto('organizacion', 'Cliente')->ancho('14rem'),
            Columna::texto('detalle', 'Detalle')
                ->formato(fn (EventoPlataforma $fila): ?string => $fila->detalleLegible()),
            Columna::texto('ip', 'IP')->ancho('9rem')->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::multiSelect('accion', 'Qué', array_map(
                static fn (AccionPlataforma $accion): Opcion => new Opcion($accion->value, $accion->etiqueta()),
                AccionPlataforma::cases(),
            )),

            // Sólo cuentas de la plataforma: no lista a usuarios de ningún cliente.
            Filtro::select('usuario_id', 'Quién', fn (): array => User::query()
                ->where('es_plataforma', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (User $cuenta): Opcion => new Opcion((string) $cuenta->id, $cuenta->name))
                ->values()
                ->all())->enColumna('autor'),

            Filtro::select('organizacion_afectada_id', 'Cliente', fn (): array => Organizacion::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(static fn (Organizacion $organizacion): Opcion => new Opcion((string) $organizacion->id, $organizacion->nombre))
                ->values()
                ->all())->enColumna('organizacion'),

            Filtro::rangoFechas('created_at', 'Cuándo'),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return '-created_at';
    }
}
