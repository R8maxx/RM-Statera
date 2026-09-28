<?php

declare(strict_types=1);

namespace App\Domain\Copia;

use App\Domain\Copia\Excepciones\CopiaInvalida;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Restaura una copia en una base aparte y comprueba que es lo que se copió.
 *
 * Es la mitad del § 6 que se suele quedar en promesa: «restauración probada».
 * Los pasos son los que haría alguien el día que hiciera falta: descargar,
 * comprobar la huella, descifrar, restaurar y mirar. La diferencia es que aquí
 * se hacen cada semana, con la copia de verdad, y el resultado se guarda al lado
 * de la copia.
 */
final readonly class VerificarCopia
{
    public const PREFIJO = 'verificaciones';

    /**
     * Qué tabla guarda qué ficheros, y en qué disco.
     *
     * Las versiones de documento, sólo las emitidas: el borrador se regenera y
     * se borra, y el que nombraba la base al volcarse puede haberse ido ya. Lo
     * que hay que poder devolver es lo que se entregó.
     *
     * @var array<string, array{disco: string, donde: string}>
     */
    private const FICHEROS = [
        'evidencias' => ['disco' => 'evidencias', 'donde' => 'ruta is not null and hash_sha256 is not null'],
        'adjuntos' => ['disco' => 'adjuntos', 'donde' => 'ruta is not null and hash_sha256 is not null'],
        'documento_versiones' => ['disco' => 'documentos', 'donde' => 'numero is not null and ruta is not null and hash_sha256 is not null'],
    ];

    public function __construct(
        private VolcadoDeBase $volcado,
        private CifradoDeCopias $cifrado,
    ) {}

    public function __invoke(?string $nombre = null): ResultadoVerificacion
    {
        $disco = Storage::disk(config('copias.disco'));
        $nombre ??= HacerCopia::copias($disco)[0] ?? throw CopiaInvalida::ningunaCopia();

        $manifiesto = Manifiesto::desdeArray((array) json_decode(
            (string) $disco->get(HacerCopia::ruta($nombre, 'manifiesto.json')),
            true,
        ));

        $temporal = storage_path('app/copias/'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($temporal, 0700);

        try {
            $cifrado = "{$temporal}/base.cifrada";
            $claro = "{$temporal}/base.dump";

            $this->descargar($disco, HacerCopia::ruta($nombre, 'base.cifrada'), $cifrado);

            $huella = (string) hash_file('sha256', $cifrado);

            if (! hash_equals($manifiesto->huella, $huella)) {
                throw CopiaInvalida::huellaDistinta($manifiesto->huella, $huella);
            }

            $this->cifrado->descifrar($cifrado, $claro);
            $restauradas = $this->volcado->restaurar($claro);
        } finally {
            File::deleteDirectory($temporal);
        }

        [$comprobados, $ausentes, $distintos] = $this->comprobarFicheros($disco);

        $resultado = new ResultadoVerificacion(
            manifiesto: $manifiesto,
            verificada_en: Carbon::now(),
            tablas: count($manifiesto->recuentos),
            tablasDistintas: $this->compararTablas($manifiesto->recuentos, $restauradas),
            ficherosComprobados: $comprobados,
            ficherosAusentes: $ausentes,
            ficherosDistintos: $distintos,
        );

        $disco->put(
            self::PREFIJO."/{$resultado->verificada_en->format('Y-m-d\THis')}.json",
            (string) json_encode($resultado->aArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        return $resultado;
    }

    /**
     * @param  array<string, int>  $esperadas
     * @param  array<string, int>  $restauradas
     * @return array<string, array{esperadas: int|null, restauradas: int|null}>
     */
    private function compararTablas(array $esperadas, array $restauradas): array
    {
        $distintas = [];

        foreach (array_unique([...array_keys($esperadas), ...array_keys($restauradas)]) as $tabla) {
            $antes = $esperadas[$tabla] ?? null;
            $despues = $restauradas[$tabla] ?? null;

            if ($antes !== $despues) {
                $distintas[$tabla] = ['esperadas' => $antes, 'restauradas' => $despues];
            }
        }

        ksort($distintas);

        return $distintas;
    }

    /**
     * Cada fichero que nombra la base restaurada, contra su copia en el espejo.
     *
     * Se lee de la base **restaurada** y no de la viva: lo que se comprueba es
     * que esta copia, sola, basta para devolverlo todo.
     *
     * @return array{0: int, 1: list<string>, 2: list<string>}
     */
    private function comprobarFicheros(Filesystem $disco): array
    {
        $restaurada = DB::connection(config('copias.conexion_verificacion'));
        $comprobados = 0;
        $ausentes = [];
        $distintos = [];

        foreach (self::FICHEROS as $tabla => ['disco' => $origen, 'donde' => $donde]) {
            $filas = $restaurada->table($tabla)->whereRaw($donde)->orderBy('id')->cursor();

            foreach ($filas as $fila) {
                $comprobados++;
                $enCopia = EspejoDeObjetos::rutaEnCopia($origen, (string) $fila->ruta);
                $etiqueta = "{$tabla} #{$fila->id} ({$enCopia})";

                if (! $disco->exists($enCopia)) {
                    $ausentes[] = $etiqueta;

                    continue;
                }

                if (! hash_equals((string) $fila->hash_sha256, $this->huellaDe($disco, $enCopia))) {
                    $distintos[] = $etiqueta;
                }
            }
        }

        return [$comprobados, $ausentes, $distintos];
    }

    private function huellaDe(Filesystem $disco, string $ruta): string
    {
        $flujo = $disco->readStream($ruta);
        $contexto = hash_init('sha256');

        try {
            hash_update_stream($contexto, $flujo);
        } finally {
            if (is_resource($flujo)) {
                fclose($flujo);
            }
        }

        return hash_final($contexto);
    }

    private function descargar(Filesystem $disco, string $ruta, string $destino): void
    {
        $flujo = $disco->readStream($ruta);
        $salida = fopen($destino, 'wb');

        try {
            stream_copy_to_stream($flujo, $salida);
        } finally {
            if (is_resource($flujo)) {
                fclose($flujo);
            }
            fclose($salida);
        }
    }
}
