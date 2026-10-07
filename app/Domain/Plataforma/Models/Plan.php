<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lo que se le vende a una organización (punto 43).
 *
 * Es dato de la plataforma, sin `organizacion_id`, igual que el catálogo: el
 * mismo plan lo tienen varios clientes. **No lleva precio.** El cobro se modela
 * y no se hace: lo que el producto necesita saber de un plan es qué límites
 * pone y cuánto aguanta un impago antes de pasar a sólo lectura.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property ?string $descripcion
 * @property ?int $limite_cuentas nulo, sin límite
 * @property ?int $limite_sistemas nulo, sin límite
 * @property int $dias_gracia
 * @property bool $activo
 * @property ?Carbon $created_at
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $table = 'planes';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'limite_cuentas',
        'limite_sistemas',
        'dias_gracia',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'limite_cuentas' => 'integer',
            'limite_sistemas' => 'integer',
            'dias_gracia' => 'integer',
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
