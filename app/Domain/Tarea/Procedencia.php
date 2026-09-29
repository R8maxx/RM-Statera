<?php

declare(strict_types=1);

namespace App\Domain\Tarea;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Tarea\Models\Tarea;

/**
 * De dónde sale una tarea, y si eso de donde sale sigue abierto.
 *
 * La ficha de una tarea contestaba «qué hace avanzar» y no «por qué existe»: una
 * acción correctiva no enseñaba su no conformidad en ninguna parte, y sólo las
 * pruebas de continuidad asomaban como un código suelto en «Ficha». Esto reúne
 * los registros de los que la tarea es el trabajo, con lo mínimo para entender
 * el porqué sin salir de la ficha.
 *
 * **El aviso sale de aquí y no de la plantilla.** Una acción correctiva abierta
 * cuya no conformidad ya está cerrada es trabajo que probablemente sobra —o que
 * se hizo por otra vía y nadie lo apuntó—, y es exactamente lo que un auditor
 * encuentra al cruzar las dos listas. Sólo cuenta cuando **todas** las no
 * conformidades de la tarea están cerradas: si una sigue abierta, la acción sigue
 * haciendo falta.
 *
 * Las pruebas de continuidad no disparan el aviso: una prueba «realizada» no dice
 * que lo que falló esté arreglado, que es precisamente lo que hace la tarea.
 *
 * Proveedores y vulnerabilidades tienen su pivote con tareas y todavía no pasan
 * por aquí: entran como una rama más de `registros()` cuando se quiera.
 */
final readonly class Procedencia
{
    /**
     * Los registros de los que sale la tarea.
     *
     * La ruta y el permiso viajan con cada uno para que el controlador decida si
     * pinta el enlace: un enlace a un 403 enseña que la ficha miente.
     *
     * @return list<array{
     *     tipo: string,
     *     tipoEtiqueta: string,
     *     id: int,
     *     codigo: string,
     *     titulo: string,
     *     estado: array{valor: string, etiqueta: string, tono: string, icono: string},
     *     contexto: ?string,
     *     fecha: ?string,
     *     detalles: list<array{etiqueta: string, texto: string}>,
     *     ruta: string,
     *     permiso: Permiso,
     * }>
     */
    public static function registros(Tarea $tarea): array
    {
        $tarea->loadMissing(['noConformidades', 'pruebasContinuidad']);

        $noConformidades = $tarea->noConformidades
            ->map(static fn (NoConformidad $nc): array => [
                'tipo' => 'no_conformidad',
                'tipoEtiqueta' => 'No conformidad',
                'id' => $nc->id,
                'codigo' => $nc->codigo,
                'titulo' => $nc->descripcion,
                'estado' => [
                    'valor' => $nc->estado->value,
                    'etiqueta' => $nc->estado->etiqueta(),
                    'tono' => $nc->estado->tono(),
                    'icono' => $nc->estado->icono(),
                ],
                'contexto' => $nc->origen->etiqueta(),
                'fecha' => $nc->fecha_deteccion->toDateString(),
                'detalles' => self::detalles([
                    'Causa raíz' => $nc->analisis_causa_raiz,
                    'Verificación' => $nc->resultado_verificacion,
                ]),
                'ruta' => 'no-conformidades.show',
                'permiso' => Permiso::NoConformidadesVer,
            ]);

        $pruebas = $tarea->pruebasContinuidad
            ->map(static fn (PruebaContinuidad $prueba): array => [
                'tipo' => 'prueba_continuidad',
                'tipoEtiqueta' => 'Prueba de continuidad',
                'id' => $prueba->id,
                'codigo' => $prueba->codigo,
                'titulo' => $prueba->titulo,
                'estado' => [
                    'valor' => $prueba->estado->value,
                    'etiqueta' => $prueba->estado->etiqueta(),
                    'tono' => $prueba->estado->tono(),
                    'icono' => $prueba->estado->icono(),
                ],
                'contexto' => $prueba->resultado?->etiqueta(),
                'fecha' => $prueba->fecha_realizacion?->toDateString(),
                'detalles' => self::detalles(['Conclusiones' => $prueba->conclusiones]),
                'ruta' => 'continuidad.pruebas.show',
                'permiso' => Permiso::ContinuidadVer,
            ]);

        return [...$noConformidades->values()->all(), ...$pruebas->values()->all()];
    }

    /**
     * Las no conformidades ya cerradas de una tarea que sigue abierta, o nada.
     *
     * Devuelve lista vacía en cuanto la tarea está cerrada, no tiene no
     * conformidades o alguna sigue abierta.
     *
     * @return list<array{id: int, codigo: string, estado: string, fecha: ?string}>
     */
    public static function origenCerrado(Tarea $tarea): array
    {
        $tarea->loadMissing('noConformidades');

        $noConformidades = $tarea->noConformidades;

        if ($tarea->estado->esCerrada()
            || $noConformidades->isEmpty()
            || $noConformidades->contains(static fn (NoConformidad $nc): bool => ! $nc->estado->esCerrada())) {
            return [];
        }

        return $noConformidades
            ->map(static fn (NoConformidad $nc): array => [
                'id' => $nc->id,
                'codigo' => $nc->codigo,
                'estado' => $nc->estado->etiqueta(),
                'fecha' => ($nc->fecha_verificacion ?? $nc->fecha_cierre)?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Los pares etiqueta–texto que tienen texto.
     *
     * @param  array<string, ?string>  $campos
     * @return list<array{etiqueta: string, texto: string}>
     */
    private static function detalles(array $campos): array
    {
        $detalles = [];

        foreach ($campos as $etiqueta => $texto) {
            if ($texto !== null && trim($texto) !== '') {
                $detalles[] = ['etiqueta' => $etiqueta, 'texto' => $texto];
            }
        }

        return $detalles;
    }
}
