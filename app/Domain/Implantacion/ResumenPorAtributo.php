<?php

declare(strict_types=1);

namespace App\Domain\Implantacion;

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Implantacion\Models\Implantacion;
use App\Http\Resources\Panel\SegmentoEstado;
use Illuminate\Support\Facades\DB;

/**
 * Lo exigible agrupado por un atributo de la ISO 27002: § 4.4, «agrupar».
 *
 * Contesta lo que la tabla no puede, porque no tiene sub-filas: **cómo va cada
 * valor de un atributo** —qué parte de los controles detectivos está implantada,
 * cuánto queda en «recuperar»—. Cada fila lleva a la tabla filtrada por ese
 * valor, que es donde se trabaja.
 *
 * **Sobre lo exigible**, como todo recuento del producto (`aplica = true`): un
 * control que no se le exige al sistema no está pendiente. Y **las filas no
 * suman el total**: un control lleva a menudo varios valores de la misma
 * dimensión —A.5.1 es preventivo, y sus propiedades son C, I y D—, así que cuenta
 * en cada uno. Se dice en la pantalla.
 *
 * Las dimensiones y sus valores salen **del vocabulario del catálogo**, no de
 * una lista escrita aquí: el mismo criterio que los filtros de
 * `ImplantacionRecurso`.
 */
final readonly class ResumenPorAtributo
{
    /**
     * Las dimensiones que se pueden agrupar, en el orden del vocabulario.
     *
     * @return array<string, array{etiqueta: string, valores: array<string, string>}>
     */
    public function dimensiones(): array
    {
        $dimensiones = [];

        foreach (Marco::query()->orderBy('codigo')->get() as $marco) {
            foreach ($marco->atributos as $dimension) {
                $dimensiones[$dimension['clave']] ??= ['etiqueta' => $dimension['etiqueta'], 'valores' => []];
                $dimensiones[$dimension['clave']]['valores'] += $marco->valoresDeAtributo($dimension['clave']);
            }
        }

        return $dimensiones;
    }

    /**
     * Una fila por valor de la dimensión, con su reparto por estado.
     *
     * Nulo si la dimensión no existe: la clave llega de la petición y sólo se
     * acepta si está en el vocabulario, que es además lo que hace segura la
     * consulta —la ruta del JSONB va como binding, y la clave no se interpola—.
     *
     * @return list<array{valor: string, etiqueta: string, total: int, implantadas: int, segmentos: list<SegmentoEstado>}>|null
     */
    public function para(string $dimension, ?int $sistemaId = null): ?array
    {
        $vocabulario = $this->dimensiones()[$dimension] ?? null;

        if ($vocabulario === null) {
            return null;
        }

        $conteos = [];

        $filas = Implantacion::query()
            ->aplicables()
            ->when($sistemaId !== null, fn ($consulta) => $consulta->delSistema((int) $sistemaId))
            ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
            ->crossJoin(DB::raw('lateral jsonb_array_elements_text(requisitos.atributos -> ?::text) as atributo(valor)'))
            ->addBinding($dimension, 'join')
            ->groupBy('atributo.valor', 'implantaciones.estado')
            ->selectRaw('atributo.valor as valor, implantaciones.estado as estado, count(*) as total')
            ->toBase()
            ->get();

        foreach ($filas as $fila) {
            $conteos[(string) $fila->valor][(string) $fila->estado] = (int) $fila->total;
        }

        $resultado = [];

        foreach ($vocabulario['valores'] as $valor => $etiqueta) {
            $porEstado = $conteos[(string) $valor] ?? [];
            $segmentos = [];

            foreach (ResumenCumplimiento::ORDEN_DE_LA_BARRA as $estado) {
                $segmentos[] = new SegmentoEstado($estado->value, $estado->etiqueta(), $porEstado[$estado->value] ?? 0);
            }

            $resultado[] = [
                'valor' => (string) $valor,
                'etiqueta' => $etiqueta,
                'total' => array_sum($porEstado),
                'implantadas' => $porEstado['implantado'] ?? 0,
                'segmentos' => $segmentos,
            ];
        }

        return $resultado;
    }
}
