<?php

declare(strict_types=1);

namespace App\Domain\Copia;

use App\Domain\Copia\Excepciones\CopiaInvalida;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Volcar la base y restaurarla, con los recuentos que las comparan.
 *
 * **Recuentos y volcado salen de la misma instantánea.** Se abre una
 * transacción `REPEATABLE READ`, se exporta su instantánea, se cuentan las filas
 * dentro de ella y se le pasa a `pg_dump --snapshot`. Contar antes o después
 * dejaría colar las escrituras de entre medias, y la verificación daría por
 * mala una copia buena cada vez que alguien trabajara a las dos de la mañana.
 *
 * Todo con el rol de copias: lee todas las organizaciones (BYPASSRLS) y no puede
 * escribir en ninguna.
 */
final class VolcadoDeBase
{
    /**
     * Vuelca la base de la conexión de copias en `$destino`, en formato propio
     * de `pg_dump`.
     *
     * @return array<string, int> Las filas de cada tabla en el instante del volcado.
     */
    public function volcar(string $destino): array
    {
        $conexion = DB::connection(config('copias.conexion'));
        $conexion->beginTransaction();

        try {
            $conexion->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $instantanea = (string) $conexion->selectOne('select pg_export_snapshot() as id')->id;

            $recuentos = $this->contar($conexion);

            $this->ejecutar('pg_dump', $conexion, [
                'pg_dump', '--format=custom', '--no-owner', '--no-privileges',
                '--snapshot='.$instantanea,
                '--file='.$destino,
            ]);

            return $recuentos;
        } finally {
            $conexion->rollBack();
        }
    }

    /**
     * Restaura un volcado en la base de verificación, que se vacía entera antes.
     *
     * `--exit-on-error` y `--single-transaction` porque una restauración a
     * medias es exactamente lo que hay que detectar, no algo que tolerar.
     *
     * @return array<string, int> Las filas de cada tabla una vez restaurada.
     */
    public function restaurar(string $origen): array
    {
        $conexion = DB::connection(config('copias.conexion_verificacion'));

        $conexion->statement('DROP SCHEMA IF EXISTS public CASCADE');
        $conexion->statement('CREATE SCHEMA public');

        $this->ejecutar('pg_restore', $conexion, [
            'pg_restore', '--no-owner', '--no-privileges', '--exit-on-error', '--single-transaction',
            '--dbname='.$conexion->getDatabaseName(),
            $origen,
        ]);

        return $this->contar($conexion);
    }

    /**
     * @return array<string, int>
     */
    private function contar(Connection $conexion): array
    {
        $tablas = $conexion->table('pg_tables')
            ->where('schemaname', 'public')
            ->orderBy('tablename')
            ->pluck('tablename');

        $recuentos = [];

        foreach ($tablas as $tabla) {
            $recuentos[(string) $tabla] = (int) $conexion->table((string) $tabla)->count();
        }

        return $recuentos;
    }

    /**
     * @param  list<string>  $orden
     */
    private function ejecutar(string $nombre, Connection $conexion, array $orden): void
    {
        /** @var array{host: string, port: int|string, username: string, password: string, database: string} $config */
        $config = $conexion->getConfig();

        $resultado = Process::env([
            'PGHOST' => (string) $config['host'],
            'PGPORT' => (string) $config['port'],
            'PGUSER' => (string) $config['username'],
            'PGPASSWORD' => (string) $config['password'],
            'PGDATABASE' => (string) $config['database'],
        ])->timeout(3600)->run($orden);

        if ($resultado->failed()) {
            throw CopiaInvalida::procesoFallido($nombre, $resultado->errorOutput() ?: $resultado->output());
        }
    }
}
