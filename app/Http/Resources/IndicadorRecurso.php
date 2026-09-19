<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Models\Indicador;
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
 * El cuadro de indicadores: § 4.14 y cláusula 9.1.
 *
 * La última medición llega por **relación cargada y no por join**, igual que las
 * cifras de un documento o las acciones de una no conformidad: un join contra
 * `mediciones` multiplicaría las filas —un indicador con ocho trimestres saldría
 * ocho veces— y la paginación contaría mal.
 *
 * **La columna que se mira es «Cumplimiento»**, y la que gasta rojo no es ésa:
 * es «Periodo», cuando el último cerrado pasó sin medir. Quedarse por debajo de
 * un objetivo es la distancia que queda; no medir habiéndose comprometido a
 * medir es la cláusula 9.1 sin hacer. El mismo reparto que en tareas, donde el
 * rojo es del plazo y no del estado.
 *
 * @extends Recurso<Indicador>
 */
final class IndicadorRecurso extends Recurso
{
    public function clave(): string
    {
        return 'indicadores';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Indicador',
            plural: 'Indicadores',
            descripcion: 'Qué mide la organización, cada cuánto y contra qué objetivo. La cláusula 9.1 pregunta las tres cosas, y la serie es la que contesta si algo ha mejorado.',
            vacio: 'No hay indicadores declarados. Statera calcula unos cuantos solo; el resto se registran a mano.',
        );
    }

    /** @return Builder<Indicador> */
    public function consulta(): Builder
    {
        return Indicador::query()->with(['responsable', 'marco', 'ultimaMedicion']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('7rem'),

            Columna::texto('nombre', 'Indicador')->ordenable(),

            Columna::badge('cumplimiento', 'Cumplimiento')
                ->ancho('11rem')
                ->ayuda('Se juzga contra el objetivo que estaba puesto al cerrar el periodo, no contra el de hoy.')
                ->formato(function (Indicador $fila): ValorEtiquetado {
                    $cumplimiento = $fila->cumplimiento();

                    return new ValorEtiquetado(
                        $cumplimiento->value,
                        $cumplimiento->etiqueta(),
                        $cumplimiento->tono(),
                        $cumplimiento->icono(),
                    );
                }),

            /*
             * La cifra con su denominador al lado, como todas las del producto:
             * «14 %» no dice lo mismo que «14 % (43 de 307)».
             */
            Columna::texto('ultimo_valor', 'Última cifra')
                ->alinear(Alineacion::Derecha)
                ->ancho('10rem')
                ->formato(function (Indicador $fila): ?string {
                    $medicion = $fila->ultimaMedicion;

                    if ($medicion === null) {
                        return null;
                    }

                    $escrito = $fila->unidad->escribir((float) $medicion->valor);
                    $fraccion = $medicion->fraccion();

                    return $fraccion === null ? $escrito : "{$escrito} ({$fraccion})";
                }),

            Columna::texto('objetivo', 'Objetivo')
                ->ordenable()
                ->alinear(Alineacion::Derecha)
                ->ancho('8rem')
                ->formato(fn (Indicador $fila): ?string => $fila->objetivo === null
                    ? null
                    : $fila->sentido->comparador().' '.$fila->unidad->escribir((float) $fila->objetivo)),

            /*
             * **El rojo del módulo.** Un periodo cerrado sin medir es la 9.1 sin
             * hacer, y es lo primero que se comprueba en una auditoría del
             * seguimiento. No lo gasta ningún otro valor de esta tabla.
             */
            Columna::badge('periodo', 'Periodo')
                ->ancho('10rem')
                ->formato(function (Indicador $fila): ValorEtiquetado {
                    $medicion = $fila->ultimaMedicion;

                    if ($fila->tienePeriodoSinMedir()) {
                        [$inicio] = $fila->periodoACerrar();

                        return new ValorEtiquetado(
                            $inicio->toDateString(),
                            $fila->periodicidad->etiquetaDe($inicio).' sin medir',
                            'caducada',
                            null,
                        );
                    }

                    if ($medicion === null) {
                        return new ValorEtiquetado('', 'Sin medir', 'no_iniciado', null);
                    }

                    return new ValorEtiquetado(
                        $medicion->periodo_inicio->toDateString(),
                        $fila->periodicidad->etiquetaDe($medicion->periodo_inicio),
                        'no_aplica',
                        null,
                    );
                }),

            Columna::texto('periodicidad', 'Cadencia')
                ->ordenable()
                ->oculta()
                ->formato(fn (Indicador $fila): string => $fila->periodicidad->etiqueta()),

            Columna::texto('origen', 'Origen')
                ->ordenable()
                ->oculta()
                ->formato(fn (Indicador $fila): string => $fila->origen->etiqueta()),

            Columna::texto('marco', 'Marco')
                ->oculta()
                ->formato(fn (Indicador $fila): ?string => $fila->marco?->codigo),

            Columna::texto('responsable', 'Responsable')
                ->formato(fn (Indicador $fila): ?string => $fila->responsable?->name),

            Columna::booleano('activo', 'En seguimiento')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'nombre' => 'nombre',
                'descripcion' => 'nombre',
            ])->placeholder('Buscar por código, nombre o descripción…'),

            Filtro::multiSelect('periodicidad', 'Cadencia', array_map(
                static fn (Periodicidad $caso): Opcion => new Opcion($caso->value, $caso->etiqueta()),
                Periodicidad::cases(),
            )),

            Filtro::multiSelect('origen', 'Origen', array_map(
                static fn (OrigenMedicion $caso): Opcion => new Opcion($caso->value, $caso->etiqueta()),
                OrigenMedicion::cases(),
            )),

            /*
             * El marco es del catálogo global: no lleva `organizacion_id` y no
             * hay nada que acotar (invariante 2).
             */
            Filtro::select('marco_id', 'Marco', fn (): array => Marco::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Marco $marco): Opcion => new Opcion((string) $marco->id, $marco->nombre))
                ->all())->enColumna('marco'),

            /*
             * Acotado a mano, como en el resto del producto: `User` no lleva
             * `PerteneceAOrganizacion`, así que aquí no hay scope global ni RLS
             * que tapen el cruce y sin este `where` el desplegable listaría a los
             * usuarios de todos los clientes.
             */
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),

            /*
             * Por scope, y la clave de cada filtro **es** la clave de su indicador
             * en `RegistroIndicadores`. Es lo que hace que pulsar la cifra del
             * panel enseñe exactamente esa cifra.
             */
            Filtro::porScope('periodo_sin_medir', 'Periodo sin medir', 'periodoSinMedir')->enColumna('periodo'),
            Filtro::porScope('fuera_de_objetivo', 'Fuera de objetivo', 'fueraDeObjetivo')->enColumna('cumplimiento'),
            Filtro::porScope('sin_medir', 'Nunca medidos', 'sinMedir')->enColumna('ultimo_valor'),
            Filtro::porScope('sin_objetivo', 'Sin objetivo', 'sinObjetivo')->enColumna('objetivo'),
            Filtro::porScope('activos', 'Sólo los que se siguen', 'activos')->enColumna('activo'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/indicadores/{id}'),
            Accion::eliminar(
                '/indicadores/{id}',
                '¿Eliminar el indicador? Se lleva por delante toda su serie histórica, que es lo que demuestra '
                .'el seguimiento de la cláusula 9.1. Si lo que se quiere es dejar de medirlo, retíralo: la serie se queda.',
            )->permiso(Permiso::IndicadoresGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nuevo indicador', '/indicadores/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::IndicadoresGestionar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }
}
