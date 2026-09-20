<?php

declare(strict_types=1);

namespace App\Domain\Objetivo\Models;

use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cada cambio de estado de un objetivo de seguridad, con su fecha y su autor.
 *
 * Invariante 7, y aquí carga con tres cosas que no tienen columna propia: el
 * motivo de una retirada, **por qué un objetivo no se alcanzó** y por qué se
 * volvió a abrir uno que ya estaba cerrado. La del medio es la que paga la tabla:
 * es literalmente lo que la revisión por la dirección va a preguntar del año que
 * termina, y sin ella el acta diría «tres de cinco» sin poder explicar ni uno.
 *
 * Sin `updated_at`: es histórico y no se edita.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $objetivo_id
 * @property ?EstadoObjetivo $estado_anterior
 * @property EstadoObjetivo $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class ObjetivoTransicion extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'objetivo_transiciones';

    protected $fillable = [
        'organizacion_id',
        'objetivo_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
        'created_at',
    ];

    /** @return BelongsTo<Objetivo, $this> */
    public function objetivo(): BelongsTo
    {
        return $this->belongsTo(Objetivo::class);
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
            'estado_anterior' => EstadoObjetivo::class,
            'estado_nuevo' => EstadoObjetivo::class,
            'created_at' => 'datetime',
        ];
    }
}
