<?php

declare(strict_types=1);

namespace App\Domain\Conformidad\Models;

use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cada cambio de estado de una conformidad, con su fecha y su autor.
 *
 * Invariante 7, y aquí contesta la pregunta que el auditor hace de verdad:
 * «¿desde cuándo tiene este sistema la declaración?». Carga además con el motivo
 * de una retirada, que no tiene columna propia.
 *
 * Sin `updated_at`: es histórico y no se edita.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $conformidad_id
 * @property ?EstadoConformidad $estado_anterior
 * @property EstadoConformidad $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class ConformidadTransicion extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'conformidad_transiciones';

    protected $fillable = [
        'organizacion_id',
        'conformidad_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
        'created_at',
    ];

    /** @return BelongsTo<Conformidad, $this> */
    public function conformidad(): BelongsTo
    {
        return $this->belongsTo(Conformidad::class);
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
            'estado_anterior' => EstadoConformidad::class,
            'estado_nuevo' => EstadoConformidad::class,
            'created_at' => 'datetime',
        ];
    }
}
