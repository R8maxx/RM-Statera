<?php

declare(strict_types=1);

namespace App\Domain\Proveedor;

use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\RegistrarEvidencia;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Proveedor\Models\ProveedorCertificacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra lo que acredita un proveedor y, si llega, el certificado en sí.
 *
 * **El fichero entra como evidencia, no como adjunto**, porque es lo que el
 * módulo decidió desde el principio (`proveedor_certificaciones.evidencia_id`):
 * una evidencia es donde viven los ficheros con caducidad, lleva su SHA-256 y el
 * Object Lock del bucket, y además puede probar A.5.19. Se sube aquí para no
 * obligar a ir a «Evidencias», dar de alta el fichero y volver a buscarlo en un
 * desplegable.
 *
 * - **La caducidad es la del certificado**, y la obtención su emisión (o hoy):
 *   así la evidencia caduca el mismo día y el aviso diario lo cuenta una vez
 *   por cada lado, como ya pasaba al vincular una a mano.
 * - **O fichero o evidencia existente, no las dos cosas**: lo rechaza el
 *   `FormRequest`, porque con las dos no se sabe cuál es la prueba.
 * - Si la inserción falla después de subir, el objeto se queda en el bucket:
 *   con Object Lock no se puede borrar, y es la misma situación que da de baja
 *   una evidencia (`RegistrarEvidencia`, decisión 3).
 */
final readonly class RegistrarCertificacion
{
    public function __construct(private RegistrarEvidencia $registrarEvidencia) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function __invoke(Proveedor $proveedor, array $datos, ?UploadedFile $fichero = null): ProveedorCertificacion
    {
        return DB::transaction(function () use ($proveedor, $datos, $fichero): ProveedorCertificacion {
            $certificacion = $proveedor->certificaciones()->make($datos);

            if ($fichero !== null) {
                $caduca = isset($datos['caduca_en']) ? Carbon::parse((string) $datos['caduca_en']) : null;
                // Sin emisión se toma hoy, salvo que ya caducara: la base exige
                // que la caducidad no sea anterior a la obtención.
                $obtencion = isset($datos['emitida_en'])
                    ? Carbon::parse((string) $datos['emitida_en'])
                    : ($caduca !== null && $caduca->isPast() ? $caduca : Carbon::today());

                $evidencia = $this->registrarEvidencia->crear([
                    'titulo' => "Certificado {$certificacion->etiqueta()} · {$proveedor->nombre}",
                    'tipo' => TipoEvidencia::Certificado->value,
                    'descripcion' => isset($datos['entidad_emisora']) ? "Emitido por {$datos['entidad_emisora']}." : null,
                    'fecha_obtencion' => $obtencion->toDateString(),
                    'fecha_caducidad' => $caduca?->toDateString(),
                    'responsable_id' => $proveedor->responsable_id,
                ], $fichero);

                $certificacion->evidencia_id = $evidencia->id;
            }

            $certificacion->save();

            return $certificacion;
        });
    }
}
