<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Models;

use Database\Factories\Catalogo\RefuerzoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refuerzo de una medida del ENS: R1, R2, R3… Cada uno con descripción propia.
 *
 * @property int $id
 * @property string $codigo
 * @property string $descripcion
 */
class Refuerzo extends Model
{
    /** @use HasFactory<RefuerzoFactory> */
    use HasFactory;

    protected $table = 'refuerzos';

    protected $fillable = ['requisito_id', 'codigo', 'descripcion', 'orden'];

    /** @return BelongsTo<Requisito, $this> */
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(Requisito::class);
    }

    protected static function newFactory(): RefuerzoFactory
    {
        return RefuerzoFactory::new();
    }
}
