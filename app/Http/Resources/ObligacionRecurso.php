<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Obligacion\Models\Compromiso;
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
 * El registro de obligaciones periódicas: la mitad del § 4.16 que no sale de
 * ningún otro sitio.
 *
 * **La columna que se mira es «Próximo vencimiento»**, y por eso es un badge con
 * «vence en 12 días» y no una fecha suelta: una fecha obliga a compararla con hoy
 * fila a fila, que es exactamente el argumento por el que «Plazo» es así en
 * tareas. La fecha la calcula el dominio —`Compromiso::proximaFecha()`— y no este
 * recurso.
 *
 * **Sin acciones masivas, y se declara aquí para que no se lea como un olvido.**
 * Marcar veinte compromisos como cumplidos de golpe escribe veinte filas de
 * histórico que nadie ha mirado, y cada cumplimiento lleva su fecha, su prueba y
 * su nota. Es el mismo argumento que dejó fuera las masivas de indicadores.
 *
 * @extends Recurso<Compromiso>
 */
final class ObligacionRecurso extends Recurso
{
    /**
     * La ventana larga: noventa días y no treinta.
     *
     * Se declara aquí además de en el scope porque es lo que la etiqueta del
     * filtro tiene que decir, y una cifra escrita dos veces se desincroniza.
     */
    private const DIAS_POR_VENCER = 90;

    public function clave(): string
    {
        return 'obligaciones';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Obligación',
            plural: 'Obligaciones',
            descripcion: 'Lo que hay que hacer cada tanto y no sale de ningún otro registro: el informe INES, la renovación de conformidad, las auditorías de seguimiento. Cada una con su cadencia y con la prueba de la última vez que se cumplió.',
            vacio: 'No hay ninguna obligación asumida. Las habituales del catálogo se pueden asumir de una vez desde aquí.',
        );
    }

    /** @return Builder<Compromiso> */
    public function consulta(): Builder
    {
        return Compromiso::query()->with(['responsable', 'obligacion', 'sistema', 'cumplimientos']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('titulo', 'Obligación')->ordenable()->anclada(),

            /*
             * **La columna que de verdad se mira.** Fecha y distancia juntas, como
             * el plazo de una tarea: sin la distancia hay que restar de cabeza en
             * cada fila, y con quince filas nadie lo hace.
             */
            Columna::badge('proximo_vencimiento', 'Próximo vencimiento')
                ->ancho('13rem')
                ->ayuda('Sale del último cumplimiento registrado más la cadencia. Sin cumplimientos, de la fecha desde la que corre el reloj.')
                ->formato(function (Compromiso $fila): ValorEtiquetado {
                    $fecha = $fila->proximaFecha();
                    $dias = (int) Carbon::today()->diffInDays($fecha, false);

                    return new ValorEtiquetado(
                        $fecha->toDateString(),
                        $fecha->format('d/m/Y').' · '.$this->cuando($dias),
                        $dias < 0 ? 'caducada' : ($dias <= self::DIAS_POR_VENCER ? 'en_progreso' : 'implantado'),
                        null,
                    );
                }),

            Columna::badge('estado', 'Estado')
                ->ancho('10rem')
                ->formato(function (Compromiso $fila): ValorEtiquetado {
                    if (! $fila->activo) {
                        return new ValorEtiquetado('retirado', 'Retirada', 'no_aplica', 'Ban');
                    }

                    if ($fila->cumplimientos->isEmpty()) {
                        return new ValorEtiquetado('nunca', 'Nunca cumplida', 'no_iniciado', 'Circle');
                    }

                    return $fila->vencido()
                        ? new ValorEtiquetado('vencida', 'Fuera de plazo', 'caducada', 'TriangleAlert')
                        : new ValorEtiquetado('al_dia', 'Al día', 'implantado', 'CircleCheck');
                }),

            /*
             * El tipo en tono `marco`: el chip neutro monoespaciado que DESIGN.md
             * reserva a los identificadores sin grados. Inventar una familia de
             * color para siete tipos de obligación rompería la rueda de hue, que
             * ya está agotada.
             */
            Columna::badge('tipo', 'Tipo')
                ->ancho('12rem')
                ->formato(function (Compromiso $fila): ValorEtiquetado {
                    $obligacion = $fila->obligacion;

                    return $obligacion === null
                        ? new ValorEtiquetado('propia', 'Propia', 'marco', null)
                        : new ValorEtiquetado($obligacion->codigo, $obligacion->codigo, 'marco', null);
                }),

            Columna::texto('cadencia', 'Cadencia')
                ->ordenable('periodicidad_meses')
                ->ancho('8rem')
                ->formato(fn (Compromiso $fila): string => $fila->cadencia()->etiqueta()),

            Columna::texto('responsable', 'Responsable')
                ->ayuda('Quién responde de que esto se haga a tiempo.')
                ->formato(fn (Compromiso $fila): ?string => $fila->responsable?->name),

            Columna::fecha('ultimo_cumplimiento', 'Último cumplimiento')
                ->ancho('11rem')
                ->ayuda('La pregunta del auditor no es «¿se hace?», es «¿desde cuándo?».')
                ->formato(fn (Compromiso $fila): ?string => $fila->cumplimientos->first()?->fecha?->toDateString()),

            Columna::numero('cumplimientos', 'Registros')
                ->oculta()
                ->formato(fn (Compromiso $fila): int => $fila->cumplimientos->count()),

            Columna::texto('codigo', 'Código')->ordenable()->oculta()->ancho('8rem'),

            Columna::texto('sistema', 'Sistema')
                ->oculta()
                ->ayuda('La renovación de conformidad es de un sistema concreto; el informe INES, de la organización entera.')
                ->formato(fn (Compromiso $fila): ?string => $fila->sistema?->nombre),

            Columna::booleano('activo', 'Vigente')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'titulo' => 'titulo',
                'codigo' => 'titulo',
                'notas' => 'titulo',
            ])->placeholder('Buscar por título, código o notas…'),

            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),

            /*
             * Por scope, y la clave de cada filtro **es** la clave de su indicador
             * en `RegistroObligaciones`. Es lo que hace que la cifra del panel y
             * la lista no puedan divergir.
             */
            Filtro::porScope('vencidas', 'Fuera de plazo', 'vencidos')->enColumna('estado'),
            Filtro::porScope('por_vencer', 'Vence en '.self::DIAS_POR_VENCER.' días', 'porVencer')->enColumna('proximo_vencimiento'),
            Filtro::porScope('nunca_cumplidas', 'Nunca cumplidas', 'nuncaCumplidos')->enColumna('ultimo_cumplimiento'),
            Filtro::porScope('sin_responsable', 'Sin responsable', 'sinResponsable')->enColumna('responsable'),
            Filtro::porScope('activos', 'Sólo las vigentes', 'activos')->enColumna('activo'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/obligaciones/{id}'),
            Accion::editar('/obligaciones/{id}/editar')->permiso(Permiso::ObligacionesGestionar->value),
            Accion::eliminar(
                '/obligaciones/{id}',
                '¿Eliminar la obligación? Se lleva por delante el histórico de cumplimiento, que es la prueba de '
                .'que se cumplió mientras aplicaba. Si lo que pasa es que ya no aplica, retírala: el histórico se queda.',
            )->permiso(Permiso::ObligacionesGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nueva obligación', '/obligaciones/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ObligacionesGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'titulo';
    }

    /** «vence en 12 días» / «venció hace 4 días», que es lo que se lee de un vistazo. */
    private function cuando(int $dias): string
    {
        return match (true) {
            $dias < 0 => 'venció hace '.abs($dias).' '.($dias === -1 ? 'día' : 'días'),
            $dias === 0 => 'vence hoy',
            default => 'vence en '.$dias.' '.($dias === 1 ? 'día' : 'días'),
        };
    }
}
