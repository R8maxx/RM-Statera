<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Excepciones\ServicioNoValido;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta una prueba de continuidad, con su pivote de servicios y su
 * primera transición.
 *
 * **Comprueba las dos fronteras antes de escribir nada, en el dominio y no
 * sólo en el `FormRequest`**: que el documento es un plan de continuidad y
 * que cada servicio es un activo de tipo `Servicios`. Mismo razonamiento que
 * `RegistrarBia` y `VincularServicioAPlan` — la regla vale igual para lo que
 * llegue por un importador el día que exista uno, y la base no puede imponer
 * ninguna de las dos con un `CHECK`: comprobar una columna de otra tabla desde
 * una restricción no es portable.
 *
 * El `refresh()` no es opcional, por el mismo motivo que en `RegistrarBia` y
 * `RegistrarIncidente`: `estado` lo pone la base con su valor por defecto.
 */
final class PlanificarPrueba
{
    public function __construct(private readonly RegistroTransicionesPrueba $registro) {}

    /**
     * @param  array<string, mixed>  $atributos
     * @param  list<int>  $servicioIds
     *
     * @throws ServicioNoValido
     */
    public function __invoke(array $atributos, array $servicioIds, ?User $usuario = null): PruebaContinuidad
    {
        $documento = $this->exigirPlan($atributos);

        foreach ($servicioIds as $servicioId) {
            $this->exigirServicio($servicioId);
        }

        return DB::transaction(function () use ($atributos, $servicioIds, $documento, $usuario): PruebaContinuidad {
            $prueba = PruebaContinuidad::query()->create($atributos);
            $prueba->refresh();

            $prueba->servicios()->sync(collect($servicioIds)->mapWithKeys(
                static fn (int $servicioId): array => [$servicioId => ['organizacion_id' => $documento->organizacion_id]],
            )->all());

            $this->registro->registrar($prueba, null, $prueba->estado, $usuario);

            return $prueba;
        });
    }

    /**
     * @param  array<string, mixed>  $atributos
     *
     * @throws ServicioNoValido
     */
    private function exigirPlan(array $atributos): Documento
    {
        $documento = Documento::query()->find($atributos['documento_id'] ?? null);

        if (! $documento instanceof Documento || $documento->tipo !== TipoDocumento::PlanContinuidad) {
            throw ServicioNoValido::noEsPlan();
        }

        return $documento;
    }

    /**
     * @throws ServicioNoValido
     */
    private function exigirServicio(int $servicioId): void
    {
        $activo = Activo::query()->find($servicioId);

        if (! $activo instanceof Activo || $activo->tipo !== TipoActivo::Servicios) {
            throw ServicioNoValido::noEsServicio();
        }
    }
}
