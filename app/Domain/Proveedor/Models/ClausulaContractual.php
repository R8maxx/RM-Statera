<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Models;

use Database\Factories\Proveedor\ClausulaContractualFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una cláusula de seguridad que se comprueba en el contrato de un proveedor.
 *
 * **Catálogo global**, sin `organizacion_id` y sin ninguna de las tres capas
 * (invariante 2), como una amenaza de MAGERIT. Se carga desde
 * `catalogo/clausulas-proveedor.yaml` y se retira, nunca se borra.
 *
 * @property int $id
 * @property string $codigo
 * @property string $titulo
 * @property ?string $descripcion
 * @property list<array{marco: string, requisito: string}> $referencias
 * @property int $orden
 * @property ?string $huella
 * @property bool $vigente
 * @property ?Carbon $retirado_en
 */
class ClausulaContractual extends Model
{
    /** @use HasFactory<ClausulaContractualFactory> */
    use HasFactory;

    protected $table = 'clausulas_contractuales';

    protected $fillable = [
        'codigo',
        'titulo',
        'descripcion',
        'referencias',
        'orden',
        'huella',
        'vigente',
        'retirado_en',
    ];

    /** @param  Builder<self>  $consulta */
    public function scopeVigentes(Builder $consulta): void
    {
        $consulta->where('vigente', true)->orderBy('orden')->orderBy('codigo');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'referencias' => 'array',
            'orden' => 'integer',
            'vigente' => 'boolean',
            'retirado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): ClausulaContractualFactory
    {
        return ClausulaContractualFactory::new();
    }
}
