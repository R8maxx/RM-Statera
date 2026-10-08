<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Models;

use App\Domain\Plataforma\Enums\OrigenCambioSuscripcion;
use App\Domain\Plataforma\Enums\PeriodoFacturacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un cambio de plan o de fechas de la suscripción de un cliente (punto 43).
 *
 * El histórico del invariante 7: la pregunta no es «¿qué plan tiene?», es
 * «¿desde cuándo, y quién se lo cambió?». Se escribe y no se toca: la base le
 * quita a la aplicación `UPDATE`, `DELETE` y `TRUNCATE`.
 *
 * Desde el punto 51 lo cambia también la propia organización, y la fila dice
 * quién (`origen`), con qué periodo y qué importe supuso. El importe lleva
 * signo —negativo es saldo a favor— y **no se cobró**: no hay pasarela.
 *
 * @property int $id
 * @property int $organizacion_afectada_id
 * @property ?int $plan_anterior_id
 * @property ?int $plan_nuevo_id
 * @property ?Carbon $vence_en_anterior
 * @property ?Carbon $vence_en_nuevo
 * @property ?string $motivo
 * @property ?PeriodoFacturacion $periodo_anterior
 * @property ?PeriodoFacturacion $periodo_nuevo
 * @property ?int $importe_centimos con signo; nulo en los cambios de la plataforma
 * @property OrigenCambioSuscripcion $origen
 * @property ?int $usuario_id
 * @property Carbon $created_at
 */
class TransicionSuscripcion extends Model
{
    protected $table = 'transiciones_suscripcion';

    public const UPDATED_AT = null;

    protected $fillable = [
        'organizacion_afectada_id',
        'plan_anterior_id',
        'plan_nuevo_id',
        'vence_en_anterior',
        'vence_en_nuevo',
        'motivo',
        'periodo_anterior',
        'periodo_nuevo',
        'importe_centimos',
        'origen',
        'usuario_id',
        'created_at',
    ];

    /** @return BelongsTo<Plan, $this> */
    public function planAnterior(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_anterior_id');
    }

    /** @return BelongsTo<Plan, $this> */
    public function planNuevo(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_nuevo_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'vence_en_anterior' => 'datetime',
            'vence_en_nuevo' => 'datetime',
            'created_at' => 'datetime',
            'periodo_anterior' => PeriodoFacturacion::class,
            'periodo_nuevo' => PeriodoFacturacion::class,
            'importe_centimos' => 'integer',
            'origen' => OrigenCambioSuscripcion::class,
        ];
    }
}
