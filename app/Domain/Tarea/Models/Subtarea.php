<?php

declare(strict_types=1);

namespace App\Domain\Tarea\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use Database\Factories\SubtareaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un paso de la lista de comprobación de una tarea.
 *
 * No es una tarea: no tiene responsable, ni plazo, ni estado, ni histórico, y
 * **no cuenta en ningún sitio donde se cuenten tareas**. La unidad de trabajo,
 * de planificación y de informe sigue siendo la tarea.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $tarea_id
 * @property string $titulo
 * @property ?Carbon $hecha_en
 * @property int $orden
 */
class Subtarea extends Model
{
    /** @use HasFactory<SubtareaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;

    protected $table = 'subtareas';

    protected $fillable = [
        'organizacion_id',
        'tarea_id',
        'titulo',
        'hecha_en',
        'orden',
    ];

    /** @return BelongsTo<Tarea, $this> */
    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }

    public function estaHecha(): bool
    {
        return $this->hecha_en !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hecha_en' => 'datetime',
            'orden' => 'integer',
        ];
    }

    protected static function newFactory(): SubtareaFactory
    {
        return SubtareaFactory::new();
    }
}
