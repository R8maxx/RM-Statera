<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Models;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\EstadoSolicitud;
use App\Domain\Plataforma\Enums\TipoRescate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una solicitud de rescate de cuenta (punto 52).
 *
 * @property int $id
 * @property int $organizacion_afectada_id
 * @property TipoRescate $tipo
 * @property ?int $cuenta_id
 * @property ?array{nombre?: string, email?: string} $datos
 * @property string $verificacion
 * @property ?int $solicitada_por
 * @property Carbon $solicitada_en
 * @property string $estado lo guardado; el que vale es `estado()`, que deriva la caducidad
 * @property ?int $resuelta_por
 * @property ?Carbon $resuelta_en
 * @property ?string $motivo_rechazo
 * @property bool $sin_segunda_persona
 * @property bool $requiere_segunda_persona si había otra persona de Administración al pedirla
 * @property-read Organizacion $organizacion
 * @property-read ?User $cuenta
 * @property-read ?User $solicitante
 * @property-read ?User $resolutor
 */
class SolicitudPlataforma extends Model
{
    /** Una solicitud pendiente deja de valer pasado este plazo. */
    public const HORAS_DE_VALIDEZ = 72;

    protected $table = 'solicitudes_plataforma';

    public $timestamps = false;

    protected $fillable = [
        'organizacion_afectada_id',
        'tipo',
        'cuenta_id',
        'datos',
        'verificacion',
        'solicitada_por',
        'solicitada_en',
        'estado',
        'requiere_segunda_persona',
    ];

    /** El estado que vale: una pendiente que pasó su plazo está caducada. */
    public function estado(?Carbon $ahora = null): EstadoSolicitud
    {
        $guardado = EstadoSolicitud::from($this->estado);

        if ($guardado === EstadoSolicitud::Pendiente
            && $this->solicitada_en->copy()->addHours(self::HORAS_DE_VALIDEZ)->lt($ahora ?? Carbon::now())) {
            return EstadoSolicitud::Caducada;
        }

        return $guardado;
    }

    /** @return BelongsTo<Organizacion, $this> */
    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_afectada_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cuenta_id');
    }

    /** @return BelongsTo<User, $this> */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitada_por');
    }

    /** @return BelongsTo<User, $this> */
    public function resolutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoRescate::class,
            'datos' => 'array',
            'solicitada_en' => 'datetime',
            'resuelta_en' => 'datetime',
            'sin_segunda_persona' => 'boolean',
            'requiere_segunda_persona' => 'boolean',
        ];
    }
}
