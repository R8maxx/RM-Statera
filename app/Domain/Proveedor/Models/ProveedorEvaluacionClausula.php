<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Proveedor\Enums\ResultadoClausula;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que se vio para una cláusula en una evaluación.
 *
 * @property int $id
 * @property int $evaluacion_id
 * @property int $clausula_id
 * @property ResultadoClausula $resultado
 * @property ?string $nota
 */
class ProveedorEvaluacionClausula extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'proveedor_evaluacion_clausulas';

    protected $fillable = [
        'organizacion_id',
        'evaluacion_id',
        'clausula_id',
        'resultado',
        'nota',
    ];

    /** @return BelongsTo<ProveedorEvaluacion, $this> */
    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(ProveedorEvaluacion::class, 'evaluacion_id');
    }

    /** @return BelongsTo<ClausulaContractual, $this> */
    public function clausula(): BelongsTo
    {
        return $this->belongsTo(ClausulaContractual::class, 'clausula_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoClausula::class,
        ];
    }
}
