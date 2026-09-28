<?php

declare(strict_types=1);

namespace App\Domain\Copia;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Hace una copia: vuelca la base, la cifra, la sube con su manifiesto,
 * sincroniza los objetos y retira las copias que pasan del plazo.
 *
 * **Primero la base y después los objetos**, y el orden importa. Lo que se
 * sube entre el volcado y el espejo queda en el espejo aunque la base no lo
 * nombre, que no molesta. Al revés, una evidencia subida entre medias estaría
 * en la base y no en la copia, y la verificación la daría por perdida.
 */
final readonly class HacerCopia
{
    public const PREFIJO = 'bases';

    public function __construct(
        private VolcadoDeBase $volcado,
        private CifradoDeCopias $cifrado,
        private EspejoDeObjetos $espejo,
    ) {}

    /**
     * @return array{manifiesto: Manifiesto, objetos: array<string, array{copiados: int, presentes: int}>, retiradas: list<string>}
     */
    public function __invoke(): array
    {
        $hechaEn = Carbon::now();
        $nombre = $hechaEn->format('Y-m-d\THis');
        $disco = $this->disco();
        $temporal = $this->carpetaTemporal();

        try {
            $claro = "{$temporal}/base.dump";
            $cifrado = "{$temporal}/base.cifrada";

            $recuentos = $this->volcado->volcar($claro);
            $this->cifrado->cifrar($claro, $cifrado);
            // El volcado en claro no espera a la limpieza del final.
            File::delete($claro);

            $manifiesto = new Manifiesto(
                nombre: $nombre,
                hecha_en: $hechaEn,
                huella: (string) hash_file('sha256', $cifrado),
                bytes: (int) filesize($cifrado),
                recuentos: $recuentos,
            );

            $flujo = fopen($cifrado, 'rb');

            try {
                $disco->writeStream(self::ruta($nombre, 'base.cifrada'), $flujo);
            } finally {
                if (is_resource($flujo)) {
                    fclose($flujo);
                }
            }

            // El manifiesto va después del fichero: una copia con manifiesto y
            // sin fichero parecería completa.
            $disco->put(self::ruta($nombre, 'manifiesto.json'), (string) json_encode($manifiesto->aArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } finally {
            File::deleteDirectory($temporal);
        }

        $objetos = $this->espejo->sincronizar();
        $retiradas = $this->retirarAntiguas($disco, $nombre);

        return ['manifiesto' => $manifiesto, 'objetos' => $objetos, 'retiradas' => $retiradas];
    }

    public static function ruta(string $nombre, string $fichero): string
    {
        return self::PREFIJO."/{$nombre}/{$fichero}";
    }

    /**
     * Las copias que hay, de la más reciente a la más antigua.
     *
     * Sólo cuentan las que tienen manifiesto: una carpeta sin él es una subida
     * que se cortó.
     *
     * @return list<string>
     */
    public static function copias(Filesystem $disco): array
    {
        $nombres = array_values(array_filter(
            array_map('basename', $disco->directories(self::PREFIJO)),
            static fn (string $nombre): bool => $disco->exists(self::ruta($nombre, 'manifiesto.json')),
        ));

        rsort($nombres);

        return $nombres;
    }

    /**
     * @return list<string>
     */
    private function retirarAntiguas(Filesystem $disco, string $actual): array
    {
        $limite = Carbon::now()->subDays((int) config('copias.conservar_dias'))->format('Y-m-d\THis');
        $retiradas = [];

        foreach (self::copias($disco) as $nombre) {
            if ($nombre === $actual || $nombre >= $limite) {
                continue;
            }

            $disco->deleteDirectory(self::PREFIJO."/{$nombre}");
            $retiradas[] = $nombre;
        }

        return $retiradas;
    }

    private function disco(): Filesystem
    {
        return Storage::disk(config('copias.disco'));
    }

    private function carpetaTemporal(): string
    {
        $ruta = storage_path('app/copias/'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($ruta, 0700);

        return $ruta;
    }
}
