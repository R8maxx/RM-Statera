<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un cambio de estado de un proveedor, con fecha y autor (invariante 7).
 *
 * @property int $id
 * @property int $proveedor_id
 * @property ?EstadoProveedor $estado_anterior
 * @property EstadoProveedor $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class ProveedorTransicion extends Model
{
    use PerteneceAOrganizacion;

    public const UPDATED_AT = null;

    protected $table = 'proveedor_transiciones';

    protected $fillable = [
        'organizacion_id',
        'proveedor_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
    ];

    /** @return BelongsTo<Proveedor, $this> */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
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
            'estado_anterior' => EstadoProveedor::class,
            'estado_nuevo' => EstadoProveedor::class,
        ];
    }
}
