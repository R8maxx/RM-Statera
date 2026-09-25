<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Models;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Proveedor\Enums\TipoCertificacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lo que acredita un proveedor: ISO 27001, la conformidad con el ENS y su
 * categoría, u otra cosa con descripción.
 *
 * El fichero no vive aquí: si lo hay, es una evidencia, que es donde ya viven
 * los ficheros con caducidad y la que puede probar A.5.19 además.
 *
 * @property int $id
 * @property int $proveedor_id
 * @property TipoCertificacion $tipo
 * @property ?CategoriaEns $categoria_ens
 * @property ?string $descripcion
 * @property ?string $entidad_emisora
 * @property ?Carbon $emitida_en
 * @property ?Carbon $caduca_en
 * @property ?int $evidencia_id
 */
class ProveedorCertificacion extends Model
{
    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'proveedor_certificaciones';

    protected $fillable = [
        'organizacion_id',
        'proveedor_id',
        'tipo',
        'categoria_ens',
        'descripcion',
        'entidad_emisora',
        'emitida_en',
        'caduca_en',
        'evidencia_id',
    ];

    public function haCaducado(): bool
    {
        return $this->caduca_en !== null && $this->caduca_en->lt(Carbon::today());
    }

    public function etiqueta(): string
    {
        return match ($this->tipo) {
            TipoCertificacion::Ens => 'ENS, categoría '.mb_strtolower((string) $this->categoria_ens?->etiqueta()),
            TipoCertificacion::Otra => (string) $this->descripcion,
            default => $this->tipo->etiqueta(),
        };
    }

    /** @return BelongsTo<Proveedor, $this> */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class);
    }

    /** @param  Builder<self>  $consulta */
    public function scopeCaducadas(Builder $consulta): void
    {
        $consulta->whereNotNull('caduca_en')->whereDate('caduca_en', '<', Carbon::today());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoCertificacion::class,
            'categoria_ens' => CategoriaEns::class,
            'emitida_en' => 'date',
            'caduca_en' => 'date',
        ];
    }
}
