<?php

declare(strict_types=1);

namespace App\Domain\Persona\Models;

use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use Database\Factories\Persona\AcuerdoConfidencialidadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El acuerdo de confidencialidad firmado por una persona: `mp.per.2`.
 *
 * **Varios por persona a propósito**: un acuerdo se renueva, y el anterior sigue
 * siendo la prueba de qué firmó esa persona en 2024. Por eso no hay unicidad y por
 * eso `acuerdoVigente()` es una búsqueda y no una columna.
 *
 * **`vigente_hasta` nulo es «sin vencimiento»**, que es lo normal en un acuerdo de
 * confidencialidad —el deber sobrevive a la relación laboral— y no es lo mismo que
 * «no lo hemos mirado». La misma distinción que `tareas.fecha_limite`.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $persona_id
 * @property Carbon $fecha_firma
 * @property ?Carbon $vigente_hasta
 * @property ?string $nota
 * @property ?int $evidencia_id
 */
class AcuerdoConfidencialidad extends Model
{
    /** @use HasFactory<AcuerdoConfidencialidadFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'acuerdos_confidencialidad';

    protected $fillable = [
        'organizacion_id',
        'persona_id',
        'fecha_firma',
        'vigente_hasta',
        'nota',
        'evidencia_id',
    ];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class);
    }

    public function estaVigente(?Carbon $hoy = null): bool
    {
        return $this->vigente_hasta === null
            || ! $this->vigente_hasta->isBefore($hoy ?? Carbon::today());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_firma' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    protected static function newFactory(): AcuerdoConfidencialidadFactory
    {
        return AcuerdoConfidencialidadFactory::new();
    }
}
