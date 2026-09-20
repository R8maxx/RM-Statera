<?php

declare(strict_types=1);

namespace App\Domain\Persona\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Persona\Enums\RolEns;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Persona\DesignacionRolFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El nombramiento de una persona en un rol ENS de un sistema. Cláusula 5.3.
 *
 * **Con vigencia, y no se borra.** «¿Desde cuándo es responsable de seguridad?» es
 * literalmente la pregunta del auditor (invariante 7): una tabla que sólo guardara
 * el nombramiento de hoy no la contesta, y revocar borrando dejaría el sistema sin
 * poder demostrar quién respondía el año pasado. Vigente es `hasta IS NULL`.
 *
 * **Por sistema y no por organización**, que es donde la incompatibilidad
 * significa algo: la misma persona puede ser responsable de seguridad de un
 * sistema y responsable del sistema de otro sin conflicto ninguno.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $persona_id
 * @property int $sistema_id
 * @property RolEns $rol
 * @property Carbon $desde
 * @property ?Carbon $hasta
 * @property ?int $designada_por_id
 * @property ?string $nota
 */
class DesignacionRol extends Model
{
    /** @use HasFactory<DesignacionRolFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'designaciones_rol';

    protected $fillable = [
        'organizacion_id',
        'persona_id',
        'sistema_id',
        'rol',
        'desde',
        'hasta',
        'designada_por_id',
        'nota',
    ];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** @return BelongsTo<Sistema, $this> */
    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }

    /** @return BelongsTo<User, $this> */
    public function designadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designada_por_id');
    }

    /** Vigente **se deriva**: es no tener fecha de fin. */
    public function estaVigente(): bool
    {
        return $this->hasta === null;
    }

    /** @param  Builder<$this>  $query */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('designaciones_rol.hasta');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rol' => RolEns::class,
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }

    protected static function newFactory(): DesignacionRolFactory
    {
        return DesignacionRolFactory::new();
    }
}
