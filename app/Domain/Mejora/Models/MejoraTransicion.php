<?php

declare(strict_types=1);

namespace App\Domain\Mejora\Models;

use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cada cambio de estado de una oportunidad de mejora, con su fecha y su autor.
 *
 * Invariante 7, y aquí carga con **el motivo de una descartada**, que no tiene
 * columna propia. Es la fila que de verdad se lee: la mayoría de las ideas que se
 * apuntan no se hacen, y «por qué no» es lo único que impide que el registro se
 * llene de mejoras muertas sin explicación.
 *
 * Sin `updated_at`: es histórico y no se edita.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $mejora_id
 * @property ?EstadoMejora $estado_anterior
 * @property EstadoMejora $estado_nuevo
 * @property ?int $usuario_id
 * @property ?string $nota
 * @property Carbon $created_at
 */
class MejoraTransicion extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'mejora_transiciones';

    protected $fillable = [
        'organizacion_id',
        'mejora_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'nota',
        'created_at',
    ];

    /** @return BelongsTo<Mejora, $this> */
    public function mejora(): BelongsTo
    {
        return $this->belongsTo(Mejora::class);
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
            'estado_anterior' => EstadoMejora::class,
            'estado_nuevo' => EstadoMejora::class,
            'created_at' => 'datetime',
        ];
    }
}
