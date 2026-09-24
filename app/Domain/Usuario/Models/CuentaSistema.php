<?php

declare(strict_types=1);

namespace App\Domain\Usuario\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un sistema que una cuenta tiene en su alcance.
 *
 * Es modelo y no pivote de `belongsToMany` porque la fila lleva
 * `organizacion_id` y tiene RLS: con `attach()` habría que acordarse de pasar
 * la columna a mano cada vez, y el trait la rellena solo.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $user_id
 * @property int $sistema_id
 */
class CuentaSistema extends Model
{
    use PerteneceAOrganizacion;

    protected $table = 'cuenta_sistemas';

    protected $fillable = ['user_id', 'sistema_id'];

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Sistema, $this> */
    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }
}
