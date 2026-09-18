<?php

declare(strict_types=1);

namespace Database\Factories\Contexto;

use App\Domain\Contexto\Enums\EstadoAnalisis;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Análisis del contexto sintéticos. Ni uno real de ningún cliente.
 *
 * Nace **en borrador**, que es el único estado que no arrastra nada: sin número,
 * sin instantánea, sin firma y sin la declaración del clima, que los cuatro `CHECK`
 * acoplan al estado. Los estados avanzados van en `state`s que ponen lo que la base
 * exige, igual que `TareaFactory::enEstado()` y `AuditoriaFactory::cerrada()`.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto. Declararlo aquí lo cortocircuitaría —el trait sólo rellena si viene a
 * nulo— y la fila nacería con un tenant que no es el del contexto, que es lo que
 * el `WITH CHECK` de la política rechaza con un error que habla de privilegios y
 * no menciona la palabra «organización».
 *
 * @extends Factory<AnalisisContexto>
 */
class AnalisisContextoFactory extends Factory
{
    protected $model = AnalisisContexto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => null,
            'fecha_analisis' => Carbon::today(),
            'estado' => EstadoAnalisis::Borrador->value,
            'clima_pertinente' => null,
            'clima_justificacion' => null,
            'nota' => null,
            'instantanea' => null,
            'creado_por_id' => null,
            'aprobado_por_id' => null,
            'aprobado_en' => null,
        ];
    }

    /** Con la pregunta del cambio climático contestada, que es lo que deja aprobar. */
    public function conClimaDeclarado(bool $pertinente = true, ?string $justificacion = null): self
    {
        return $this->state(fn (): array => [
            'clima_pertinente' => $pertinente,
            'clima_justificacion' => $justificacion ?? ($pertinente
                ? 'Las olas de calor afectan a la climatización del centro de proceso de datos y a la disponibilidad del servicio.'
                : 'La actividad es íntegramente digital y no depende de instalaciones propias sensibles al clima.'),
        ]);
    }

    /**
     * Aprobado: con número, firma, instantánea y la declaración del clima.
     *
     * La instantánea va con un mínimo con forma, no vacía: un análisis aprobado con
     * `{}` dentro pasaría el `CHECK` y luego rompería en cualquier sitio que la
     * leyera, que es el peor tipo de dato de prueba.
     */
    public function aprobado(int $numero = 1, ?User $aprobador = null): self
    {
        return $this->conClimaDeclarado()->state(fn (): array => [
            'numero' => $numero,
            'estado' => EstadoAnalisis::Aprobado->value,
            'instantanea' => [
                'congeladaEn' => Carbon::now()->toIso8601String(),
                'fechaAnalisis' => Carbon::today()->toDateString(),
                'clima' => ['pertinente' => true, 'justificacion' => 'Instantánea sintética.'],
                'nota' => null,
                'dafo' => [],
                'partes' => [],
                'alcance' => [],
            ],
            'aprobado_por_id' => $aprobador->id ?? User::factory(),
            'aprobado_en' => Carbon::now(),
        ]);
    }

    /** Sustituido por uno posterior. Lo pone el sistema, nunca una persona. */
    public function obsoleto(int $numero = 1): self
    {
        return $this->aprobado($numero)->state(fn (): array => [
            'estado' => EstadoAnalisis::Obsoleto->value,
        ]);
    }
}
