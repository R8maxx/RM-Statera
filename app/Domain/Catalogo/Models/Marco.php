<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Models;

use App\Domain\Catalogo\Enums\EstadoMarco;
use Database\Factories\Catalogo\MarcoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un marco normativo del catálogo global: ISO 27001:2022, ENS RD 311/2022 y, en
 * su día, NIS2. No lleva `organizacion_id` y no debe llevarlo nunca.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $version
 * @property EstadoMarco $estado
 */
class Marco extends Model
{
    /** @use HasFactory<MarcoFactory> */
    use HasFactory;

    protected $table = 'marcos';

    protected $fillable = ['codigo', 'nombre', 'version', 'fecha_vigencia', 'estado'];

    /** @return HasMany<Requisito, $this> */
    public function requisitos(): HasMany
    {
        return $this->hasMany(Requisito::class);
    }

    /** @return HasMany<Requisito, $this> */
    public function raices(): HasMany
    {
        return $this->requisitos()->whereNull('parent_id')->orderBy('orden')->orderBy('codigo');
    }

    /** @return HasMany<PerfilCumplimiento, $this> */
    public function perfiles(): HasMany
    {
        return $this->hasMany(PerfilCumplimiento::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_vigencia' => 'date',
            'estado' => EstadoMarco::class,
        ];
    }

    protected static function newFactory(): MarcoFactory
    {
        return MarcoFactory::new();
    }
}
