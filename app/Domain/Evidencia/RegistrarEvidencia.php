<?php

declare(strict_types=1);

namespace App\Domain\Evidencia;

use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Da de alta una evidencia y deja su fichero en el almacén.
 *
 * Tres decisiones que no son de comodidad:
 *
 * 1. **El SHA-256 se calcula del fichero temporal, antes de subirlo.** Calcularlo
 *    después obligaría a descargarlo del bucket para poder firmarlo, y sobre
 *    todo mediría lo que hay en el bucket en vez de lo que se recibió.
 *
 * 2. **El fichero de una evidencia no se reemplaza.** Se da de alta otra. El
 *    bucket lleva Object Lock justamente para que nadie pueda cambiar la prueba
 *    bajo un registro que ya se le enseñó a un auditor; permitir el reemplazo en
 *    la aplicación sería dejar abierta la puerta que la infraestructura cierra.
 *
 * 3. **Borrar la evidencia no borra el objeto del bucket.** No se puede —Object
 *    Lock en modo compliance— y no se debe: la traza de auditoría registra la
 *    baja de la fila, y el fichero sigue ahí por si hay que responder de él.
 */
final class RegistrarEvidencia
{
    private const DISCO = 'evidencias';

    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly VincularEvidencia $vinculos,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function crear(array $atributos, ?UploadedFile $fichero = null): Evidencia
    {
        $atributos = $this->conCaducidadDerivada($atributos);

        if ($fichero !== null) {
            $atributos = [...$atributos, ...$this->guardar($fichero)];
            $atributos['url_externa'] = null;
        }

        return Evidencia::query()->create($atributos);
    }

    /**
     * Da de alta la sustituta de una evidencia, con sus mismos vínculos.
     *
     * **Renovar es registrar otra, no editar ésta**: el fichero no se reemplaza
     * (decisión 2), y la anterior probó lo que probó durante su periodo. Se queda
     * con sus vínculos y apuntando a la nueva, y eso es lo que la saca de
     * `caducadas()` y `porCaducar()`: renovar a tiempo apaga el aviso sin borrar
     * la historia.
     *
     * Los vínculos se copian con su nota, porque el motivo por el que la prueba
     * cubría cada requisito no cambia al volver a obtenerla. Todo en una
     * transacción: una renovación a medias dejaría la vieja sin sustituta o la
     * nueva sin nada que probar.
     *
     * @param  array<string, mixed>  $atributos
     */
    public function renovar(Evidencia $anterior, array $atributos, ?UploadedFile $fichero = null, ?User $usuario = null): Evidencia
    {
        return DB::transaction(function () use ($anterior, $atributos, $fichero, $usuario): Evidencia {
            $nueva = $this->crear($atributos, $fichero);

            foreach ($anterior->implantaciones()->get() as $implantacion) {
                $this->vinculos->vincular(
                    $nueva,
                    $implantacion,
                    $usuario,
                    $implantacion->getRelationValue('pivot')?->getAttribute('nota'),
                );
            }

            $anterior->update(['renovada_por_id' => $nueva->id]);

            return $nueva;
        });
    }

    /**
     * Sólo metadatos: título, tipo, fechas, responsable, periodicidad.
     *
     * El fichero, su ruta y su huella no se tocan — ver la decisión 2 de arriba.
     *
     * @param  array<string, mixed>  $atributos
     */
    public function actualizar(Evidencia $evidencia, array $atributos): Evidencia
    {
        unset(
            $atributos['disco'],
            $atributos['ruta'],
            $atributos['nombre_fichero'],
            $atributos['mime'],
            $atributos['tamano'],
            $atributos['hash_sha256'],
        );

        // Una evidencia de fichero no puede pasar a ser de URL ni al revés: la
        // restricción de la base exige exactamente uno de los dos.
        if ($evidencia->esFichero()) {
            unset($atributos['url_externa']);
        }

        $evidencia->update($this->conCaducidadDerivada($atributos, $evidencia));

        return $evidencia->refresh();
    }

    /**
     * Si hay periodicidad y no hay fecha de caducidad, la fecha se deriva.
     *
     * Es el mismo criterio que con la categoría del sistema: lo que se puede
     * calcular no se pregunta dos veces. Una fecha escrita a mano manda sobre la
     * derivada, porque a veces el proveedor pone la suya.
     *
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function conCaducidadDerivada(array $atributos, ?Evidencia $evidencia = null): array
    {
        if (! empty($atributos['fecha_caducidad'])) {
            return $atributos;
        }

        $periodicidad = $atributos['periodicidad_renovacion'] ?? $evidencia?->periodicidad_renovacion;

        if ($periodicidad === null) {
            return $atributos;
        }

        $periodicidad = $periodicidad instanceof PeriodicidadRenovacion
            ? $periodicidad
            : PeriodicidadRenovacion::from((string) $periodicidad);

        $obtencion = $atributos['fecha_obtencion'] ?? $evidencia?->fecha_obtencion;

        if ($obtencion === null) {
            return $atributos;
        }

        $atributos['fecha_caducidad'] = $periodicidad
            ->caducidadDesde(Carbon::parse($obtencion))
            ->toDateString();

        return $atributos;
    }

    /**
     * Sube el fichero y devuelve lo que hay que guardar de él.
     *
     * La ruta lleva la organización delante para que un listado del bucket sea
     * legible y para que una política de S3 pueda acotarse por prefijo; el
     * aislamiento de verdad sigue estando en las tres capas de la base, no en la
     * forma del nombre.
     *
     * @return array{disco: string, ruta: string, nombre_fichero: string, mime: ?string, tamano: int, hash_sha256: string}
     */
    private function guardar(UploadedFile $fichero): array
    {
        $huella = hash_file('sha256', $fichero->getRealPath());

        $extension = $fichero->getClientOriginalExtension();
        $nombre = Str::ulid()->toBase32().($extension === '' ? '' : ".{$extension}");
        $ruta = sprintf('%d/%s/%s', $this->contexto->idObligatorio(), Carbon::now()->format('Y'), $nombre);

        Storage::disk(self::DISCO)->putFileAs(dirname($ruta), $fichero, basename($ruta));

        return [
            'disco' => self::DISCO,
            'ruta' => $ruta,
            // El nombre con el que llegó, para poder devolvérselo al descargar:
            // el del bucket es un ULID y no le dice nada a nadie.
            'nombre_fichero' => $fichero->getClientOriginalName(),
            'mime' => $fichero->getClientMimeType(),
            'tamano' => (int) $fichero->getSize(),
            'hash_sha256' => (string) $huella,
        ];
    }
}
