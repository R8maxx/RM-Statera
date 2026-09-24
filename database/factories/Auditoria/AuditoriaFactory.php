<?php

declare(strict_types=1);

namespace Database\Factories\Auditoria;

use App\Domain\Auditoria\Enums\EstadoAuditoria;
use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Auditorías sintéticas. Ni una real de ningún cliente.
 *
 * Nace **planificada**, que es el único estado en el que todo lo demás se puede
 * montar: con `cerrada` de partida, el trigger de inmutabilidad bloquearía la
 * checklist antes de que el test llegue a precargarla.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto, como en el resto del repositorio.
 *
 * @extends Factory<Auditoria>
 */
class AuditoriaFactory extends Factory
{
    protected $model = Auditoria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 9999);

        return [
            'sistema_id' => Sistema::factory(),
            'codigo' => sprintf('AUD-%04d', $numero),
            'tipo' => TipoAuditoria::Interna->value,
            'estado' => EstadoAuditoria::Planificada->value,
            'alcance' => null,
            'criterios' => null,
            'metodo' => null,
            'fecha' => Carbon::today(),
            'auditor' => null,
            'equipo' => null,
            'entidad_certificadora' => null,
            'resultado' => null,
            'conclusiones' => null,
            'fecha_cierre' => null,
            'cerrada_por_id' => null,
        ];
    }

    public function deTipo(TipoAuditoria $tipo): self
    {
        return $this->state(fn (): array => ['tipo' => $tipo->value]);
    }

    public function paraSistema(int $sistemaId): self
    {
        return $this->state(fn (): array => ['sistema_id' => $sistemaId]);
    }

    public function enCurso(): self
    {
        return $this->state(fn (): array => ['estado' => EstadoAuditoria::EnCurso->value]);
    }

    /**
     * Cerrada, con su fecha.
     *
     * El `CHECK` de la tabla acopla las dos columnas: cerrada sin fecha de cierre
     * no se puede insertar, y es la restricción que más se olvida — la misma que
     * ya obliga a `TareaFactory::enEstado()` a hacer lo mismo.
     *
     * **Ojo al usarla**: una auditoría cerrada ya no admite puntos ni hallazgos.
     * Lo normal en un test es montar el contenido y cerrarla después con
     * `CerrarAuditoria`, que es lo que hace el producto.
     */
    public function cerrada(?Carbon $fecha = null): self
    {
        return $this->state(fn (): array => [
            'estado' => EstadoAuditoria::Cerrada->value,
            'fecha_cierre' => $fecha ?? Carbon::today(),
        ]);
    }
}
