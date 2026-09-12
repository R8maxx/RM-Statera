<?php

declare(strict_types=1);

namespace App\Domain\Tarea\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cada cambio de estado de una tarea, con su fecha y su autor.
 *
 * Invariante 7. El auditor no pregunta si la tarea está cerrada, pregunta desde
 * cuándo — y en el caso de una descartada, por qué, que es lo que va en la nota.
 *
 * Sin `updated_at`: es histórico y no se edita.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $tarea_id
 * @property ?EstadoTarea $estado_anterior
 * @property EstadoTarea $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class TareaTransicion extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'tarea_transiciones';

    protected $fillable = [
        'organizacion_id',
        'tarea_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
        'created_at',
    ];

    /** @return BelongsTo<Tarea, $this> */
    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoTarea::class,
            'estado_nuevo' => EstadoTarea::class,
            'created_at' => 'datetime',
        ];
    }
}
