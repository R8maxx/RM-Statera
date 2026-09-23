<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Models;

use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El histórico de una prueba de continuidad (invariante 7).
 *
 * Mismo papel que `BiaServicioTransicion` e `IncidenteTransicion`: la
 * pregunta del auditor no es «¿se probó?», es «¿cuándo, y con qué resultado?».
 *
 * Sin `RegistraTraza`, como sus dos hermanas: la traza registra sobre el
 * histórico y no sobre sí misma.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $prueba_continuidad_id
 * @property ?EstadoPrueba $estado_anterior
 * @property EstadoPrueba $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class PruebaContinuidadTransicion extends Model
{
    use PerteneceAOrganizacion;

    protected $table = 'prueba_continuidad_transiciones';

    protected $fillable = [
        'organizacion_id',
        'prueba_continuidad_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
    ];

    /** @return BelongsTo<PruebaContinuidad, $this> */
    public function pruebaContinuidad(): BelongsTo
    {
        return $this->belongsTo(PruebaContinuidad::class);
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
            'estado_anterior' => EstadoPrueba::class,
            'estado_nuevo' => EstadoPrueba::class,
        ];
    }
}
