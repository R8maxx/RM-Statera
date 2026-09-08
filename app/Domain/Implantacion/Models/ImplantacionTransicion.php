<?php

declare(strict_types=1);

namespace App\Domain\Implantacion\Models;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una transición de estado, con fecha y autor.
 *
 * El auditor no pregunta "¿está implantado?", pregunta "¿desde cuándo?"
 * (invariante 7). Esta tabla es histórico: se escribe y no se edita, y por eso
 * no tiene `updated_at`.
 *
 * `usuario_id` es nulo cuando la transición la provoca el sistema al recalcular
 * tras un cambio de valoración, no una persona.
 *
 * @property int $id
 * @property int $implantacion_id
 * @property ?EstadoImplantacion $estado_anterior
 * @property EstadoImplantacion $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 */
class ImplantacionTransicion extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'implantacion_transiciones';

    protected $fillable = [
        'organizacion_id',
        'implantacion_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
        'created_at',
    ];

    /** @return BelongsTo<Implantacion, $this> */
    public function implantacion(): BelongsTo
    {
        return $this->belongsTo(Implantacion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function esAlta(): bool
    {
        return $this->estado_anterior === null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoImplantacion::class,
            'estado_nuevo' => EstadoImplantacion::class,
            'created_at' => 'datetime',
        ];
    }
}
