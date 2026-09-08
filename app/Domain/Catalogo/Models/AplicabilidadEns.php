<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Models;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Enums\Exigencia;
use Database\Factories\Catalogo\AplicabilidadEnsFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una celda de la matriz de aplicabilidad del Anexo II.
 *
 * Si `dimension_moduladora` es nula, `categoria` es la categoría del sistema.
 * Si no lo es, `categoria` se lee como el nivel de esa dimensión concreta.
 *
 * @property int $id
 * @property CategoriaEns $categoria
 * @property ?Dimension $dimension_moduladora
 */
class AplicabilidadEns extends Model
{
    /** @use HasFactory<AplicabilidadEnsFactory> */
    use HasFactory;

    protected $table = 'aplicabilidad_ens';

    protected $fillable = ['requisito_id', 'categoria', 'exigencia', 'dimension_moduladora'];

    /** @return BelongsTo<Requisito, $this> */
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(Requisito::class);
    }

    /** @return Attribute<Exigencia, Exigencia|string> */
    protected function exigencia(): Attribute
    {
        return Attribute::make(
            get: fn (string $valor): Exigencia => Exigencia::desde($valor),
            set: fn (Exigencia|string $valor): string => (string) $valor,
        );
    }

    public function moduladaPorDimension(): bool
    {
        return $this->dimension_moduladora instanceof Dimension;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'categoria' => CategoriaEns::class,
            'dimension_moduladora' => Dimension::class,
        ];
    }

    protected static function newFactory(): AplicabilidadEnsFactory
    {
        return AplicabilidadEnsFactory::new();
    }
}
