<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Models;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\HitoSuscripcion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una línea de la traza de la plataforma. Se escribe y no se toca: la base le
 * quita a la aplicación `UPDATE`, `DELETE` y `TRUNCATE`.
 *
 * @property int $id
 * @property ?int $usuario_id
 * @property ?int $organizacion_afectada_id
 * @property AccionPlataforma $accion
 * @property ?array<string, mixed> $detalle
 * @property ?string $ip
 * @property Carbon $created_at
 */
class EventoPlataforma extends Model
{
    protected $table = 'eventos_plataforma';

    public const UPDATED_AT = null;

    protected $fillable = ['usuario_id', 'organizacion_afectada_id', 'accion', 'detalle', 'ip', 'created_at'];

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<Organizacion, $this> */
    public function organizacionAfectada(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_afectada_id');
    }

    /**
     * Lo que añade el detalle a la acción, en una frase para la ficha del
     * cliente, o nulo si no añade nada que se lea.
     */
    public function resumen(): ?string
    {
        if ($this->accion !== AccionPlataforma::AvisoVencimientoEnviado) {
            return null;
        }

        $hito = HitoSuscripcion::tryFrom((string) ($this->detalle['hito'] ?? ''));
        $destinatarios = array_values(array_filter(
            (array) ($this->detalle['destinatarios'] ?? []),
            is_string(...),
        ));

        return implode(' · ', array_filter([
            $hito?->etiqueta(),
            $destinatarios === [] ? 'sin responsable de seguridad que pudiera recibirlo' : 'a '.implode(', ', $destinatarios),
        ]));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'accion' => AccionPlataforma::class,
            'detalle' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
