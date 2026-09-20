<?php

declare(strict_types=1);

namespace App\Domain\Incidente\Models;

use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El histórico de un incidente (invariante 7).
 *
 * La pregunta del auditor no es «¿está cerrado?», es **«¿cuánto se tardó en
 * contenerlo?»**, y eso sólo lo contesta la fecha de cada paso.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $incidente_id
 * @property ?EstadoIncidente $estado_anterior
 * @property EstadoIncidente $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class IncidenteTransicion extends Model
{
    use PerteneceAOrganizacion;

    protected $table = 'incidente_transiciones';

    protected $fillable = [
        'organizacion_id',
        'incidente_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
    ];

    /** @return BelongsTo<Incidente, $this> */
    public function incidente(): BelongsTo
    {
        return $this->belongsTo(Incidente::class);
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
            'estado_anterior' => EstadoIncidente::class,
            'estado_nuevo' => EstadoIncidente::class,
        ];
    }
}
