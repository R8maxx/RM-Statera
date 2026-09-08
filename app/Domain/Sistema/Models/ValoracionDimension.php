<?php

declare(strict_types=1);

namespace App\Domain\Sistema\Models;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use Database\Factories\ValoracionDimensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La valoración de UNA dimensión de un sistema, con su justificación.
 *
 * La justificación no es adorno: es lo que el auditor contrasta cuando discute
 * la categoría, porque de estas cinco filas sale todo lo que se le exige a la
 * organización.
 *
 * @property int $id
 * @property int $sistema_id
 * @property Dimension $dimension
 * @property NivelDimension $nivel
 * @property ?string $justificacion
 */
class ValoracionDimension extends Model
{
    /** @use HasFactory<ValoracionDimensionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;

    protected $table = 'valoracion_dimensiones';

    protected $fillable = [
        'organizacion_id',
        'sistema_id',
        'dimension',
        'nivel',
        'justificacion',
    ];

    /** @return BelongsTo<Sistema, $this> */
    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dimension' => Dimension::class,
            'nivel' => NivelDimension::class,
        ];
    }

    protected static function newFactory(): ValoracionDimensionFactory
    {
        return ValoracionDimensionFactory::new();
    }
}
