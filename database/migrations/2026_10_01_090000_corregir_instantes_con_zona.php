<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las seis columnas `timestamptz` guardaban un instante dos horas desplazado
 * (punto 33).
 *
 * La aplicación trabaja con la hora de Madrid y Laravel escribe las fechas sin
 * desfase —«2026-09-28 16:00:00»—. En las 192 columnas `timestamp` eso es justo
 * lo que se quiere: se escribe hora de Madrid y se lee hora de Madrid. En una
 * `timestamptz`, en cambio, PostgreSQL interpreta ese texto en la zona de la
 * **sesión**, que era UTC, y guardaba las 16:00 UTC, que son las 18:00 en
 * Madrid. Leída por Eloquent, `emitida_en` quedaba dos horas por delante de
 * cualquier `created_at`, y `RegistrarDeclaracion` tenía que comparar en SQL
 * para no rechazar la firma correcta.
 *
 * Lo que lo arregla no es esta migración, es `'timezone' => 'Europe/Madrid'`
 * en `config/database.php`: con la sesión en la misma zona que la aplicación,
 * el texto sin desfase se interpreta como lo que es. Esto corrige lo que ya
 * estaba escrito: `col AT TIME ZONE 'UTC'` recupera la hora de reloj que mandó
 * Laravel, y `AT TIME ZONE 'Europe/Madrid'` la convierte en el instante que
 * quería decir, con el horario de verano de cada fecha y no con el de hoy. No
 * depende de la zona de la sesión que corre la migración.
 *
 * **`documento_versiones` exige apagar su trigger**, y no es un atajo. Una
 * versión emitida es inmutable y el trigger no tiene puerta de mantenimiento,
 * a propósito. Pero aquí no se reescribe lo que alguien firmó: el instante
 * era el mismo, y lo que estaba mal era su representación. El PDF entregado no
 * se toca. Sólo el dueño de la tabla puede apagar un trigger, y desde el punto
 * 32 el dueño es el migrador y no la aplicación. Se apaga y se enciende dentro
 * de la transacción de la migración, así que ninguna otra escritura lo ve
 * apagado.
 *
 * Las filas se recorren con `comoMantenimiento()`: sin él, RLS deniega por
 * defecto y los `UPDATE` afectan a cero filas sin fallar.
 *
 * **El `down()` sólo tiene sentido junto con volver a quitar la zona de la
 * conexión.** Con la sesión en Madrid, deshacer esto vuelve a desplazar los
 * datos.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const COLUMNAS = [
        'documento_versiones' => ['emitida_en'],
        'documento_cuerpos' => ['generado_en', 'editado_en'],
        'documento_lecturas' => ['acusada_en'],
        'subtareas' => ['hecha_en'],
        'pasos_persona' => ['hecho_en'],
    ];

    public function up(): void
    {
        $this->recolocar(desde: 'UTC', hacia: 'Europe/Madrid');
    }

    public function down(): void
    {
        $this->recolocar(desde: 'Europe/Madrid', hacia: 'UTC');
    }

    private function recolocar(string $desde, string $hacia): void
    {
        app(ContextoOrganizacion::class)->comoMantenimiento(function () use ($desde, $hacia): void {
            DB::statement('ALTER TABLE documento_versiones DISABLE TRIGGER documento_versiones_inmutables');

            foreach (self::COLUMNAS as $tabla => $columnas) {
                $asignaciones = implode(', ', array_map(
                    static fn (string $columna): string => "{$columna} = ({$columna} AT TIME ZONE '{$desde}') AT TIME ZONE '{$hacia}'",
                    $columnas,
                ));

                DB::statement("UPDATE {$tabla} SET {$asignaciones}");
            }

            DB::statement('ALTER TABLE documento_versiones ENABLE TRIGGER documento_versiones_inmutables');
        });
    }
};
