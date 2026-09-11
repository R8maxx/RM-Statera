<?php

declare(strict_types=1);

namespace App\Domain\Documento\Jobs;

use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Jobs\ConContextoDeOrganizacion;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Genera el PDF fuera del ciclo de petición.
 *
 * Va a la cola `documentos`, que está dimensionada aparte precisamente porque es
 * la lenta: una SoA de noventa y tres controles con sus evidencias tarda, y no
 * puede bloquear a los avisos ni a los importadores.
 *
 * **Aquí no hay petición HTTP, ni usuario, ni contexto de organización.** Sin
 * contexto, el scope global no devuelve ninguna fila y la política de RLS
 * deniega por defecto: el job no rompería, sencillamente no vería nada. De eso
 * responde el middleware `ConContextoDeOrganizacion`, y por eso lo que viaja son
 * dos enteros.
 *
 * **Nada de `SerializesModels`**: ese trait vuelve a consultar el modelo al
 * deserializar, ANTES de que corra ningún middleware, y el job muere con un
 * `ModelNotFoundException` que no menciona la palabra «organización».
 */
final class GenerarDocumentoJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Tres intentos: los fallos de Gotenberg que se ven en la práctica son de
     * arranque del contenedor y se arreglan solos al segundo.
     */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /** Más que el timeout del job, para que el candado no se suelte a mitad. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $versionId,
        public readonly int $organizacionId,
    ) {
        $this->onQueue('documentos');
    }

    /** Pulsar «Generar» dos veces no genera dos veces. */
    public function uniqueId(): string
    {
        return "documento-version:{$this->versionId}";
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new ConContextoDeOrganizacion($this->organizacionId)];
    }

    public function handle(GenerarDocumento $generar): void
    {
        $version = DocumentoVersion::query()->find($this->versionId);

        // Pudo borrarse el documento entre el encolado y el trabajo. No es un
        // fallo: es que ya no hace falta.
        if ($version === null) {
            return;
        }

        $generar->ejecutar($version);
    }

    /**
     * Marca el fallo, y sólo cuando ya no quedan intentos.
     *
     * **`failed()` NO pasa por el middleware del job**, así que aquí hay que
     * fijar el contexto a mano o la escritura se la come RLS y la versión se
     * queda «generando» para siempre, que es la peor forma de fallar: la ficha
     * seguiría preguntando al servidor sin que nadie vaya a contestar.
     *
     * Se escribe con el query builder porque el modelo no se puede ni leer sin
     * contexto, y porque aquí lo único que se quiere es dejar constancia.
     */
    public function failed(?Throwable $e): void
    {
        app(ContextoOrganizacion::class)->paraOrganizacion($this->organizacionId, function () use ($e): void {
            DB::table('documento_versiones')
                ->where('id', $this->versionId)
                ->whereNull('numero')
                ->update([
                    'estado_generacion' => EstadoGeneracion::Fallida->value,
                    // El CHECK de la tabla exige motivo cuando el estado es
                    // `fallida`: un fallo sin mensaje no se diagnostica.
                    'error' => $e?->getMessage() ?? 'La generación falló sin mensaje.',
                    'updated_at' => now(),
                ]);
        });
    }
}
