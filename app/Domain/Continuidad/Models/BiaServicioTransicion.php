<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Models;

use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El histórico de un BIA (invariante 7).
 *
 * La pregunta del auditor no es «¿está aprobado?», es **«¿desde cuándo, y
 * quién lo aprobó?»** — y, con `EditarBia` devolviendo la fila vigente a
 * borrador, también «¿qué decía la última vez que se aprobó?». Sin traza, esa
 * respuesta se pierde con la propia edición.
 *
 * Sin `RegistraTraza`, como `IncidenteTransicion`: la traza registra sobre el
 * histórico y no sobre sí misma.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $bia_servicio_id
 * @property ?EstadoBia $estado_anterior
 * @property EstadoBia $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class BiaServicioTransicion extends Model
{
    use PerteneceAOrganizacion;

    protected $table = 'bia_servicio_transiciones';

    protected $fillable = [
        'organizacion_id',
        'bia_servicio_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
    ];

    /** @return BelongsTo<BiaServicio, $this> */
    public function biaServicio(): BelongsTo
    {
        return $this->belongsTo(BiaServicio::class);
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
            'estado_anterior' => EstadoBia::class,
            'estado_nuevo' => EstadoBia::class,
        ];
    }
}
