<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad;

/**
 * El vector CVSS 3.x leído en castellano, para la ficha.
 *
 * **Sólo para leerlo, no para puntuar.** La puntuación la escribe quien la
 * registra y la severidad sale de ella (`Severidad::desdeCvss()`); esto no
 * recalcula nada. Un vector que no sea de la v3 —uno de la v4, uno mal
 * escrito— devuelve `null` y la ficha enseña la cadena tal cual.
 */
final class VectorCvss
{
    /** @var array<string, array{0: string, 1: array<string, string>}> */
    private const METRICAS = [
        'AV' => ['Vector de ataque', ['N' => 'Red', 'A' => 'Red adyacente', 'L' => 'Local', 'P' => 'Física']],
        'AC' => ['Complejidad', ['L' => 'Baja', 'H' => 'Alta']],
        'PR' => ['Privilegios', ['N' => 'Ninguno', 'L' => 'Bajos', 'H' => 'Altos']],
        'UI' => ['Interacción', ['N' => 'Ninguna', 'R' => 'Necesaria']],
        'S' => ['Alcance', ['U' => 'Sin cambio', 'C' => 'Cambia']],
        'C' => ['Confidencialidad', ['N' => 'Ninguna', 'L' => 'Baja', 'H' => 'Alta']],
        'I' => ['Integridad', ['N' => 'Ninguna', 'L' => 'Baja', 'H' => 'Alta']],
        'A' => ['Disponibilidad', ['N' => 'Ninguna', 'L' => 'Baja', 'H' => 'Alta']],
    ];

    /** @return list<array{metrica: string, valor: string}>|null */
    public function desglose(?string $vector): ?array
    {
        if ($vector === null || trim($vector) === '') {
            return null;
        }

        $partes = explode('/', strtoupper(trim($vector)));

        if (str_starts_with($partes[0], 'CVSS:')) {
            if (! str_starts_with($partes[0], 'CVSS:3.')) {
                return null;
            }

            array_shift($partes);
        }

        $valores = [];

        foreach ($partes as $parte) {
            [$clave, $valor] = array_pad(explode(':', $parte, 2), 2, '');

            if (! isset(self::METRICAS[$clave][1][$valor]) || isset($valores[$clave])) {
                return null;
            }

            $valores[$clave] = $valor;
        }

        if (count($valores) !== count(self::METRICAS)) {
            return null;
        }

        return array_map(
            static fn (string $clave): array => [
                'metrica' => self::METRICAS[$clave][0],
                'valor' => self::METRICAS[$clave][1][$valores[$clave]],
            ],
            array_keys(self::METRICAS),
        );
    }
}
