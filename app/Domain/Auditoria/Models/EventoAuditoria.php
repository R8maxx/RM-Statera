<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Models;

use App\Domain\Auditoria\Enums\AccionAuditada;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Una entrada del log inmutable.
 *
 * NO usa `RegistraTraza`: un log que se audita a sí mismo es un bucle.
 *
 * La inmutabilidad de verdad la impone PostgreSQL (`REVOKE UPDATE, DELETE` sobre
 * `statera_app`). Lo de aquí es la segunda barrera y existe para que el error
 * llegue como una excepción con nombre en vez de como un fallo de privilegios
 * que no dice nada de la causa.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property ?int $usuario_id
 * @property string $entidad
 * @property int $entidad_id
 * @property AccionAuditada $accion
 * @property ?array<string, mixed> $valor_anterior
 * @property ?array<string, mixed> $valor_nuevo
 * @property ?string $ip
 * @property ?Carbon $created_at
 */
class EventoAuditoria extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'eventos_auditoria';

    protected $fillable = [
        'organizacion_id',
        'usuario_id',
        'entidad',
        'entidad_id',
        'accion',
        'valor_anterior',
        'valor_nuevo',
        'ip',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new RuntimeException('La traza de auditoría no se modifica. La tabla tampoco lo permite en PostgreSQL.');
        });

        static::deleting(static function (): never {
            throw new RuntimeException('La traza de auditoría no se borra. La tabla tampoco lo permite en PostgreSQL.');
        });
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
            'accion' => AccionAuditada::class,
            'valor_anterior' => 'array',
            'valor_nuevo' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
