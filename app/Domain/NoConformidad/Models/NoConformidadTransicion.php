<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad\Models;

use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cada cambio de estado de una no conformidad, con su fecha y su autor.
 *
 * Invariante 7, y aquí carga con dos cosas que no tienen columna propia: **el
 * motivo de una anulación** y **por qué falló una verificación**. Las dos son
 * decisiones que el auditor puede cuestionar, y las dos se pierden si el cambio
 * de estado no deja rastro.
 *
 * Sin `updated_at`: es histórico y no se edita.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $no_conformidad_id
 * @property ?EstadoNoConformidad $estado_anterior
 * @property EstadoNoConformidad $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class NoConformidadTransicion extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'no_conformidad_transiciones';

    protected $fillable = [
        'organizacion_id',
        'no_conformidad_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
        'created_at',
    ];

    /** @return BelongsTo<NoConformidad, $this> */
    public function noConformidad(): BelongsTo
    {
        return $this->belongsTo(NoConformidad::class);
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
            'estado_anterior' => EstadoNoConformidad::class,
            'estado_nuevo' => EstadoNoConformidad::class,
            'created_at' => 'datetime',
        ];
    }
}
