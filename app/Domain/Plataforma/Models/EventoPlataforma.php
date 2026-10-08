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

    /**
     * Claves del detalle que no se enseñan nunca, aunque un día alguien las
     * escriba ahí por error (punto 50). La traza se lee en pantalla y se
     * exporta a CSV.
     *
     * @var list<string>
     */
    private const SECRETAS = ['password', 'contrasena', 'token', 'secret', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    /**
     * El detalle en una línea legible, sin claves secretas: «clave: valor ·
     * clave: valor». Las listas se unen con comas.
     */
    public function detalleLegible(): ?string
    {
        $partes = [];

        foreach ($this->detalle ?? [] as $clave => $valor) {
            if (in_array(mb_strtolower((string) $clave), self::SECRETAS, true) || $valor === null || $valor === []) {
                continue;
            }

            $texto = match (true) {
                is_bool($valor) => $valor ? 'sí' : 'no',
                is_array($valor) => implode(', ', array_map(static fn ($uno): string => is_scalar($uno) ? (string) $uno : json_encode($uno, JSON_UNESCAPED_UNICODE), $valor)),
                is_scalar($valor) => (string) $valor,
                default => (string) json_encode($valor, JSON_UNESCAPED_UNICODE),
            };

            $partes[] = "{$clave}: {$texto}";
        }

        return $partes === [] ? null : implode(' · ', $partes);
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
