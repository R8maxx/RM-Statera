<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un cambio de estado de una vulnerabilidad, con fecha y autor (invariante 7).
 *
 * @property int $id
 * @property int $vulnerabilidad_id
 * @property ?EstadoVulnerabilidad $estado_anterior
 * @property EstadoVulnerabilidad $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class VulnerabilidadTransicion extends Model
{
    use PerteneceAOrganizacion;

    public const UPDATED_AT = null;

    protected $table = 'vulnerabilidad_transiciones';

    protected $fillable = [
        'organizacion_id',
        'vulnerabilidad_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
    ];

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoVulnerabilidad::class,
            'estado_nuevo' => EstadoVulnerabilidad::class,
        ];
    }
}
