<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Models;

use App\Domain\Sistema\Models\Sistema;
use Database\Factories\OrganizacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La raíz del tenant.
 *
 * Es la única tabla de datos propios sin `organizacion_id`, porque es la
 * organización. No usa el trait PerteneceAOrganizacion por lo mismo: filtrarse a
 * sí misma no significa nada.
 *
 * @property int $id
 * @property string $nombre
 * @property ?string $cif
 * @property ?string $url_base_etiquetas
 * @property bool $sujeto_obligado_ens
 * @property bool $proveedor_sector_publico
 * @property bool $activa
 */
class Organizacion extends Model
{
    /** @use HasFactory<OrganizacionFactory> */
    use HasFactory;

    protected $table = 'organizaciones';

    protected $fillable = [
        'nombre',
        'cif',
        'sector',
        'url_base_etiquetas',
        'sujeto_obligado_ens',
        'proveedor_sector_publico',
        'activa',
    ];

    /** @return HasMany<Sistema, $this> */
    public function sistemas(): HasMany
    {
        return $this->hasMany(Sistema::class);
    }

    /**
     * El ENS aplica por obligación legal o porque se hereda del cliente público.
     */
    public function leAplicaElEns(): bool
    {
        return $this->sujeto_obligado_ens || $this->proveedor_sector_publico;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sujeto_obligado_ens' => 'boolean',
            'proveedor_sector_publico' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    protected static function newFactory(): OrganizacionFactory
    {
        return OrganizacionFactory::new();
    }
}
