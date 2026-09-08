<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Models;

use Database\Factories\Catalogo\PerfilCumplimientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Perfil de cumplimiento del ENS (serie CCN-STIC 890). Es una vista filtrada
 * del catálogo, no un catálogo aparte.
 *
 * @property string $codigo
 * @property string $nombre
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

    protected static function newFactory(): PerfilCumplimientoFactory
    {
        return PerfilCumplimientoFactory::new();
    }
}
