<?php

declare(strict_types=1);

namespace App\Domain\Persona\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Quién ocupa qué puesto y desde cuándo.
 *
 * **Con vigencia y sin borrar**, como `DesignacionRol`: «¿desde cuándo ocupa ese
 * puesto?» es la pregunta del auditor (invariante 7), y una columna `puesto_id`
 * en `personas` sólo sabe contestar por el presente. Vigente es `hasta IS NULL`,
 * y lo impone un índice único parcial sobre `persona_id`.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $persona_id
 * @property int $puesto_id
 * @property Carbon $desde
 * @property ?Carbon $hasta
 * @property ?int $asignada_por_id
 * @property ?string $nota
 */
class AsignacionPuesto extends Model
{
    use PerteneceAOrganizacion;

    protected $table = 'asignaciones_puesto';

    protected $fillable = [
        'organizacion_id',
        'persona_id',
        'puesto_id',
        'desde',
        'hasta',
        'asignada_por_id',
        'nota',
    ];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** @return BelongsTo<Puesto, $this> */
    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    /** @return BelongsTo<User, $this> */
    public function asignadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_por_id');
    }

    public function estaVigente(): bool
    {
        return $this->hasta === null;
    }

    /** @param  Builder<$this>  $query */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('hasta');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }
}
