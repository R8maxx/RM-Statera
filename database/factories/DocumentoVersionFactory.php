<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\Models\DocumentoVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Datos sintéticos.
 *
 * **Una versión emitida se crea emitida, en un solo `INSERT`.** El trigger
 * `documento_versiones_inmutables` rechaza cualquier `UPDATE` sobre una fila con
 * `numero`, así que el `create()` + `update()` de toda la vida choca contra él.
 * Es molesto exactamente una vez, y a cambio la inmutabilidad la garantiza la
 * base y no la disciplina de quien escriba el próximo test.
 *
 * @extends Factory<DocumentoVersion>
 */
class DocumentoVersionFactory extends Factory
{
    protected $model = DocumentoVersion::class;

    /**
     * Por defecto, un borrador recién encolado: es el estado por el que pasa
     * todo, y el único sobre el que se puede seguir escribiendo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => null,
            'estado_generacion' => EstadoGeneracion::Encolada->value,
            'instantanea' => [],
            'parametros' => [],
        ];
    }

    public function generando(): self
    {
        return $this->state(fn (): array => ['estado_generacion' => EstadoGeneracion::Generando->value]);
    }

    /** Borrador con PDF detrás: el estado desde el que se puede emitir. */
    public function generada(): self
    {
        return $this->state(fn (): array => [
            'estado_generacion' => EstadoGeneracion::Generada->value,
            'disco' => 'documentos',
            'ruta' => '1/1/borradores/'.fake()->uuid().'.pdf',
            'nombre_fichero' => 'documento.pdf',
            'mime' => 'application/pdf',
            'tamano' => 24_576,
            'hash_sha256' => hash('sha256', (string) fake()->uuid()),
            'total_requisitos' => 93,
            'total_excluidos' => 2,
            'total_implantados' => 41,
        ]);
    }

    public function fallida(string $error = 'Gotenberg respondió 503.'): self
    {
        return $this->state(fn (): array => [
            'estado_generacion' => EstadoGeneracion::Fallida->value,
            'error' => $error,
        ]);
    }

    /** Ya entregada. Inmutable desde el propio `INSERT`. */
    public function emitida(int $numero = 1, ?string $motivo = null): self
    {
        return $this->generada()->state(fn (): array => [
            'numero' => $numero,
            'ruta' => "1/1/emitidas/v{$numero}/".fake()->uuid().'.pdf',
            'motivo' => $motivo ?? 'Entrega a auditoría.',
            'emitida_en' => Carbon::now(),
        ]);
    }

    public function delDocumento(int $documentoId): self
    {
        return $this->state(fn (): array => ['documento_id' => $documentoId]);
    }
}
