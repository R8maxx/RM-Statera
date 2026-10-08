<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Console;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\EstadoExportacion;
use App\Domain\Plataforma\Exportacion\ExportarOrganizacion;
use App\Domain\Plataforma\Exportacion\ModelosDelCliente;
use App\Domain\Plataforma\Models\ExportacionOrganizacion;
use App\Domain\Plataforma\TrazaPlataforma;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Borra de verdad a un cliente que se fue (punto 57). **Sólo por consola.**
 *
 * Tres frenos antes de tocar nada:
 *
 * 1. **Lleva al menos noventa días de baja.** Lo comprueba la función SQL
 *    `purgar_organizacion()`, que es la única que puede borrar la fila: el plazo
 *    no lo decide este comando.
 * 2. **Hay una exportación generada después de la baja**, o se dice
 *    explícitamente `--sin-exportacion`. Lo que se borra no vuelve.
 * 3. **Se teclea su CIF** (o su nombre, si no tiene).
 *
 * `--dry-run` cuenta lo que se borraría y no toca nada.
 *
 * **Los ficheros**: se borran los de `adjuntos` y los borradores de
 * `documentos`. Las evidencias y los documentos emitidos pueden estar bajo
 * Object Lock: se intenta, y lo que el almacenamiento retiene se cuenta y se
 * dice, porque caducará con el ciclo de vida del bucket. Las copias dejan de
 * contener al cliente cuando caducan, a los `COPIAS_CONSERVAR_DIAS` días.
 */
final class PurgarOrganizacionCommand extends Command
{
    protected $signature = 'organizaciones:purgar
        {organizacion : Id de la organización, que tiene que llevar noventa días de baja}
        {--dry-run : Cuenta lo que se borraría, sin borrar nada}
        {--sin-exportacion : Purga aunque no haya una exportación generada después de la baja}';

    protected $description = 'Borra definitivamente todos los datos de una organización dada de baja';

    public function handle(ContextoOrganizacion $contexto, ModelosDelCliente $modelos, TrazaPlataforma $traza): int
    {
        $organizacion = Organizacion::query()->find((int) $this->argument('organizacion'));

        if ($organizacion === null) {
            $this->components->error('Esa organización no existe.');

            return self::FAILURE;
        }

        if ($organizacion->baja_en === null || $organizacion->baja_en->gt(now()->subDays(90))) {
            $this->components->error('Sólo se purga una organización que lleve al menos noventa días de baja.');

            return self::FAILURE;
        }

        $recuentos = $contexto->paraOrganizacion($organizacion, function () use ($modelos): array {
            $recuentos = [];

            foreach ($modelos->todos() as $clase) {
                $total = $clase::query()->count();

                if ($total > 0) {
                    $recuentos[class_basename($clase)] = $total;
                }
            }

            return $recuentos;
        });
        $ficheros = $this->ficheros($organizacion);

        $this->components->info("{$organizacion->nombre} (CIF {$organizacion->cif}), de baja desde el {$organizacion->baja_en->format('d/m/Y')}.");
        foreach ($recuentos as $modelo => $total) {
            $this->components->twoColumnDetail($modelo, (string) $total);
        }
        $this->components->twoColumnDetail('Ficheros', (string) count($ficheros));

        if ($this->option('dry-run')) {
            $this->components->info('Simulación: no se ha borrado nada.');

            return self::SUCCESS;
        }

        $exportada = ExportacionOrganizacion::query()
            ->where('organizacion_afectada_id', $organizacion->id)
            ->where('estado', EstadoExportacion::Lista->value)
            ->where('generada_en', '>', $organizacion->baja_en)
            ->exists();

        if (! $exportada && ! $this->option('sin-exportacion')) {
            $this->components->error('No hay una exportación generada después de la baja. Expórtala desde su ficha, o repite con --sin-exportacion si de verdad no hace falta.');

            return self::FAILURE;
        }

        $clave = $organizacion->cif ?: $organizacion->nombre;

        if ($this->ask("Esto no se puede deshacer. Escribe «{$clave}» para purgar") !== $clave) {
            $this->components->warn('No coincide: no se ha borrado nada.');

            return self::FAILURE;
        }

        [$borrados, $retenidos] = $this->borrarFicheros($ficheros);

        /** @var object{resultado: string} $fila */
        $fila = DB::selectOne('SELECT purgar_organizacion(?) AS resultado', [$organizacion->id]);
        $resultado = (array) json_decode($fila->resultado, true);

        // Sin `organizacion_afectada_id`: la fila ya no existe. Lo que fue
        // queda en el detalle.
        $traza->registrar(AccionPlataforma::OrganizacionPurgada, null, [
            'nombre' => $organizacion->nombre,
            'cif' => $organizacion->cif,
            'recuentos' => $recuentos,
            'ficheros_borrados' => $borrados,
            'ficheros_retenidos' => $retenidos,
            ...$resultado,
        ]);

        $this->components->info("Purgada. {$borrados} ficheros borrados.");

        if ($retenidos > 0) {
            $this->components->warn("{$retenidos} ficheros los retiene el almacenamiento (Object Lock): caducarán con el ciclo de vida del bucket.");
        }

        $this->components->warn('Las copias de seguridad dejan de contenerla cuando caducan, a los '.config('copias.conservar_dias').' días.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function ficheros(Organizacion $organizacion): array
    {
        $prefijos = [
            ['evidencias', "{$organizacion->id}/"],
            ['documentos', "{$organizacion->id}/"],
            ['adjuntos', "{$organizacion->id}/"],
            ['adjuntos', "marca/{$organizacion->id}/"],
            ['adjuntos', "avatares/{$organizacion->id}/"],
            [ExportarOrganizacion::DISCO, "exportaciones/{$organizacion->id}/"],
        ];

        $ficheros = [];

        foreach ($prefijos as [$disco, $prefijo]) {
            foreach (Storage::disk($disco)->allFiles($prefijo) as $ruta) {
                $ficheros[] = [$disco, $ruta];
            }
        }

        return $ficheros;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $ficheros
     * @return array{0: int, 1: int}
     */
    private function borrarFicheros(array $ficheros): array
    {
        $borrados = 0;
        $retenidos = 0;

        foreach ($ficheros as [$disco, $ruta]) {
            try {
                Storage::disk($disco)->delete($ruta) ? $borrados++ : $retenidos++;
            } catch (Throwable) {
                $retenidos++;
            }
        }

        return [$borrados, $retenidos];
    }
}
