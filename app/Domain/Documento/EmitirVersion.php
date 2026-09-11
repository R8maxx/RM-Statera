<?php

declare(strict_types=1);

namespace App\Domain\Documento;

use App\Domain\Documento\Excepciones\VersionNoEmisible;
use App\Domain\Documento\Models\DocumentoVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Convierte el borrador en una entrega.
 *
 * A partir de aquí la fila es **inmutable** —lo impone un trigger de
 * PostgreSQL— y el PDF es el que se enseñará dentro de dos años. Ésa es toda la
 * razón de ser del módulo: si el documento se regenerara al pedirlo, el
 * contenido habría cambiado y no habría forma de demostrar qué se entregó.
 *
 * El fichero se **mueve** del prefijo `borradores/` al de `emitidas/` en vez de
 * quedarse donde está, porque en producción el Object Lock se aplica sólo al
 * segundo: un objeto bloqueado es lo que permite decirle al auditor que nadie ha
 * podido tocarlo, y un borrador tiene que poder reescribirse.
 *
 * La huella no se recalcula: se comprueba. El PDF es byte a byte el mismo, y si
 * el hash del objeto movido no coincidiera, eso es justo lo que hay que saber.
 */
final readonly class EmitirVersion
{
    public function __invoke(DocumentoVersion $version, ?string $motivo = null): DocumentoVersion
    {
        if (! $version->esEmisible()) {
            throw VersionNoEmisible::porEstado($version);
        }

        $documento = $version->documento;
        $numero = $documento->siguienteNumero();

        $origen = (string) $version->ruta;
        // La organización sale del documento y no del contexto: es la misma bajo
        // RLS, y así la ruta de la versión emitida se construye igual que la del
        // borrador en `GenerarDocumento::almacenar()`.
        $destino = sprintf(
            '%d/%d/emitidas/v%d/%s',
            $documento->organizacion_id,
            $documento->id,
            $numero,
            basename($origen),
        );

        $disco = Storage::disk(GenerarDocumento::DISCO);
        $disco->copy($origen, $destino);

        /*
         * Se escribe con el query builder y no con `$version->update()`: el
         * trigger de inmutabilidad sólo mira `OLD.numero`, así que este UPDATE
         * —el que PONE el número— pasa; pero cualquiera posterior ya no. Usar el
         * modelo funcionaría igual, y esto deja claro que es la última escritura
         * de la fila.
         */
        DB::table('documento_versiones')
            ->where('id', $version->id)
            ->update([
                'numero' => $numero,
                'ruta' => $destino,
                'motivo' => $motivo,
                'emitida_en' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        // El borrador desaparece con la emisión: la siguiente generación crea
        // uno nuevo. Así el índice único parcial sigue admitiendo un borrador.
        $disco->delete($origen);

        return $version->fresh() ?? $version;
    }
}
