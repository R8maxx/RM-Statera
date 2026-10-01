<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use App\Domain\Comunicacion\RegistroComunicacion;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Obligacion\Cadencia;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * El plan de comunicación: la cláusula 7.4, en modo tabla.
 *
 * Una fila por línea del plan, con las cinco preguntas de la norma en columnas:
 * qué, cuándo, a quién, quién y cómo. **La columna que se mira es «Próxima»**, y
 * por eso es un badge con la distancia y no una fecha suelta, como el próximo
 * vencimiento de una obligación.
 *
 * La próxima fecha llega **por SQL** —`expresionProxima()` como columna añadida—
 * y no fila a fila, por lo mismo que en `ObligacionRecurso`.
 *
 * @extends Recurso<ComunicacionPrevista>
 */
final class ComunicacionPrevistaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'plan_comunicacion';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Comunicación prevista',
            plural: 'Plan de comunicación',
            descripcion: 'Qué se comunica, cuándo, a quién, quién y cómo. La cláusula 7.4 pide decidirlo, y esto es donde se decide.',
            vacio: 'El plan de comunicación está vacío. Empieza por lo que ya se hace: el informe a la dirección, el aviso de la política al personal.',
        );
    }

    /** @return Builder<ComunicacionPrevista> */
    public function consulta(): Builder
    {
        return ComunicacionPrevista::query()
            ->select('comunicaciones_previstas.*')
            ->selectRaw(ComunicacionPrevista::expresionProxima().' as proxima_fecha')
            ->with(['responsable', 'partesInteresadas']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('7rem'),

            Columna::texto('titulo', 'Qué se comunica')->ordenable(),

            Columna::texto('cadencia', 'Cuándo')
                ->ordenable('periodicidad_meses')
                ->ancho('9rem')
                ->formato(fn (ComunicacionPrevista $fila): string => $fila->cadencia()?->etiqueta() ?? 'Cuando proceda'),

            Columna::badge('proxima', 'Próxima')
                ->ordenable('proxima_fecha')
                ->ancho('13rem')
                ->ayuda('La última vez que se comunicó más la cadencia. Sin ninguna, desde la fecha en que empieza a contar.')
                ->formato(fn (ComunicacionPrevista $fila): ValorEtiquetado => self::proxima($fila)),

            Columna::texto('destinatarios', 'A quién')
                ->formato(fn (ComunicacionPrevista $fila): string => self::destinatarios($fila)),

            Columna::texto('responsable', 'Quién')
                ->formato(fn (ComunicacionPrevista $fila): ?string => $fila->responsable?->name),

            Columna::texto('canal', 'Cómo')
                ->ordenable()
                ->formato(fn (ComunicacionPrevista $fila): string => $fila->canal->etiqueta()),
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
            ])->placeholder('Buscar por código o por lo que se comunica…'),

            Filtro::multiSelect('canal', 'Cómo', array_map(
                static fn (CanalComunicacion $canal): Opcion => new Opcion($canal->value, $canal->etiqueta()),
                CanalComunicacion::cases(),
            )),

            // Acotado a mano: `User` no lleva `PerteneceAOrganizacion`.
            Filtro::select('responsable_id', 'Quién', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),

            Filtro::porRelacion('parte_interesada', 'A quién', 'partesInteresadas', 'partes_interesadas.id', fn (): array => ParteInteresada::query()
                ->vigentes()
                ->orderBy('nombre')
                ->get()
                ->map(fn (ParteInteresada $parte): Opcion => new Opcion((string) $parte->id, $parte->nombre))
                ->all(), multiple: true)->enColumna('destinatarios'),

            // Por defecto se enseñan las vivas; las retiradas se piden.
            Filtro::porScope('activas', 'Sólo vigentes', 'activas')->sinColumna(),
            Filtro::porScope('vencidas', 'Fuera de plazo', 'vencidas')->enColumna('proxima'),
            Filtro::porScope('por_vencer', 'Tocan pronto', 'porVencer')->enColumna('proxima'),
            Filtro::porScope('sin_destinatarios', 'Sin destinatarios', 'sinDestinatarios')->enColumna('destinatarios'),
            Filtro::porScope('sin_responsable', 'Sin responsable', 'sinResponsable')->enColumna('responsable'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/plan-comunicacion/{id}'),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva comunicación', '/plan-comunicacion/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ComunicacionGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }

    /**
     * La próxima vez que toca, con la distancia dicha. Lo leen la tabla y la ficha.
     *
     * Retirada o sin cadencia no hay próxima, y se dice: «Cuando proceda» es una
     * respuesta legítima, no un hueco.
     */
    public static function proxima(ComunicacionPrevista $fila): ValorEtiquetado
    {
        if ($fila->estaRetirada()) {
            return new ValorEtiquetado('retirada', 'Retirada', 'no_aplica', 'Ban');
        }

        $alias = $fila->getAttribute('proxima_fecha');
        $fecha = $alias !== null
            ? Carbon::parse((string) $alias)->startOfDay()
            : $fila->proximaFecha();

        if ($fecha === null) {
            return new ValorEtiquetado('sin_cadencia', 'Cuando proceda', 'no_iniciado', null);
        }

        $dias = (int) Carbon::today()->diffInDays($fecha, false);

        $cuando = match (true) {
            $dias < 0 => 'tocaba hace '.abs($dias).' '.(abs($dias) === 1 ? 'día' : 'días'),
            $dias === 0 => 'toca hoy',
            default => "en {$dias} ".($dias === 1 ? 'día' : 'días'),
        };

        return new ValorEtiquetado(
            $fecha->toDateString(),
            $fecha->format('d/m/Y').' · '.$cuando,
            $dias < 0 ? 'caducada' : ($dias <= RegistroComunicacion::DIAS_POR_VENCER ? 'en_progreso' : 'implantado'),
            null,
        );
    }

    /** A quién, en una línea: las partes interesadas y los demás destinatarios. */
    public static function destinatarios(ComunicacionPrevista $fila): string
    {
        $nombres = $fila->partesInteresadas->pluck('nombre')->all();

        if ($fila->destinatarios_otros !== null && trim($fila->destinatarios_otros) !== '') {
            $nombres[] = trim($fila->destinatarios_otros);
        }

        return $nombres === [] ? '—' : implode(', ', $nombres);
    }

    /**
     * Las cadencias que se ofrecen en el formulario. La lista corta de las que
     * `Cadencia` sabe nombrar; una cadencia rara se escribe en meses.
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function cadencias(): array
    {
        return array_map(
            static fn (int $meses): array => ['valor' => (string) $meses, 'etiqueta' => (new Cadencia($meses))->etiqueta()],
            [1, 3, 6, 12, 24],
        );
    }
}
