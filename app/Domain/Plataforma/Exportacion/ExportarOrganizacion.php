<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Exportacion;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\EstadoExportacion;
use App\Domain\Plataforma\Models\ExportacionOrganizacion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Empaqueta todos los datos de un cliente en un ZIP (punto 56).
 *
 * **Corre dentro del contexto del cliente** (el job lleva
 * `ConContextoDeOrganizacion`), así que las tres capas siguen aplicando: lo que
 * no es suyo no está en la consulta, aunque alguien olvidara un `where`.
 *
 * Lo que lleva:
 *
 * - **un NDJSON por modelo con `organizacion_id`**, descubiertos por
 *   `ModelosDelCliente`. **Con Eloquent y no con SQL en bruto**, para que los
 *   datos personales cifrados (punto 35) salgan en claro y `$hidden` se
 *   respete;
 * - **sus cuentas**, sin contraseña ni secretos;
 * - **sus ficheros** de `evidencias`, `documentos` y `adjuntos`, bajo su
 *   prefijo de organización;
 * - **un `manifiesto.json`** con la fecha, el esquema, los recuentos y la
 *   huella SHA-256 de cada fichero, para que quien lo recibe pueda comprobar
 *   que está entero.
 */
final class ExportarOrganizacion
{
    /** Disco donde se deja el ZIP: el único sin Object Lock, porque caduca. */
    public const DISCO = 'adjuntos';

    /** Las cuentas sin lo que nadie debe llevarse. */
    private const CAMPOS_DE_CUENTA = ['id', 'name', 'email', 'invitada_en', 'activada_en', 'desactivada_en', 'motivo_desactivacion', 'acceso_hasta', 'ultimo_acceso_en', 'created_at'];

    public function __construct(private readonly ModelosDelCliente $modelos) {}

    public function __invoke(ExportacionOrganizacion $exportacion): void
    {
        $organizacion = Organizacion::query()->findOrFail($exportacion->organizacion_afectada_id);
        $temporal = tempnam(sys_get_temp_dir(), 'exportacion-');

        if ($temporal === false) {
            throw new RuntimeException('No se pudo crear el fichero temporal de la exportación.');
        }

        $zip = new ZipArchive;
        $zip->open($temporal, ZipArchive::OVERWRITE);

        $recuentos = [];
        $huellas = [];

        // La propia organización: es la raíz del tenant y no tiene
        // `organizacion_id`, así que el descubrimiento no la encuentra.
        $ficha = (string) json_encode($organizacion->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $zip->addFromString('datos/organizacion.json', $ficha);
        $huellas['datos/organizacion.json'] = hash('sha256', $ficha);

        foreach ($this->modelos->todos() as $clase) {
            $lineas = [];

            foreach ($clase::query()->cursor() as $fila) {
                $lineas[] = json_encode($fila->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $nombre = 'datos/'.Str::snake(class_basename($clase)).'.ndjson';
            $contenido = implode("\n", $lineas);
            $zip->addFromString($nombre, $contenido);
            $recuentos[class_basename($clase)] = count($lineas);
            $huellas[$nombre] = hash('sha256', $contenido);
        }

        $lineasDeCuentas = User::query()
            ->where('organizacion_id', $organizacion->id)
            ->get(self::CAMPOS_DE_CUENTA)
            ->map(static fn (User $cuenta): string => (string) json_encode([...$cuenta->only(self::CAMPOS_DE_CUENTA), 'rol' => $cuenta->rol()?->value], JSON_UNESCAPED_UNICODE));
        $cuentas = $lineasDeCuentas->implode("\n");
        $zip->addFromString('datos/cuentas.ndjson', $cuentas);
        $recuentos['Cuentas'] = $lineasDeCuentas->count();
        $huellas['datos/cuentas.ndjson'] = hash('sha256', $cuentas);

        $recuentos['Ficheros'] = 0;

        foreach ($this->ficheros($organizacion) as [$disco, $ruta]) {
            $contenido = (string) Storage::disk($disco)->get($ruta);
            $nombre = "ficheros/{$disco}/{$ruta}";
            $zip->addFromString($nombre, $contenido);
            $recuentos['Ficheros']++;
            $huellas[$nombre] = hash('sha256', $contenido);
        }

        $zip->addFromString('manifiesto.json', (string) json_encode([
            'organizacion' => ['id' => $organizacion->id, 'nombre' => $organizacion->nombre, 'razon_social' => $organizacion->razon_social, 'cif' => $organizacion->cif],
            'generada_en' => Carbon::now()->toIso8601String(),
            'esquema' => DB::table('migrations')->orderByDesc('id')->value('migration'),
            'recuentos' => $recuentos,
            'huellas' => $huellas,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $zip->close();

        $ruta = "exportaciones/{$organizacion->id}/".Str::ulid().'.zip';
        $flujo = fopen($temporal, 'rb');
        Storage::disk(self::DISCO)->writeStream($ruta, $flujo);

        if (is_resource($flujo)) {
            fclose($flujo);
        }

        $exportacion->forceFill([
            'estado' => EstadoExportacion::Lista->value,
            'ruta' => $ruta,
            'tamano' => (int) filesize($temporal),
            'huella' => hash_file('sha256', $temporal),
            'generada_en' => Carbon::now(),
            'caduca_en' => Carbon::now()->addDays(ExportacionOrganizacion::DIAS_DE_VALIDEZ),
        ])->save();

        @unlink($temporal);
    }

    /**
     * Los ficheros del cliente, por los prefijos que usa cada módulo.
     *
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
        ];

        $ficheros = [];

        foreach ($prefijos as [$disco, $prefijo]) {
            foreach (Storage::disk($disco)->allFiles($prefijo) as $ruta) {
                $ficheros[] = [$disco, $ruta];
            }
        }

        return $ficheros;
    }
}
