<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Proveedor\Enums\Criticidad;
use App\Domain\Proveedor\Enums\ResultadoEvaluacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Una evaluación del contrato de un proveedor: lo que se comprobó un día
 * concreto, cláusula a cláusula.
 *
 * **No se edita.** Lo que se registró es lo que decía el contrato ese día, y
 * corregirlo después es reescribir lo que se firmó: se registra otra. El modelo
 * lo impide en `updating`, y no hay ruta de edición.
 *
 * @property int $id
 * @property int $proveedor_id
 * @property Carbon $fecha
 * @property ResultadoEvaluacion $resultado
 * @property Criticidad $criticidad
 * @property ?string $conclusiones
 * @property ?int $evaluada_por_id
 * @property Carbon $created_at
 */
class ProveedorEvaluacion extends Model
{
    use PerteneceAOrganizacion;
    use RegistraTraza;

    public const UPDATED_AT = null;

    protected $table = 'proveedor_evaluaciones';

    protected $fillable = [
        'organizacion_id',
        'proveedor_id',
        'fecha',
        'resultado',
        'criticidad',
        'conclusiones',
        'evaluada_por_id',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new RuntimeException('Una evaluación de proveedor registrada no se edita: se registra otra.');
        });
    }

    /** @return BelongsTo<Proveedor, $this> */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function evaluadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluada_por_id');
    }

    /** @return HasMany<ProveedorEvaluacionClausula, $this> */
    public function clausulas(): HasMany
    {
        return $this->hasMany(ProveedorEvaluacionClausula::class, 'evaluacion_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'resultado' => ResultadoEvaluacion::class,
            'criticidad' => Criticidad::class,
        ];
    }
}
