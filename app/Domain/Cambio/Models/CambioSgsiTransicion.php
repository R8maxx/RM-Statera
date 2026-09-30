<?php

declare(strict_types=1);

namespace App\Domain\Cambio\Models;

use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cada cambio de estado de un cambio del SGSI, con su fecha y su autor.
 *
 * Invariante 7. Carga con el motivo de descartar y de reabrir, que no tienen
 * columna propia, y conserva la firma de un cambio que volvió al borrador.
 *
 * Sin `updated_at`: es histórico y no se edita.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $cambio_sgsi_id
 * @property ?EstadoCambio $estado_anterior
 * @property EstadoCambio $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class CambioSgsiTransicion extends Model
{
    use PerteneceAOrganizacion;
    use RegistraTraza;

    public $timestamps = false;

    protected $table = 'cambio_sgsi_transiciones';

    protected $fillable = [
        'organizacion_id',
        'cambio_sgsi_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
        'created_at',
    ];

    /** @return BelongsTo<CambioSgsi, $this> */
    public function cambio(): BelongsTo
    {
        return $this->belongsTo(CambioSgsi::class, 'cambio_sgsi_id');
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
            'estado_anterior' => EstadoCambio::class,
            'estado_nuevo' => EstadoCambio::class,
            'created_at' => 'datetime',
        ];
    }
}
