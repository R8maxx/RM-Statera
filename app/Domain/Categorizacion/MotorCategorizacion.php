<?php

declare(strict_types=1);

namespace App\Domain\Categorizacion;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Enums\Exigencia;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\PerfilCumplimiento;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use Illuminate\Support\Facades\DB;

/**
 * Motor de categorización del ENS.
 *
 * Es una función pura, no un formulario (§3 de la especificación): dada la
 * valoración de las cinco dimensiones y, opcionalmente, un perfil de
 * cumplimiento, devuelve el conjunto exacto de medidas exigibles con su nivel de
 * refuerzo. No escribe nada; persistir el resultado es trabajo del punto 3.
 *
 * ```
 * paso 1:  categoría del sistema = máximo de las cinco dimensiones
 * paso 2:  consultar aplicabilidad_ens por esa categoría
 * paso 3:  aplicar modulación por nivel de dimensión donde corresponda
 * paso 4:  si hay perfil, intersecar con perfil_requisitos
 * ```
 *
 * Un fallo silencioso aquí deja a una organización fuera de conformidad sin que
 * nadie se entere, y por eso es la prioridad 1 de cobertura de tests.
 */
final class MotorCategorizacion
{
    public function calcular(
        Marco $marco,
        ValoracionDimensiones $valoracion,
        ?PerfilCumplimiento $perfil = null,
    ): ConjuntoExigible {
        $categoria = $valoracion->categoria();

        // Las cinco dimensiones a `na` no es categoría básica: es un sistema
        // fuera del ámbito del ENS, al que no se le exige ninguna medida.
        if ($categoria === null) {
            return new ConjuntoExigible;
        }

        $delPerfil = $perfil === null ? null : $this->exigenciasDelPerfil($perfil);

        $medidas = [];

        foreach ($this->matrizDe($marco) as $requisitoId => $medida) {
            // El perfil es una vista filtrada del catálogo: lo que no está en él
            // no se exige, por alta que sea la categoría del sistema.
            if ($delPerfil !== null && ! array_key_exists($requisitoId, $delPerfil)) {
                continue;
            }

            $exigible = $this->resolver($medida, $valoracion, $categoria);

            if ($delPerfil !== null) {
                $exigible = $this->aplicarPerfil($exigible, $medida, $delPerfil[$requisitoId]);
            }

            if ($exigible === null) {
                continue;
            }

            $medidas[] = $exigible;
        }

        return new ConjuntoExigible($medidas);
    }

    /**
     * Pasos 2 y 3. Devuelve `null` cuando la medida no aplica.
     *
     * @param  array{id: int, codigo: string, titulo: string, dimension: ?Dimension, celdas: array<string, Exigencia>}  $medida
     */
    private function resolver(
        array $medida,
        ValoracionDimensiones $valoracion,
        CategoriaEns $categoria,
    ): ?MedidaExigible {
        $dimension = $medida['dimension'];

        if ($dimension === null) {
            $exigencia = $medida['celdas'][$categoria->value] ?? null;
            $origen = OrigenExigencia::Categoria;
        } else {
            // Modulación por dimensión: la exigencia la dicta el nivel de esa
            // dimensión concreta, no la categoría global del sistema. Un sistema
            // de categoría alta por confidencialidad no debe medidas de
            // disponibilidad si su disponibilidad es `na`.
            $nivel = $valoracion->nivelDe($dimension);

            if ($nivel === NivelDimension::Na) {
                return null;
            }

            $categoriaModulada = $nivel->aCategoria();
            $exigencia = $categoriaModulada === null ? null : ($medida['celdas'][$categoriaModulada->value] ?? null);
            $origen = OrigenExigencia::ModulacionDimension;
        }

        if ($exigencia === null || ! $exigencia->esAplicable()) {
            return null;
        }

        return new MedidaExigible(
            requisitoId: $medida['id'],
            codigo: $medida['codigo'],
            titulo: $medida['titulo'],
            exigencia: $exigencia,
            origen: $origen,
            dimensionModuladora: $dimension,
        );
    }

    /**
     * Paso 4. Un perfil endurece, nunca rebaja: si el perfil fija una exigencia
     * menor que la que impone el RD para esa categoría, manda el RD. Ante un
     * auditor, la lectura conservadora es la única defendible.
     *
     * @param  array{id: int, codigo: string, titulo: string, dimension: ?Dimension, celdas: array<string, Exigencia>}  $medida
     */
    private function aplicarPerfil(?MedidaExigible $exigible, array $medida, ?Exigencia $delPerfil): ?MedidaExigible
    {
        if ($delPerfil === null || ! $delPerfil->esAplicable()) {
            return $exigible;
        }

        if ($exigible === null) {
            return new MedidaExigible(
                requisitoId: $medida['id'],
                codigo: $medida['codigo'],
                titulo: $medida['titulo'],
                exigencia: $delPerfil,
                origen: OrigenExigencia::Perfil,
                dimensionModuladora: $medida['dimension'],
            );
        }

        if ($delPerfil->peso() <= $exigible->exigencia->peso()) {
            return $exigible;
        }

        return $exigible->con($delPerfil, OrigenExigencia::Perfil);
    }

    /**
     * La matriz de aplicabilidad del marco, agrupada por requisito.
     *
     * Un solo viaje a la base: son unas 230 filas para el ENS completo (92
     * requisitos × 3 categorías) y resolverlas en PHP es más barato y más claro
     * que tres consultas condicionales.
     *
     * Filtra por tener filas en `aplicabilidad_ens`, NO por `tipo`: los nodos de
     * agrupación (`org`, `op`, `op.acc`) también son `tipo: medida` en el
     * catálogo, y lo que distingue a una medida real es tener matriz.
     *
     * @return array<int, array{id: int, codigo: string, titulo: string, dimension: ?Dimension, celdas: array<string, Exigencia>}>
     */
    private function matrizDe(Marco $marco): array
    {
        $filas = DB::table('requisitos')
            ->join('aplicabilidad_ens', 'aplicabilidad_ens.requisito_id', '=', 'requisitos.id')
            ->where('requisitos.marco_id', $marco->id)
            ->where('requisitos.vigente', true)
            ->orderBy('requisitos.orden')
            ->orderBy('requisitos.codigo')
            ->get([
                'requisitos.id',
                'requisitos.codigo',
                'requisitos.titulo',
                'aplicabilidad_ens.categoria',
                'aplicabilidad_ens.exigencia',
                'aplicabilidad_ens.dimension_moduladora',
            ]);

        $matriz = [];

        foreach ($filas as $fila) {
            $id = (int) $fila->id;

            $matriz[$id] ??= [
                'id' => $id,
                'codigo' => (string) $fila->codigo,
                'titulo' => (string) $fila->titulo,
                'dimension' => null,
                'celdas' => [],
            ];

            $matriz[$id]['celdas'][(string) $fila->categoria] = Exigencia::desde((string) $fila->exigencia);

            // La modulación es propiedad de la medida, no de la celda: las tres
            // filas comparten `dimension_moduladora`, y un test del catálogo lo
            // verifica. Basta con quedarse con la primera que venga informada.
            if ($matriz[$id]['dimension'] === null && $fila->dimension_moduladora !== null) {
                $matriz[$id]['dimension'] = Dimension::from((string) $fila->dimension_moduladora);
            }
        }

        return $matriz;
    }

    /**
     * Requisitos del perfil con la exigencia que el propio perfil fija, si la
     * fija. Pivot a `null` significa "la que dicte la categoría".
     *
     * @return array<int, ?Exigencia>
     */
    private function exigenciasDelPerfil(PerfilCumplimiento $perfil): array
    {
        $exigencias = [];

        $filas = DB::table('perfil_requisitos')
            ->where('perfil_id', $perfil->id)
            ->get(['requisito_id', 'exigencia']);

        foreach ($filas as $fila) {
            $exigencias[(int) $fila->requisito_id] = $fila->exigencia === null
                ? null
                : Exigencia::desde((string) $fila->exigencia);
        }

        return $exigencias;
    }
}
