<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Models;

use Database\Factories\Catalogo\PerfilCumplimientoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Perfil de cumplimiento del ENS (serie CCN-STIC 890). Es una vista filtrada
 * del catálogo, no un catálogo aparte.
 *
 * @property int $id
 * @property int $marco_id
 * @property string $codigo
 * @property string $nombre
 * @property ?string $descripcion
 * @property ?string $referencia
 */
class PerfilCumplimiento extends Model
{
    /** @use HasFactory<PerfilCumplimientoFactory> */
    use HasFactory;

    protected $table = 'perfiles_cumplimiento';

    protected $fillable = ['marco_id', 'codigo', 'nombre', 'descripcion', 'referencia'];

    /** @return BelongsTo<Marco, $this> */
    public function marco(): BelongsTo
    {
        return $this->belongsTo(Marco::class);
    }

    /** @return BelongsToMany<Requisito, $this> */
    public function requisitos(): BelongsToMany
    {
        return $this->belongsToMany(Requisito::class, 'perfil_requisitos', 'perfil_id', 'requisito_id')
            ->withPivot('exigencia')
            ->withTimestamps();
    }

    /**
     * Los que llevan al menos una medida: los únicos que se pueden asignar.
     *
     * Un perfil cargado sin medidas —el catálogo lo admite mientras el contraste
     * con la guía está pendiente— dejaría el sistema sin nada exigible. Ver
     * `PerfilNoAplicable`.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeConContenido(Builder $query): void
    {
        $query->whereHas('requisitos');
    }

    protected static function newFactory(): PerfilCumplimientoFactory
    {
        return PerfilCumplimientoFactory::new();
    }
}
